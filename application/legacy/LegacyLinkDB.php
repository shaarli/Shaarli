<?php

namespace Shaarli\Legacy;

use ArrayAccess;
use Countable;
use DateTime;
use Iterator;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Exceptions\IOException;
use Shaarli\Helper\FileUtils;
use Shaarli\Render\PageCacheManager;

/**
 * Data storage for bookmarks.
 *
 * This object behaves like an associative array.
 *
 * Example:
 *    $myLinks = new LinkDB();
 *    echo $myLinks[350]['title'];
 *    foreach ($myLinks as $link)
 *       echo $link['title'].' at url '.$link['url'].'; description:'.$link['description'];
 *
 * Available keys:
 *  - id:       primary key, incremental integer identifier (persistent)
 *  - description: description of the entry
 *  - created:  creation date of this entry, DateTime object.
 *  - updated:  last modification date of this entry, DateTime object.
 *  - private:  Is this link private? 0=no, other value=yes
 *  - tags:     tags attached to this entry (separated by spaces)
 *  - title     Title of the link
 *  - url       URL of the link. Used for displayable bookmarks.
 *              Can be absolute or relative in the database but the relative bookmarks
 *              will be converted to absolute ones in templates.
 *  - real_url  Raw URL in stored in the DB (absolute or relative).
 *  - shorturl  Permalink smallhash
 *
 * Implements 3 interfaces:
 *  - ArrayAccess: behaves like an associative array;
 *  - Countable:   there is a count() method;
 *  - Iterator:    usable in foreach () loops.
 *
 * ID mechanism:
 *   ArrayAccess is implemented in a way that will allow to access a link
 *   with the unique identifier ID directly with $link[ID].
 *   Note that it's not the real key of the link array attribute.
 *   This mechanism is in place to have persistent link IDs,
 *   even though the internal array is reordered by date.
 *   Example:
 *     - DB: link #1 (2010-01-01) link #2 (2016-01-01)
 *     - Order: #2 #1
 *     - Import bookmarks containing: link #3 (2013-01-01)
 *     - New DB: link #1 (2010-01-01) link #2 (2016-01-01) link #3 (2013-01-01)
 *     - Real order: #2 #3 #1
 *
 * @deprecated
 */
class LegacyLinkDB implements Iterator, Countable, ArrayAccess
{
    // Links are stored as a PHP serialized string
    private $datastore;

    // Link date storage format
    public const LINK_DATE_FORMAT = 'Ymd_His';

    // List of bookmarks (associative array)
    //  - key:   link date (e.g. "20110823_124546"),
    //  - value: associative array (keys: title, description...)
    private $links;

    // List of all recorded URLs (key=url, value=link offset)
    // for fast reserve search (url-->link offset)
    private $urls;

    /**
     * @var array List of all bookmarks IDS mapped with their array offset.
     *            Map: id->offset.
     */
    protected $ids;

    // List of offset keys (for the Iterator interface implementation)
    private $keys;

    // Position in the $this->keys array (for the Iterator interface)
    private $position;

    // Is the user logged in? (used to filter private bookmarks)
    private $loggedIn;

    // Hide public bookmarks
    private $hidePublicLinks;

    /**
     * Creates a new LinkDB
     *
     * Checks if the datastore exists; else, attempts to create a dummy one.
     *
     * @param string  $datastore        datastore file path.
     * @param boolean $isLoggedIn       is the user logged in?
     * @param boolean $hidePublicLinks  if true all bookmarks are private.
     */
    public function __construct(
        $datastore,
        $isLoggedIn,
        $hidePublicLinks
    ) {

        $this->datastore = $datastore;
        $this->loggedIn = $isLoggedIn;
        $this->hidePublicLinks = $hidePublicLinks;
        $this->check();
        $this->read();
    }

    /**
     * Countable - Counts elements of an object
     */
    public function count(): int
    {
        return count($this->links);
    }

    /**
     * ArrayAccess - Assigns a value to the specified offset
     */
    public function offsetSet($offset, $value): void
    {
        // TODO: use exceptions instead of "die"
        if (!$this->loggedIn) {
            die(t('You are not authorized to add a link.'));
        }
        if (!isset($value['id']) || empty($value['url'])) {
            die(t('Internal Error: A link should always have an id and URL.'));
        }
        if (($offset !== null && !is_int($offset)) || !is_int($value['id'])) {
            die(t('You must specify an integer as a key.'));
        }
        if ($offset !== null && $offset !== $value['id']) {
            die(t('Array offset and link ID must be equal.'));
        }

        // If the link exists, we reuse the real offset, otherwise new entry
        $existing = $this->getLinkOffset($offset);
        if ($existing !== null) {
            $offset = $existing;
        } else {
            $offset = count($this->links);
        }
        $this->links[$offset] = $value;
        $this->urls[$value['url']] = $offset;
        $this->ids[$value['id']] = $offset;
    }

    /**
     * ArrayAccess - Whether or not an offset exists
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($this->getLinkOffset($offset), $this->links);
    }

    /**
     * ArrayAccess - Unsets an offset
     */
    public function offsetUnset($offset): void
    {
        if (!$this->loggedIn) {
            // TODO: raise an exception
            die('You are not authorized to delete a link.');
        }
        $realOffset = $this->getLinkOffset($offset);
        $url = $this->links[$realOffset]['url'];
        unset($this->urls[$url]);
        unset($this->ids[$realOffset]);
        unset($this->links[$realOffset]);
    }

    /**
     * ArrayAccess - Returns the value at specified offset
     */
    public function offsetGet($offset): ?array
    {
        $realOffset = $this->getLinkOffset($offset);
        return isset($this->links[$realOffset]) ? $this->links[$realOffset] : null;
    }

    /**
     * Iterator - Returns the current element
     */
    public function current(): array
    {
        return $this[$this->keys[$this->position]];
    }

    /**
     * Iterator - Returns the key of the current element
     *
     * @return int|string
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->keys[$this->position];
    }

    /**
     * Iterator - Moves forward to next element
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Iterator - Rewinds the Iterator to the first element
     *
     * Entries are sorted by date (latest first)
     */
    public function rewind(): void
    {
        $this->keys = array_keys($this->ids);
        $this->position = 0;
    }

    /**
     * Iterator - Checks if current position is valid
     */
    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    /**
     * Checks if the DB directory and file exist
     *
     * If no DB file is found, creates a dummy DB.
     */
    private function check()
    {
        if (file_exists($this->datastore)) {
            return;
        }

        // Create a dummy database for example
        $this->links = [];
        $link = [
            'id' => 1,
            'title' => t('The personal, minimalist, super-fast, database free, bookmarking service'),
            'url' => 'https://shaarli.readthedocs.io',
            'description' => t(
                'Welcome to Shaarli! This is your first public bookmark. '
                . 'To edit or delete me, you must first login.

To learn how to use Shaarli, consult the link "Documentation" at the bottom of this page.

You use the community supported version of the original Shaarli project, by Sebastien Sauvage.'
            ),
            'private' => 0,
            'created' => new DateTime(),
            'tags' => 'opensource software',
            'sticky' => false,
        ];
        $link['shorturl'] = link_small_hash($link['created'], $link['id']);
        $this->links[1] = $link;

        $link = [
            'id' => 0,
            'title' => t('My secret stuff... - Pastebin.com'),
            'url' => 'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=',
            'description' => t('Shhhh! I\'m a private link only YOU can see. You can delete me too.'),
            'private' => 1,
            'created' => new DateTime('1 minute ago'),
            'tags' => 'secretstuff',
            'sticky' => false,
        ];
        $link['shorturl'] = link_small_hash($link['created'], $link['id']);
        $this->links[0] = $link;

        // Write database to disk
        $this->write();
    }

    /**
     * Reads database from disk to memory
     */
    private function read()
    {
        // Public bookmarks are hidden and user not logged in => nothing to show
        if ($this->hidePublicLinks && !$this->loggedIn) {
            $this->links = [];
            return;
        }

        $this->urls = [];
        $this->ids = [];
        $this->links = FileUtils::readFlatDB($this->datastore, []);

        $toremove = [];
        foreach ($this->links as $key => &$link) {
            if (!$this->loggedIn && $link['private'] != 0) {
                // Transition for not upgraded databases.
                unset($this->links[$key]);
                continue;
            }

            // Sanitize data fields.
            sanitizeLink($link);

            // Remove private tags if the user is not logged in.
            if (!$this->loggedIn) {
                $link['tags'] = preg_replace('/(^|\s+)\.[^($|\s)]+\s*/', ' ', $link['tags']);
            }

            $link['real_url'] = $link['url'];

            $link['sticky'] = isset($link['sticky']) ? $link['sticky'] : false;

            // To be able to load bookmarks before running the update, and prepare the update
            if (!isset($link['created'])) {
                $link['id'] = $link['linkdate'];
                $link['created'] = DateTime::createFromFormat(self::LINK_DATE_FORMAT, $link['linkdate']);
                if (!empty($link['updated'])) {
                    $link['updated'] = DateTime::createFromFormat(self::LINK_DATE_FORMAT, $link['updated']);
                }
                $link['shorturl'] = smallHash($link['linkdate']);
            }

            $this->urls[$link['url']] = $key;
            $this->ids[$link['id']] = $key;
        }
    }

    /**
     * Saves the database from memory to disk
     *
     * @throws IOException the datastore is not writable
     */
    private function write()
    {
        $this->reorder();
        FileUtils::writeFlatDB($this->datastore, $this->links);
    }

    /**
     * Saves the database from memory to disk
     *
     * @param string $pageCacheDir page cache directory
     */
    public function save($pageCacheDir)
    {
        if (!$this->loggedIn) {
            // TODO: raise an Exception instead
            die('You are not authorized to change the database.');
        }

        $this->write();

        $pageCacheManager = new PageCacheManager($pageCacheDir, $this->loggedIn);
        $pageCacheManager->invalidateCaches();
    }

    /**
     * Returns the link for a given URL, or False if it does not exist.
     *
     * @param string $url URL to search for
     *
     * @return mixed the existing link if it exists, else 'false'
     */
    public function getLinkFromUrl($url)
    {
        if (isset($this->urls[$url])) {
            return $this->links[$this->urls[$url]];
        }
        return false;
    }

    /**
     * Returns the shaare corresponding to a smallHash.
     *
     * @param string $request QUERY_STRING server parameter.
     *
     * @return array $filtered array containing permalink data.
     *
     * @throws BookmarkNotFoundException if the smallhash is malformed or doesn't match any link.
     */
    public function filterHash($request)
    {
        $request = substr($request, 0, 6);
        $linkFilter = new LegacyLinkFilter($this->links);
        return $linkFilter->filter(LegacyLinkFilter::$FILTER_HASH, $request);
    }

    /**
     * Returns the list of articles for a given day.
     *
     * @param string $request day to filter. Format: YYYYMMDD.
     *
     * @return array list of shaare found.
     */
    public function filterDay($request)
    {
        $linkFilter = new LegacyLinkFilter($this->links);
        return $linkFilter->filter(LegacyLinkFilter::$FILTER_DAY, $request);
    }

    /**
     * Filter bookmarks according to search parameters.
     *
     * @param array  $filterRequest  Search request content. Supported keys:
     *                                - searchtags: list of tags
     *                                - searchterm: term search
     * @param bool   $casesensitive  Optional: Perform case sensitive filter
     * @param string $visibility     return only all/private/public bookmarks
     * @param bool   $untaggedonly   return only untagged bookmarks
     *
     * @return array filtered bookmarks, all bookmarks if no suitable filter was provided.
     */
    public function filterSearch(
        $filterRequest = [],
        $casesensitive = false,
        $visibility = 'all',
        $untaggedonly = false
    ) {

        // Filter link database according to parameters.
        $searchtags = isset($filterRequest['searchtags']) ? escape($filterRequest['searchtags']) : '';
        $searchterm = isset($filterRequest['searchterm']) ? escape($filterRequest['searchterm']) : '';

        // Search tags + fullsearch - blank string parameter will return all bookmarks.
        $type = LegacyLinkFilter::$FILTER_TAG | LegacyLinkFilter::$FILTER_TEXT; // == "vuotext"
        $request = [$searchtags, $searchterm];

        $linkFilter = new LegacyLinkFilter($this);
        return $linkFilter->filter($type, $request, $casesensitive, $visibility, $untaggedonly);
    }

    /**
     * Returns the list tags appearing in the bookmarks with the given tags
     *
     * @param array  $filteringTags tags selecting the bookmarks to consider
     * @param string $visibility    process only all/private/public bookmarks
     *
     * @return array tag => linksCount
     */
    public function linksCountPerTag($filteringTags = [], $visibility = 'all')
    {
        $links = $this->filterSearch(['searchtags' => $filteringTags], false, $visibility);
        $tags = [];
        $caseMapping = [];
        foreach ($links as $link) {
            foreach (preg_split('/\s+/', $link['tags'], 0, PREG_SPLIT_NO_EMPTY) as $tag) {
                if (empty($tag)) {
                    continue;
                }
                // The first case found will be displayed.
                if (!isset($caseMapping[strtolower($tag)])) {
                    $caseMapping[strtolower($tag)] = $tag;
                    $tags[$caseMapping[strtolower($tag)]] = 0;
                }
                $tags[$caseMapping[strtolower($tag)]]++;
            }
        }

        /*
         * Formerly used arsort(), which doesn't define the sort behaviour for equal values.
         * Also, this function doesn't produce the same result between PHP 5.6 and 7.
         *
         * So we now use array_multisort() to sort tags by DESC occurrences,
         * then ASC alphabetically for equal values.
         *
         * @see https://github.com/shaarli/Shaarli/issues/1142
         */
        $keys = array_keys($tags);
        $tmpTags = array_combine($keys, $keys);
        array_multisort($tags, SORT_DESC, $tmpTags, SORT_ASC, $tags);
        return $tags;
    }

    /**
     * Rename or delete a tag across all bookmarks.
     *
     * @param string $from Tag to rename
     * @param string $to   New tag. If none is provided, the from tag will be deleted
     *
     * @return array|bool List of altered bookmarks or false on error
     */
    public function renameTag($from, $to)
    {
        if (empty($from)) {
            return false;
        }
        $delete = empty($to);
        // True for case-sensitive tag search.
        $linksToAlter = $this->filterSearch(['searchtags' => $from], true);
        foreach ($linksToAlter as $key => &$value) {
            $tags = preg_split('/\s+/', trim($value['tags']));
            if (($pos = array_search($from, $tags)) !== false) {
                if ($delete) {
                    unset($tags[$pos]); // Remove tag.
                } else {
                    $tags[$pos] = trim($to);
                }
                $value['tags'] = trim(implode(' ', array_unique($tags)));
                $this[$value['id']] = $value;
            }
        }

        return $linksToAlter;
    }

    /**
     * Returns the list of days containing articles (oldest first)
     * Output: An array containing days (in format YYYYMMDD).
     */
    public function days()
    {
        $linkDays = [];
        foreach ($this->links as $link) {
            $linkDays[$link['created']->format('Ymd')] = 0;
        }
        $linkDays = array_keys($linkDays);
        sort($linkDays);

        return $linkDays;
    }

    /**
     * Reorder bookmarks by creation date (newest first).
     *
     * Also update the urls and ids mapping arrays.
     *
     * @param string $order ASC|DESC
     */
    public function reorder($order = 'DESC')
    {
        $order = $order === 'ASC' ? -1 : 1;
        // Reorder array by dates.
        usort($this->links, function ($a, $b) use ($order) {
            if (isset($a['sticky']) && isset($b['sticky']) && $a['sticky'] !== $b['sticky']) {
                return $a['sticky'] ? -1 : 1;
            }
            if ($a['created'] == $b['created']) {
                return $a['id'] < $b['id'] ? 1 * $order : -1 * $order;
            }
            return $a['created'] < $b['created'] ? 1 * $order : -1 * $order;
        });

        $this->urls = [];
        $this->ids = [];
        foreach ($this->links as $key => $link) {
            $this->urls[$link['url']] = $key;
            $this->ids[$link['id']] = $key;
        }
    }

    /**
     * Return the next key for link creation.
     * E.g. If the last ID is 597, the next will be 598.
     *
     * @return int next ID.
     */
    public function getNextId()
    {
        if (!empty($this->ids)) {
            return max(array_keys($this->ids)) + 1;
        }
        return 0;
    }

    /**
     * Returns a link offset in bookmarks array from its unique ID.
     *
     * @param int $id Persistent ID of a link.
     *
     * @return int Real offset in local array, or null if doesn't exist.
     */
    protected function getLinkOffset($id)
    {
        if (isset($this->ids[$id])) {
            return $this->ids[$id];
        }
        return null;
    }
}

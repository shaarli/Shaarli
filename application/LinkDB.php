<?php
/**
 * Data storage for links.
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
 *  - url       URL of the link. Used for displayable links (no redirector, relative, etc.).
 *              Can be absolute or relative.
 *              Relative URLs are permalinks (e.g.'?m-ukcw')
 *  - real_url  Absolute processed URL.
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
 *     - Import links containing: link #3 (2013-01-01)
 *     - New DB: link #1 (2010-01-01) link #2 (2016-01-01) link #3 (2013-01-01)
 *     - Real order: #2 #3 #1
 */
class LinkDB implements Iterator, Countable, ArrayAccess
{
    // Links are stored as a PHP serialized string
    private $datastore;

    // Link date storage format
    const LINK_DATE_FORMAT = 'Ymd_His';

    // Datastore PHP prefix
    protected static $phpPrefix = '<?php /* ';

    // Datastore PHP suffix
    protected static $phpSuffix = ' */ ?>';

    // List of links (associative array)
    //  - key:   link date (e.g. "20110823_124546"),
    //  - value: associative array (keys: title, description...)
    private $links;

    // List of all recorded URLs (key=url, value=link offset)
    // for fast reserve search (url-->link offset)
    private $urls;

    /**
     * @var array List of all links IDS mapped with their array offset.
     *            Map: id->offset.
     */
    protected $ids;

    // List of offset keys (for the Iterator interface implementation)
    private $keys;

    // Position in the $this->keys array (for the Iterator interface)
    private $position;

    // Is the user logged in? (used to filter private links)
    private $loggedIn;

    // Hide public links
    private $hidePublicLinks;

    // link redirector set in user settings.
    private $redirector;

    /**
     * Set this to `true` to urlencode link behind redirector link, `false` to leave it untouched.
     *
     * Example:
     *   anonym.to needs clean URL while dereferer.org needs urlencoded URL.
     *
     * @var boolean $redirectorEncode parameter: true or false
     */
    private $redirectorEncode;

    /**
     * Creates a new LinkDB
     *
     * Checks if the datastore exists; else, attempts to create a dummy one.
     *
     * @param string  $datastore        datastore file path.
     * @param boolean $isLoggedIn       is the user logged in?
     * @param boolean $hidePublicLinks  if true all links are private.
     * @param string  $redirector       link redirector set in user settings.
     * @param boolean $redirectorEncode Enable urlencode on redirected urls (default: true).
     */
    public function __construct(
        $datastore,
        $isLoggedIn,
        $hidePublicLinks,
        $redirector = '',
        $redirectorEncode = true
    )
    {
        $this->datastore = $datastore;
        $this->loggedIn = $isLoggedIn;
        $this->hidePublicLinks = $hidePublicLinks;
        $this->redirector = $redirector;
        $this->redirectorEncode = $redirectorEncode === true;
        $this->check();
        $this->read();
    }

    /**
     * Countable - Counts elements of an object
     */
    public function count()
    {
        return count($this->links);
    }

    /**
     * ArrayAccess - Assigns a value to the specified offset
     */
    public function offsetSet($offset, $value)
    {
        // TODO: use exceptions instead of "die"
        if (!$this->loggedIn) {
            die('You are not authorized to add a link.');
        }
        if (!isset($value['id']) || empty($value['url'])) {
            die('Internal Error: A link should always have an id and URL.');
        }
        if (($offset !== null && ! is_int($offset)) || ! is_int($value['id'])) {
            die('You must specify an integer as a key.');
        }
        if ($offset !== null && $offset !== $value['id']) {
            die('Array offset and link ID must be equal.');
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
    public function offsetExists($offset)
    {
        return array_key_exists($this->getLinkOffset($offset), $this->links);
    }

    /**
     * ArrayAccess - Unsets an offset
     */
    public function offsetUnset($offset)
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
    public function offsetGet($offset)
    {
        $realOffset = $this->getLinkOffset($offset);
        return isset($this->links[$realOffset]) ? $this->links[$realOffset] : null;
    }

    /**
     * Iterator - Returns the current element
     */
    public function current()
    {
        return $this[$this->keys[$this->position]];
    }

    /**
     * Iterator - Returns the key of the current element
     */
    public function key()
    {
        return $this->keys[$this->position];
    }

    /**
     * Iterator - Moves forward to next element
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * Iterator - Rewinds the Iterator to the first element
     *
     * Entries are sorted by date (latest first)
     */
    public function rewind()
    {
        $this->keys = array_keys($this->ids);
        $this->position = 0;
    }

    /**
     * Iterator - Checks if current position is valid
     */
    public function valid()
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
        $this->links = array();
        $link = array(
            'id' => 1,
            'title'=>' Shaarli: the personal, minimalist, super-fast, no-database delicious clone',
            'url'=>'https://github.com/shaarli/Shaarli/wiki',
            'description'=>'Welcome to Shaarli! This is your first public bookmark. To edit or delete me, you must first login.

To learn how to use Shaarli, consult the link "Help/documentation" at the bottom of this page.

You use the community supported version of the original Shaarli project, by Sebastien Sauvage.',
            'private'=>0,
            'created'=> new DateTime(),
            'tags'=> array('open source', 'software')
        );
        $link['shorturl'] = link_small_hash($link['created'], $link['id']);
        $this->links[1] = $link;

        $link = array(
            'id' => 0,
            'title'=>'My secret stuff... - Pastebin.com',
            'url'=>'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=',
            'description'=>'Shhhh! I\'m a private link only YOU can see. You can delete me too.',
            'private'=>1,
            'created'=> new DateTime('1 minute ago'),
            'tags'=> array('secret stuff'),
        );
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
        // Public links are hidden and user not logged in => nothing to show
        if ($this->hidePublicLinks && !$this->loggedIn) {
            $this->links = array();
            return;
        }

        // Read data
        // Note that gzinflate is faster than gzuncompress.
        // See: http://www.php.net/manual/en/function.gzdeflate.php#96439
        $this->links = array();

        if (file_exists($this->datastore)) {
            $this->links = unserialize(gzinflate(base64_decode(
                substr(file_get_contents($this->datastore),
                       strlen(self::$phpPrefix), -strlen(self::$phpSuffix)))));
        }

        $toremove = array();
        foreach ($this->links as $key => &$link) {
            if (! $this->loggedIn && $link['private'] != 0) {
                // Transition for not upgraded databases.
                $toremove[] = $key;
                continue;
            }

            // Sanitize data fields.
            sanitizeLink($link);

            // Remove private tags if the user is not logged in.
            if (! $this->loggedIn) {
                foreach($link['tags'] as $id => $tag) {
                    if (strlen($tag) > 0 && $tag[0] == '.') {
                        unset($link['tags'][$id]);
                    }
                }
            }

            // Do not use the redirector for internal links (Shaarli note URL starting with a '?').
            if (!empty($this->redirector) && !startsWith($link['url'], '?')) {
                $link['real_url'] = $this->redirector;
                if ($this->redirectorEncode) {
                    $link['real_url'] .= urlencode(unescape($link['url']));
                } else {
                    $link['real_url'] .= $link['url'];
                }
            }
            else {
                $link['real_url'] = $link['url'];
            }

            // To be able to load links before running the update, and prepare the update
            if (! isset($link['created'])) {
                $link['id'] = $link['linkdate'];
                $link['created'] = DateTime::createFromFormat(self::LINK_DATE_FORMAT, $link['linkdate']);
                if (! empty($link['updated'])) {
                    $link['updated'] = DateTime::createFromFormat(self::LINK_DATE_FORMAT, $link['updated']);
                }
                $link['shorturl'] = smallHash($link['linkdate']);
            }
        }

        // If user is not logged in, filter private links.
        foreach ($toremove as $offset) {
            unset($this->links[$offset]);
        }

        $this->reorder();
    }

    /**
     * Saves the database from memory to disk
     *
     * @throws IOException the datastore is not writable
     */
    private function write()
    {
        if (is_file($this->datastore) && !is_writeable($this->datastore)) {
            // The datastore exists but is not writeable
            throw new IOException($this->datastore);
        } else if (!is_file($this->datastore) && !is_writeable(dirname($this->datastore))) {
            // The datastore does not exist and its parent directory is not writeable
            throw new IOException(dirname($this->datastore));
        }

        file_put_contents(
            $this->datastore,
            self::$phpPrefix.base64_encode(gzdeflate(serialize($this->links))).self::$phpSuffix
        );

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

        invalidateCaches($pageCacheDir);
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
     * @throws LinkNotFoundException if the smallhash is malformed or doesn't match any link.
     */
    public function filterHash($request)
    {
        $request = substr($request, 0, 6);
        $linkFilter = new LinkFilter($this->links);
        return $linkFilter->filter(LinkFilter::$FILTER_HASH, $request);
    }

    /**
     * Returns the list of articles for a given day.
     *
     * @param string $request day to filter. Format: YYYYMMDD.
     *
     * @return array list of shaare found.
     */
    public function filterDay($request) {
        $linkFilter = new LinkFilter($this->links);
        return $linkFilter->filter(LinkFilter::$FILTER_DAY, $request);
    }

    /**
     * Filter links according to search parameters.
     *
     * @param array  $filterRequest Search request content. Supported keys:
     *                                - searchtags: list of tags
     *                                - searchterm: term search
     * @param bool   $casesensitive Optional: Perform case sensitive filter
     * @param string $visibility    return only all/private/public links
     *
     * @return array filtered links, all links if no suitable filter was provided.
     */
    public function filterSearch($filterRequest = array(), $casesensitive = false, $visibility = 'all')
    {
        // Filter link database according to parameters.
        $searchtags = !empty($filterRequest['searchtags']) ? $filterRequest['searchtags'] : array();
        $searchterm = !empty($filterRequest['searchterm']) ? escape($filterRequest['searchterm']) : '';

        // Search tags + fullsearch.
        if (! empty($searchtags) && ! empty($searchterm)) {
            $type = LinkFilter::$FILTER_TAG | LinkFilter::$FILTER_TEXT;
            $request = array($searchtags, $searchterm);
        }
        // Search by tags.
        elseif (! empty($searchtags)) {
            $type = LinkFilter::$FILTER_TAG;
            $request = $searchtags;
        }
        // Fulltext search.
        elseif (! empty($searchterm)) {
            $type = LinkFilter::$FILTER_TEXT;
            $request = $searchterm;
        }
        // Otherwise, display without filtering.
        else {
            $type = '';
            $request = '';
        }

        $linkFilter = new LinkFilter($this);
        return $linkFilter->filter($type, $request, $casesensitive, $visibility);
    }

    /**
     * Returns the list of all tags
     * Output: associative array key=tags, value=count
     */
    public function allTags()
    {
        $tags = array();
        $caseMapping = array();
        foreach ($this->links as $link) {
            foreach ($link['tags'] as $tag) {
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
        // Sort tags by usage (most used tag first)
        arsort($tags);
        return $tags;
    }

    /**
     * Returns the list of days containing articles (oldest first)
     * Output: An array containing days (in format YYYYMMDD).
     */
    public function days()
    {
        $linkDays = array();
        foreach ($this->links as $link) {
            $linkDays[$link['created']->format('Ymd')] = 0;
        }
        $linkDays = array_keys($linkDays);
        sort($linkDays);

        return $linkDays;
    }

    /**
     * Reorder links by creation date (newest first).
     *
     * Also update the urls and ids mapping arrays.
     *
     * @param string $order ASC|DESC
     */
    public function reorder($order = 'DESC')
    {
        $order = $order === 'ASC' ? -1 : 1;
        // Reorder array by dates.
        usort($this->links, function($a, $b) use ($order) {
            return $a['created'] < $b['created'] ? 1 * $order : -1 * $order;
        });

        $this->urls = array();
        $this->ids = array();
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
     * Returns a link offset in links array from its unique ID.
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

<?php
/**
 * Data storage for links.
 *
 * This object behaves like an associative array.
 *
 * Example:
 *    $myLinks = new LinkDB();
 *    echo $myLinks['20110826_161819']['title'];
 *    foreach ($myLinks as $link)
 *       echo $link['title'].' at url '.$link['url'].'; description:'.$link['description'];
 *
 * Available keys:
 *  - description: description of the entry
 *  - linkdate: date of the creation of this entry, in the form YYYYMMDD_HHMMSS
 *              (e.g.'20110914_192317')
 *  - private:  Is this link private? 0=no, other value=yes
 *  - tags:     tags attached to this entry (separated by spaces)
 *  - title     Title of the link
 *  - url       URL of the link. Used for displayable links (no redirector, relative, etc.).
 *              Can be absolute or relative.
 *              Relative URLs are permalinks (e.g.'?m-ukcw')
 *  - real_url  Absolute processed URL.
 *
 * Implements 3 interfaces:
 *  - ArrayAccess: behaves like an associative array;
 *  - Countable:   there is a count() method;
 *  - Iterator:    usable in foreach () loops.
 */
class LinkDB implements Iterator, Countable, ArrayAccess
{
    // Links are stored as a PHP serialized string
    private $_datastore;

    // Link date storage format
    const LINK_DATE_FORMAT = 'Ymd_His';

    // Datastore PHP prefix
    protected static $phpPrefix = '<?php /* ';

    // Datastore PHP suffix
    protected static $phpSuffix = ' */ ?>';

    // List of links (associative array)
    //  - key:   link date (e.g. "20110823_124546"),
    //  - value: associative array (keys: title, description...)
    private $_links;

    // List of all recorded URLs (key=url, value=linkdate)
    // for fast reserve search (url-->linkdate)
    private $_urls;

    // List of linkdate keys (for the Iterator interface implementation)
    private $_keys;

    // Position in the $this->_keys array (for the Iterator interface)
    private $_position;

    // Is the user logged in? (used to filter private links)
    private $_loggedIn;

    // Hide public links
    private $_hidePublicLinks;

    // link redirector set in user settings.
    private $_redirector;

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
    function __construct(
        $datastore,
        $isLoggedIn,
        $hidePublicLinks,
        $redirector = '',
        $redirectorEncode = true
    )
    {
        $this->_datastore = $datastore;
        $this->_loggedIn = $isLoggedIn;
        $this->_hidePublicLinks = $hidePublicLinks;
        $this->_redirector = $redirector;
        $this->redirectorEncode = $redirectorEncode === true;
        $this->_checkDB();
        $this->_readDB();
    }

    /**
     * Countable - Counts elements of an object
     */
    public function count()
    {
        return count($this->_links);
    }

    /**
     * ArrayAccess - Assigns a value to the specified offset
     */
    public function offsetSet($offset, $value)
    {
        // TODO: use exceptions instead of "die"
        if (!$this->_loggedIn) {
            die('You are not authorized to add a link.');
        }
        if (empty($value['linkdate']) || empty($value['url'])) {
            die('Internal Error: A link should always have a linkdate and URL.');
        }
        if (empty($offset)) {
            die('You must specify a key.');
        }
        $this->_links[$offset] = $value;
        $this->_urls[$value['url']]=$offset;
    }

    /**
     * ArrayAccess - Whether or not an offset exists
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_links);
    }

    /**
     * ArrayAccess - Unsets an offset
     */
    public function offsetUnset($offset)
    {
        if (!$this->_loggedIn) {
            // TODO: raise an exception
            die('You are not authorized to delete a link.');
        }
        $url = $this->_links[$offset]['url'];
        unset($this->_urls[$url]);
        unset($this->_links[$offset]);
    }

    /**
     * ArrayAccess - Returns the value at specified offset
     */
    public function offsetGet($offset)
    {
        return isset($this->_links[$offset]) ? $this->_links[$offset] : null;
    }

    /**
     * Iterator - Returns the current element
     */
    function current()
    {
        return $this->_links[$this->_keys[$this->_position]];
    }

    /**
     * Iterator - Returns the key of the current element
     */
    function key()
    {
        return $this->_keys[$this->_position];
    }

    /**
     * Iterator - Moves forward to next element
     */
    function next()
    {
        ++$this->_position;
    }

    /**
     * Iterator - Rewinds the Iterator to the first element
     *
     * Entries are sorted by date (latest first)
     */
    function rewind()
    {
        $this->_keys = array_keys($this->_links);
        rsort($this->_keys);
        $this->_position = 0;
    }

    /**
     * Iterator - Checks if current position is valid
     */
    function valid()
    {
        return isset($this->_keys[$this->_position]);
    }

    /**
     * Checks if the DB directory and file exist
     *
     * If no DB file is found, creates a dummy DB.
     */
    private function _checkDB()
    {
        if (file_exists($this->_datastore)) {
            return;
        }

        // Create a dummy database for example
        $this->_links = array();
        $link = array(
            'title'=>' Shaarli: the personal, minimalist, super-fast, no-database delicious clone',
            'url'=>'https://github.com/shaarli/Shaarli/wiki',
            'description'=>'Welcome to Shaarli! This is your first public bookmark. To edit or delete me, you must first login.

To learn how to use Shaarli, consult the link "Help/documentation" at the bottom of this page.

You use the community supported version of the original Shaarli project, by Sebastien Sauvage.',
            'private'=>0,
            'linkdate'=> date('Ymd_His'),
            'tags'=>'opensource software'
        );
        $this->_links[$link['linkdate']] = $link;

        $link = array(
            'title'=>'My secret stuff... - Pastebin.com',
            'url'=>'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=',
            'description'=>'Shhhh! I\'m a private link only YOU can see. You can delete me too.',
            'private'=>1,
            'linkdate'=> date('Ymd_His', strtotime('-1 minute')),
            'tags'=>'secretstuff'
        );
        $this->_links[$link['linkdate']] = $link;

        // Write database to disk
        $this->writeDB();
    }

    /**
     * Reads database from disk to memory
     */
    private function _readDB()
    {

        // Public links are hidden and user not logged in => nothing to show
        if ($this->_hidePublicLinks && !$this->_loggedIn) {
            $this->_links = array();
            return;
        }

        // Read data
        // Note that gzinflate is faster than gzuncompress.
        // See: http://www.php.net/manual/en/function.gzdeflate.php#96439
        $this->_links = array();

        if (file_exists($this->_datastore)) {
            $this->_links = unserialize(gzinflate(base64_decode(
                substr(file_get_contents($this->_datastore),
                       strlen(self::$phpPrefix), -strlen(self::$phpSuffix)))));
        }

        // If user is not logged in, filter private links.
        if (!$this->_loggedIn) {
            $toremove = array();
            foreach ($this->_links as $link) {
                if ($link['private'] != 0) {
                    $toremove[] = $link['linkdate'];
                }
            }
            foreach ($toremove as $linkdate) {
                unset($this->_links[$linkdate]);
            }
        }

        $this->_urls = array();
        foreach ($this->_links as &$link) {
            // Keep the list of the mapping URLs-->linkdate up-to-date.
            $this->_urls[$link['url']] = $link['linkdate'];

            // Sanitize data fields.
            sanitizeLink($link);

            // Remove private tags if the user is not logged in.
            if (! $this->_loggedIn) {
                $link['tags'] = preg_replace('/(^|\s+)\.[^($|\s)]+\s*/', ' ', $link['tags']);
            }

            // Do not use the redirector for internal links (Shaarli note URL starting with a '?').
            if (!empty($this->_redirector) && !startsWith($link['url'], '?')) {
                $link['real_url'] = $this->_redirector;
                if ($this->redirectorEncode) {
                    $link['real_url'] .= urlencode(unescape($link['url']));
                } else {
                    $link['real_url'] .= $link['url'];
                }
            }
            else {
                $link['real_url'] = $link['url'];
            }
        }
    }

    /**
     * Saves the database from memory to disk
     *
     * @throws IOException the datastore is not writable
     */
    private function writeDB()
    {
        if (is_file($this->_datastore) && !is_writeable($this->_datastore)) {
            // The datastore exists but is not writeable
            throw new IOException($this->_datastore);
        } else if (!is_file($this->_datastore) && !is_writeable(dirname($this->_datastore))) {
            // The datastore does not exist and its parent directory is not writeable
            throw new IOException(dirname($this->_datastore));
        }

        file_put_contents(
            $this->_datastore,
            self::$phpPrefix.base64_encode(gzdeflate(serialize($this->_links))).self::$phpSuffix
        );

    }

    /**
     * Saves the database from memory to disk
     *
     * @param string $pageCacheDir page cache directory
     */
    public function savedb($pageCacheDir)
    {
        if (!$this->_loggedIn) {
            // TODO: raise an Exception instead
            die('You are not authorized to change the database.');
        }

        $this->writeDB();

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
        if (isset($this->_urls[$url])) {
            return $this->_links[$this->_urls[$url]];
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
        $linkFilter = new LinkFilter($this->_links);
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
        $linkFilter = new LinkFilter($this->_links);
        return $linkFilter->filter(LinkFilter::$FILTER_DAY, $request);
    }

    /**
     * Filter links according to search parameters.
     *
     * @param array  $filterRequest Search request content. Supported keys:
     *                                - searchtags: list of tags
     *                                - searchterm: term search
     * @param bool   $casesensitive Optional: Perform case sensitive filter
     * @param bool   $privateonly   Optional: Returns private links only if true.
     *
     * @return array filtered links, all links if no suitable filter was provided.
     */
    public function filterSearch($filterRequest = array(), $casesensitive = false, $privateonly = false)
    {
        // Filter link database according to parameters.
        $searchtags = !empty($filterRequest['searchtags']) ? escape($filterRequest['searchtags']) : '';
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

        $linkFilter = new LinkFilter($this->_links);
        return $linkFilter->filter($type, $request, $casesensitive, $privateonly);
    }

    /**
     * Returns the list of all tags
     * Output: associative array key=tags, value=0
     */
    public function allTags()
    {
        $tags = array();
        $caseMapping = array();
        foreach ($this->_links as $link) {
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
        foreach (array_keys($this->_links) as $day) {
            $linkDays[substr($day, 0, 8)] = 0;
        }
        $linkDays = array_keys($linkDays);
        sort($linkDays);

        return $linkDays;
    }

    /**
     * Reload all links from the datastore.
     */
    public function refresh()
    {
        $this->_readDB();
    }

    /**
     * Force the user login state.
     *
     * @param boolean $loggedIn true logged in user (can load private links).
     */
    public function setLoggedIn($loggedIn)
    {
        $this->_loggedIn = $loggedIn;
    }
}

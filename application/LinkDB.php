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
 *  - url       URL of the link. Can be absolute or relative.
 *              Relative URLs are permalinks (e.g.'?m-ukcw')
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

    /**
     * Creates a new LinkDB
     *
     * Checks if the datastore exists; else, attempts to create a dummy one.
     *
     * @param $isLoggedIn is the user logged in?
     */
    function __construct($datastore, $isLoggedIn, $hidePublicLinks)
    {
        $this->_datastore = $datastore;
        $this->_loggedIn = $isLoggedIn;
        $this->_hidePublicLinks = $hidePublicLinks;
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
            'linkdate'=>'20110914_190000',
            'tags'=>'opensource software'
        );
        $this->_links[$link['linkdate']] = $link;

        $link = array(
            'title'=>'My secret stuff... - Pastebin.com',
            'url'=>'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=',
            'description'=>'Shhhh! I\'m a private link only YOU can see. You can delete me too.',
            'private'=>1,
            'linkdate'=>'20110914_074522',
            'tags'=>'secretstuff'
        );
        $this->_links[$link['linkdate']] = $link;

        // Write database to disk
        // TODO: raise an exception if the file is not write-able
        file_put_contents(
            $this->_datastore,
            self::$phpPrefix.base64_encode(gzdeflate(serialize($this->_links))).self::$phpSuffix
        );
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

        // Keep the list of the mapping URLs-->linkdate up-to-date.
        $this->_urls = array();
        foreach ($this->_links as $link) {
            $this->_urls[$link['url']] = $link['linkdate'];
        }

        // Escape links data
        foreach($this->_links as &$link) { 
            sanitizeLink($link); 
        }
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
        file_put_contents(
            $this->_datastore,
            self::$phpPrefix.base64_encode(gzdeflate(serialize($this->_links))).self::$phpSuffix
        );
        invalidateCaches($pageCacheDir);
    }

    /**
     * Returns the link for a given URL, or False if it does not exist.
     */
    public function getLinkFromUrl($url)
    {
        if (isset($this->_urls[$url])) {
            return $this->_links[$this->_urls[$url]];
        }
        return false;
    }

    /**
     * Returns the list of links corresponding to a full-text search
     *
     * Searches:
     *  - in the URLs, title and description;
     *  - are case-insensitive.
     *
     * Example:
     *    print_r($mydb->filterFulltext('hollandais'));
     *
     * mb_convert_case($val, MB_CASE_LOWER, 'UTF-8')
     *  - allows to perform searches on Unicode text
     *  - see https://github.com/shaarli/Shaarli/issues/75 for examples
     */
    public function filterFulltext($searchterms)
    {
        // FIXME: explode(' ',$searchterms) and perform a AND search.
        // FIXME: accept double-quotes to search for a string "as is"?
        $filtered = array();
        $search = mb_convert_case($searchterms, MB_CASE_LOWER, 'UTF-8');
        $keys = array('title', 'description', 'url', 'tags');

        foreach ($this->_links as $link) {
            $found = false;

            foreach ($keys as $key) {
                if (strpos(mb_convert_case($link[$key], MB_CASE_LOWER, 'UTF-8'),
                           $search) !== false) {
                    $found = true;
                }
            }

            if ($found) {
                $filtered[$link['linkdate']] = $link;
            }
        }
        krsort($filtered);
        return $filtered;
    }

    /**
     * Returns the list of links associated with a given list of tags
     *
     * You can specify one or more tags, separated by space or a comma, e.g.
     *  print_r($mydb->filterTags('linux programming'));
     */
    public function filterTags($tags, $casesensitive=false)
    {
        // Same as above, we use UTF-8 conversion to handle various graphemes (i.e. cyrillic, or greek)
        // FIXME: is $casesensitive ever true?
        $t = str_replace(
            ',', ' ',
            ($casesensitive ? $tags : mb_convert_case($tags, MB_CASE_LOWER, 'UTF-8'))
        );

        $searchtags = explode(' ', $t);
        $filtered = array();

        foreach ($this->_links as $l) {
            $linktags = explode(
                ' ',
                ($casesensitive ? $l['tags']:mb_convert_case($l['tags'], MB_CASE_LOWER, 'UTF-8'))
            );

            if (count(array_intersect($linktags, $searchtags)) == count($searchtags)) {
                $filtered[$l['linkdate']] = $l;
            }
        }
        krsort($filtered);
        return $filtered;
    }


    /**
     * Returns the list of articles for a given day, chronologically sorted
     *
     * Day must be in the form 'YYYYMMDD' (e.g. '20120125'), e.g.
     *  print_r($mydb->filterDay('20120125'));
     */
    public function filterDay($day)
    {
        if (! checkDateFormat('Ymd', $day)) {
            throw new Exception('Invalid date format');
        }

        $filtered = array();
        foreach ($this->_links as $l) {
            if (startsWith($l['linkdate'], $day)) {
                $filtered[$l['linkdate']] = $l;
            }
        }
        ksort($filtered);
        return $filtered;
    }

    /**
     * Returns the article corresponding to a smallHash
     */
    public function filterSmallHash($smallHash)
    {
        $filtered = array();
        foreach ($this->_links as $l) {
            if ($smallHash == smallHash($l['linkdate'])) {
                // Yes, this is ugly and slow
                $filtered[$l['linkdate']] = $l;
                return $filtered;
            }
        }
        return $filtered;
    }

    /**
     * Returns the list of all tags
     * Output: associative array key=tags, value=0
     */
    public function allTags()
    {
        $tags = array();
        foreach ($this->_links as $link) {
            foreach (explode(' ', $link['tags']) as $tag) {
                if (!empty($tag)) {
                    $tags[$tag] = (empty($tags[$tag]) ? 1 : $tags[$tag] + 1);
                }
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
}

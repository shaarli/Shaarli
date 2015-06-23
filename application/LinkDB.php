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
    // List of links (associative array)
    //  - key:   link date (e.g. "20110823_124546"),
    //  - value: associative array (keys: title, description...)
    private $links;

    // List of all recorded URLs (key=url, value=linkdate)
    // for fast reserve search (url-->linkdate)
    private $urls;

    // List of linkdate keys (for the Iterator interface implementation)
    private $keys;

    // Position in the $this->keys array (for the Iterator interface)
    private $position;

    // Is the user logged in? (used to filter private links)
    private $loggedIn;

    /**
     * Creates a new LinkDB
     *
     * Checks if the datastore exists; else, attempts to create a dummy one.
     *
     * @param $isLoggedIn is the user logged in?
     */
    function __construct($isLoggedIn)
    {
        // FIXME: do not access $GLOBALS, pass the datastore instead
        $this->loggedIn = $isLoggedIn;
        $this->checkDB();
        $this->readdb();
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
        if (empty($value['linkdate']) || empty($value['url'])) {
            die('Internal Error: A link should always have a linkdate and URL.');
        }
        if (empty($offset)) {
            die('You must specify a key.');
        }
        $this->links[$offset] = $value;
        $this->urls[$value['url']]=$offset;
    }

    /**
     * ArrayAccess - Whether or not an offset exists
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->links);
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
        $url = $this->links[$offset]['url'];
        unset($this->urls[$url]);
        unset($this->links[$offset]);
    }

    /**
     * ArrayAccess - Returns the value at specified offset
     */
    public function offsetGet($offset)
    {
        return isset($this->links[$offset]) ? $this->links[$offset] : null;
    }

    /**
     * Iterator - Returns the current element
     */
    function current()
    {
        return $this->links[$this->keys[$this->position]];
    }

    /**
     * Iterator - Returns the key of the current element
     */
    function key()
    {
        return $this->keys[$this->position];
    }

    /**
     * Iterator - Moves forward to next element
     */
    function next()
    {
        ++$this->position;
    }

    /**
     * Iterator - Rewinds the Iterator to the first element
     *
     * Entries are sorted by date (latest first)
     */
    function rewind()
    {
        $this->keys = array_keys($this->links);
        rsort($this->keys);
        $this->position = 0;
    }

    /**
     * Iterator - Checks if current position is valid
     */
    function valid()
    {
        return isset($this->keys[$this->position]);
    }

    /**
     * Checks if the DB directory and file exist
     *
     * If no DB file is found, creates a dummy DB.
     */
    private function checkDB()
    {
        if (file_exists($GLOBALS['config']['DATASTORE'])) {
            return;
        }

        // Create a dummy database for example
        $this->links = array();
        $link = array(
            'title'=>'Shaarli - sebsauvage.net',
            'url'=>'http://sebsauvage.net/wiki/doku.php?id=php:shaarli',
            'description'=>'Welcome to Shaarli! This is a bookmark. To edit or delete me, you must first login.',
            'private'=>0,
            'linkdate'=>'20110914_190000',
            'tags'=>'opensource software'
        );
        $this->links[$link['linkdate']] = $link;

        $link = array(
            'title'=>'My secret stuff... - Pastebin.com',
            'url'=>'http://sebsauvage.net/paste/?8434b27936c09649#bR7XsXhoTiLcqCpQbmOpBi3rq2zzQUC5hBI7ZT1O3x8=',
            'description'=>'SShhhh!!  I\'m a private link only YOU can see. You can delete me too.',
            'private'=>1,
            'linkdate'=>'20110914_074522',
            'tags'=>'secretstuff'
        );
        $this->links[$link['linkdate']] = $link;

        // Write database to disk
        // TODO: raise an exception if the file is not write-able
        file_put_contents(
            // FIXME: do not use $GLOBALS
            $GLOBALS['config']['DATASTORE'],
            PHPPREFIX.base64_encode(gzdeflate(serialize($this->links))).PHPSUFFIX
        );
    }

    /**
     * Reads database from disk to memory
     */
    private function readdb()
    {

        // Public links are hidden and user not logged in => nothing to show
        if ($GLOBALS['config']['HIDE_PUBLIC_LINKS'] && !$this->loggedIn) {
            $this->links = array();
            return;
        }

        // Read data
        // Note that gzinflate is faster than gzuncompress.
        // See: http://www.php.net/manual/en/function.gzdeflate.php#96439
        // FIXME: do not use $GLOBALS
        $this->links = array();

        if (file_exists($GLOBALS['config']['DATASTORE'])) {
            $this->links = unserialize(gzinflate(base64_decode(
                substr(file_get_contents($GLOBALS['config']['DATASTORE']),
                       strlen(PHPPREFIX), -strlen(PHPSUFFIX)))));
        }

        // If user is not logged in, filter private links.
        if (!$this->loggedIn) {
            $toremove = array();
            foreach ($this->links as $link) {
                if ($link['private'] != 0) {
                    $toremove[] = $link['linkdate'];
                }
            }
            foreach ($toremove as $linkdate) {
                unset($this->links[$linkdate]);
            }
        }

        // Keep the list of the mapping URLs-->linkdate up-to-date.
        $this->urls = array();
        foreach ($this->links as $link) {
            $this->urls[$link['url']] = $link['linkdate'];
        }
    }

    /**
     * Saves the database from memory to disk
     */
    public function savedb()
    {
        if (!$this->loggedIn) {
            // TODO: raise an Exception instead
            die('You are not authorized to change the database.');
        }
        file_put_contents(
            $GLOBALS['config']['DATASTORE'],
            PHPPREFIX.base64_encode(gzdeflate(serialize($this->links))).PHPSUFFIX
        );
        invalidateCaches();
    }

    /**
     * Returns the link for a given URL, or False if it does not exist.
     */
    public function getLinkFromUrl($url)
    {
        if (isset($this->urls[$url])) {
            return $this->links[$this->urls[$url]];
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
        $keys = ['title', 'description', 'url', 'tags'];

        foreach ($this->links as $link) {
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

        foreach ($this->links as $l) {
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
        // TODO: check input format
        $filtered = array();
        foreach ($this->links as $l) {
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
        foreach ($this->links as $l) {
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
        foreach ($this->links as $link) {
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
        foreach (array_keys($this->links) as $day) {
            $linkDays[substr($day, 0, 8)] = 0;
        }
        $linkDays = array_keys($linkDays);
        sort($linkDays);
        return $linkDays;
    }
}
?>

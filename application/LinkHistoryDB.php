<?php
/**
 * Data storage for links history.
 *
 * This object behaves like an associative array.
 *
 * Example:
 *    $myLinks = new LinkHistoryDB();
 *    echo $myLinks['20110826_161819']['action'];
 *    foreach ($myLinks as $link)
 *       echo $link['linkdate'].' edit at date '.$link['editdate'].'; action:'.$link['action'];
 *
 * Available keys:
 *  - linkdate: date of the creation of this entry, in the form YYYYMMDD_HHMMSS
 *              (e.g.'20110914_192317')
 *  - date:  date of the edition of this entry, in the form YYYYMMDD_HHMMSS
 *              (e.g.'20110914_192317')
 *  - action:  nature of edition (e.g. UPDATED, DELETED)  
 *
 * Implements 3 interfaces:
 *  - ArrayAccess: behaves like an associative array;
 *  - Countable:   there is a count() method;
 *  - Iterator:    usable in foreach () loops.
 */
class LinkHistoryDB implements Iterator, Countable, ArrayAccess
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

    // List of linkdate keys (for the Iterator interface implementation)
    private $_keys;

    // Position in the $this->_keys array (for the Iterator interface)
    private $_position;

    // Is the user logged in?
    private $_loggedIn;


    /**
     * Creates a new LinkHistoryDB
     *
     * Checks if the datastore exists
     *
     * @param string  $datastore       datastore file path.
     * @param boolean $isLoggedIn      is the user logged in?
     */
    function __construct($datastore, $isLoggedIn)
    {
        $this->_datastore = $datastore;
        $this->_loggedIn = $isLoggedIn;
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
            die('You are not logged in.');
        }
        if (empty($value['linkdate'])) {
            die('Internal Error: A link history should always have a linkdate.');
        }
        if (empty($value['editdate'])) {
            die('Internal Error: A link history should always have a date.');
        }
        if (empty($offset)) {
            die('You must specify a key.');
        }
        $this->_links[$offset] = $value;
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
    }

    /**
     * Reads database from disk to memory
     */
    private function _readDB()
    {

        // Public links are hidden and user not logged in => nothing to show
        if (!$this->_loggedIn) {
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
     * Filter links according to search parameters.
     *
     * @param array  $request type of history (e.g. UPDATED, DELETED)
     *
     * @return array filtered links, all links if no suitable filter was provided.
     */
    public function filterSearch($request)
    {
        if (empty($request)) {
            $out = array();
            foreach ($this->_links as $value) {
                $out[$value['editdate']] = $value;
            }
            krsort($out);
            return $out;
        } else {
            $out = array();
            foreach ($this->_links as $value) {
                if ($value['type']===$request) {
                    $out[$value['editdate']] = $value;
                }
            }
            krsort($out);
            return $out;
        }
    }
    

}

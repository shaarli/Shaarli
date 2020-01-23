<?php

namespace Shaarli\Bookmark;

use Shaarli\Bookmark\Exception\EmptyDataStoreException;
use Shaarli\Bookmark\Exception\NotWritableDataStoreException;
use Shaarli\Config\ConfigManager;

/**
 * Class BookmarkIO
 *
 * This class performs read/write operation to the file data store.
 * Used by BookmarkFileService.
 *
 * @package Shaarli\Bookmark
 */
class BookmarkIO
{
    /**
     * @var string Datastore file path
     */
    protected $datastore;

    /**
     * @var ConfigManager instance
     */
    protected $conf;

    /**
     * string Datastore PHP prefix
     */
    protected static $phpPrefix = '<?php /* ';

    /**
     * string Datastore PHP suffix
     */
    protected static $phpSuffix = ' */ ?>';

    /**
     * LinksIO constructor.
     *
     * @param ConfigManager $conf instance
     */
    public function __construct($conf)
    {
        $this->conf = $conf;
        $this->datastore = $conf->get('resource.datastore');
    }

    /**
     * Reads database from disk to memory
     *
     * @return BookmarkArray instance
     *
     * @throws NotWritableDataStoreException Data couldn't be loaded
     * @throws EmptyDataStoreException       Datastore doesn't exist
     */
    public function read()
    {
        if (! file_exists($this->datastore)) {
            throw new EmptyDataStoreException();
        }

        if (!is_writable($this->datastore)) {
            throw new NotWritableDataStoreException($this->datastore);
        }

        // Note that gzinflate is faster than gzuncompress.
        // See: http://www.php.net/manual/en/function.gzdeflate.php#96439
        $links = unserialize(gzinflate(base64_decode(
            substr(file_get_contents($this->datastore),
                strlen(self::$phpPrefix), -strlen(self::$phpSuffix)))));

        if (empty($links)) {
            if (filesize($this->datastore) > 100) {
                throw new NotWritableDataStoreException($this->datastore);
            }
            throw new EmptyDataStoreException();
        }

        return $links;
    }

    /**
     * Saves the database from memory to disk
     *
     * @param BookmarkArray $links instance.
     *
     * @throws NotWritableDataStoreException the datastore is not writable
     */
    public function write($links)
    {
        if (is_file($this->datastore) && !is_writeable($this->datastore)) {
            // The datastore exists but is not writeable
            throw new NotWritableDataStoreException($this->datastore);
        } else if (!is_file($this->datastore) && !is_writeable(dirname($this->datastore))) {
            // The datastore does not exist and its parent directory is not writeable
            throw new NotWritableDataStoreException(dirname($this->datastore));
        }

        file_put_contents(
            $this->datastore,
            self::$phpPrefix.base64_encode(gzdeflate(serialize($links))).self::$phpSuffix
        );
    }
}

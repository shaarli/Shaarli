<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

use malkusch\lock\exception\LockAcquireException;
use malkusch\lock\mutex\Mutex;
use malkusch\lock\mutex\NoMutex;
use Shaarli\Bookmark\Exception\DatastoreNotInitializedException;
use Shaarli\Bookmark\Exception\EmptyDataStoreException;
use Shaarli\Bookmark\Exception\InvalidWritableDataException;
use Shaarli\Bookmark\Exception\NotEnoughSpaceException;
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


    /** @var Mutex */
    protected $mutex;

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
    public function __construct(ConfigManager $conf, Mutex $mutex = null)
    {
        if ($mutex === null) {
            // This should only happen with legacy classes
            $mutex = new NoMutex();
        }
        $this->conf = $conf;
        $this->datastore = $conf->get('resource.datastore');
        $this->mutex = $mutex;
    }

    /**
     * Reads database from disk to memory
     *
     * @return Bookmark[]
     *
     * @throws NotWritableDataStoreException    Data couldn't be loaded
     * @throws EmptyDataStoreException          Datastore file exists but does not contain any bookmark
     * @throws DatastoreNotInitializedException File does not exists
     */
    public function read()
    {
        if (! file_exists($this->datastore)) {
            throw new DatastoreNotInitializedException();
        }

        if (!is_writable($this->datastore)) {
            throw new NotWritableDataStoreException($this->datastore);
        }

        $content = null;
        $this->synchronized(function () use (&$content) {
            $content = file_get_contents($this->datastore);
        });

        // Note that gzinflate is faster than gzuncompress.
        // See: http://www.php.net/manual/en/function.gzdeflate.php#96439
        $links = unserialize(gzinflate(base64_decode(
            substr($content, strlen(self::$phpPrefix), -strlen(self::$phpSuffix))
        )));

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
     * @param Bookmark[] $links
     *
     * @throws NotWritableDataStoreException the datastore is not writable
     * @throws InvalidWritableDataException
     */
    public function write($links)
    {
        if (is_file($this->datastore) && !is_writeable($this->datastore)) {
            // The datastore exists but is not writeable
            throw new NotWritableDataStoreException($this->datastore);
        } elseif (!is_file($this->datastore) && !is_writeable(dirname($this->datastore))) {
            // The datastore does not exist and its parent directory is not writeable
            throw new NotWritableDataStoreException(dirname($this->datastore));
        }

        $data = base64_encode(gzdeflate(serialize($links)));

        if (empty($data)) {
            throw new InvalidWritableDataException();
        }

        $data = self::$phpPrefix . $data . self::$phpSuffix;

        $this->synchronized(function () use ($data) {
            if (!$this->checkDiskSpace($data)) {
                throw new NotEnoughSpaceException();
            }

            file_put_contents(
                $this->datastore,
                $data
            );
        });
    }

    /**
     * Wrapper applying mutex to provided function.
     * If the lock can't be acquired (e.g. some shared hosting provider), we execute the function without mutex.
     *
     * @see https://github.com/shaarli/Shaarli/issues/1650
     *
     * @param callable $function
     */
    protected function synchronized(callable $function): void
    {
        try {
            $this->mutex->synchronized($function);
        } catch (LockAcquireException $exception) {
            $function();
        }
    }

    /**
     * Make sure that there is enough disk space available to save the current data store.
     * We add an arbitrary margin of 500kB.
     *
     * @param string $data to be saved
     *
     * @return bool True if data can safely be saved
     */
    public function checkDiskSpace(string $data): bool
    {
        return disk_free_space(dirname($this->datastore)) > (strlen($data) + 1024 * 500);
    }
}

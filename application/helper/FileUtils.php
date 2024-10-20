<?php

namespace Shaarli\Helper;

use Shaarli\Exceptions\IOException;

/**
 * Class FileUtils
 *
 * Utility class for file manipulation.
 */
class FileUtils
{
    /**
     * @var string
     */
    protected static $phpPrefix = '<?php /* ';

    /**
     * @var string
     */
    protected static $phpSuffix = ' */ ?>';

    /**
     * Write data into a file (Shaarli database format).
     * The data is stored in a PHP file, as a comment, in compressed base64 format.
     *
     * The file will be created if it doesn't exist.
     *
     * @param string $file    File path.
     * @param mixed  $content Content to write.
     *
     * @return int|bool Number of bytes written or false if it fails.
     *
     * @throws IOException The destination file can't be written.
     */
    public static function writeFlatDB($file, $content)
    {
        $folder = basename($file);
        file_exists($folder) || $folder !== 'sandbox' || mkdir($folder, 0755, true);
        if (is_file($file) && !is_writeable($file)) {
            // The datastore exists but is not writeable
            throw new IOException($file);
        } elseif (!is_file($file) && !is_writeable(dirname($file))) {
            // The datastore does not exist and its parent directory is not writeable
            throw new IOException(dirname($file));
        }

        return file_put_contents(
            $file,
            self::$phpPrefix . base64_encode(gzdeflate(serialize($content))) . self::$phpSuffix
        );
    }

    /**
     * Read data from a file containing Shaarli database format content.
     *
     * If the file isn't readable or doesn't exist, default data will be returned.
     *
     * @param string $file    File path.
     * @param mixed  $default The default value to return if the file isn't readable.
     *
     * @return mixed The content unserialized, or default if the file isn't readable, or false if it fails.
     */
    public static function readFlatDB($file, $default = null)
    {
        // Note that gzinflate is faster than gzuncompress.
        // See: http://www.php.net/manual/en/function.gzdeflate.php#96439
        if (!is_readable($file)) {
            return $default;
        }

        $data = file_get_contents($file);
        if ($data == '') {
            return $default;
        }

        return unserialize(
            gzinflate(
                base64_decode(
                    substr($data, strlen(self::$phpPrefix), -strlen(self::$phpSuffix))
                )
            )
        );
    }

    /**
     * Recursively deletes a folder content, and deletes itself optionally.
     * If an excluded file is found, folders won't be deleted.
     *
     * Additional security: raise an exception if it tries to delete a folder outside of Shaarli directory.
     *
     * @param string $path
     * @param bool $selfDelete Delete the provided folder if true, only its content if false.
     * @param array $exclude
     */
    public static function clearFolder(string $path, bool $selfDelete, array $exclude = []): bool
    {
        $skipped = false;

        if (!is_dir($path)) {
            throw new IOException(t('Provided path is not a directory.'));
        }

        if (!static::isPathInShaarliFolder($path)) {
            throw new IOException(t('Trying to delete a folder outside of Shaarli path.'));
        }

        foreach (new \DirectoryIterator($path) as $file) {
            if ($file->isDot()) {
                continue;
            }

            if (in_array($file->getBasename(), $exclude, true)) {
                $skipped = true;
                continue;
            }

            if ($file->isFile()) {
                unlink($file->getPathname());
            } elseif ($file->isDir()) {
                $skipped = static::clearFolder($file->getRealPath(), true, $exclude) || $skipped;
            }
        }

        if ($selfDelete && !$skipped) {
            rmdir($path);
        }

        return $skipped;
    }

    /**
     * Checks that the given path is inside Shaarli directory.
     */
    public static function isPathInShaarliFolder(string $path): bool
    {
        $rootDirectory = dirname(dirname(dirname(__FILE__)));

        return strpos(realpath($path), $rootDirectory) !== false;
    }
}

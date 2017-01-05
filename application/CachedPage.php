<?php
/**
 * Simple cache system, mainly for the RSS/ATOM feeds
 */
class CachedPage
{
    // Directory containing page caches
    private $cacheDir;

    // Should this URL be cached (boolean)?
    private $shouldBeCached;

    // Name of the cache file for this URL
    private $filename;

    /**
     * Creates a new CachedPage
     *
     * @param string $cacheDir       page cache directory
     * @param string $url            page URL
     * @param bool   $shouldBeCached whether this page needs to be cached
     */
    public function __construct($cacheDir, $url, $shouldBeCached)
    {
        // TODO: check write access to the cache directory
        $this->cacheDir = $cacheDir;
        $this->filename = $this->cacheDir.'/'.sha1($url).'.cache';
        $this->shouldBeCached = $shouldBeCached;
    }

    /**
     * Returns the cached version of a page, if it exists and should be cached
     *
     * @return string a cached version of the page if it exists, null otherwise
     */
    public function cachedVersion()
    {
        if (!$this->shouldBeCached) {
            return null;
        }
        if (is_file($this->filename)) {
            return file_get_contents($this->filename);
        }
        return null;
    }

    /**
     * Puts a page in the cache
     *
     * @param string $pageContent XML content to cache
     */
    public function cache($pageContent)
    {
        if (!$this->shouldBeCached) {
            return;
        }
        file_put_contents($this->filename, $pageContent);
    }
}

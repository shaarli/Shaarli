<?php

namespace Shaarli\Render;

use Shaarli\Feed\CachedPage;

/**
 * Cache utilities
 */
class PageCacheManager
{
    /** @var string Cache directory */
    protected $pageCacheDir;

    /** @var bool */
    protected $isLoggedIn;

    public function __construct(string $pageCacheDir, bool $isLoggedIn)
    {
        $this->pageCacheDir = $pageCacheDir;
        $this->isLoggedIn = $isLoggedIn;
    }

    /**
     * Purges all cached pages
     *
     * @return string|null an error string if the directory is missing
     */
    public function purgeCachedPages(): ?string
    {
        if (!is_dir($this->pageCacheDir)) {
            $error = sprintf(t('Cannot purge %s: no directory'), $this->pageCacheDir);
            error_log($error);

            return $error;
        }

        array_map('unlink', glob($this->pageCacheDir . '/*.cache'));

        return null;
    }

    /**
     * Invalidates caches when the database is changed or the user logs out.
     */
    public function invalidateCaches(): void
    {
        // Purge page cache shared by sessions.
        $this->purgeCachedPages();
    }

    public function getCachePage(string $pageUrl): CachedPage
    {
        return new CachedPage(
            $this->pageCacheDir,
            $pageUrl,
            false === $this->isLoggedIn
        );
    }
}

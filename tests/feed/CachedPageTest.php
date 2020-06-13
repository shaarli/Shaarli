<?php
/**
 * PageCache tests
 */
namespace Shaarli\Feed;

/**
 * Unitary tests for cached pages
 */
class CachedPageTest extends \PHPUnit\Framework\TestCase
{
    // test cache directory
    protected static $testCacheDir = 'sandbox/pagecache';
    protected static $url = 'http://shaar.li/feed/atom';
    protected static $filename;

    /**
     * Create the cache directory if needed
     */
    public static function setUpBeforeClass()
    {
        if (!is_dir(self::$testCacheDir)) {
            mkdir(self::$testCacheDir);
        }
        self::$filename = self::$testCacheDir . '/' . sha1(self::$url) . '.cache';
    }

    /**
     * Reset the page cache
     */
    public function setUp()
    {
        if (file_exists(self::$filename)) {
            unlink(self::$filename);
        }
    }

    /**
     * Create a new cached page
     */
    public function testConstruct()
    {
        new CachedPage(self::$testCacheDir, '', true);
        new CachedPage(self::$testCacheDir, '', false);
        new CachedPage(self::$testCacheDir, 'http://shaar.li/feed/rss', true);
        new CachedPage(self::$testCacheDir, 'http://shaar.li/feed/atom', false);
        $this->addToAssertionCount(1);
    }

    /**
     * Cache a page's content
     */
    public function testCache()
    {
        $page = new CachedPage(self::$testCacheDir, self::$url, true);

        $this->assertFileNotExists(self::$filename);
        $page->cache('<p>Some content</p>');
        $this->assertFileExists(self::$filename);
        $this->assertEquals(
            '<p>Some content</p>',
            file_get_contents(self::$filename)
        );
    }

    /**
     * "Cache" a page's content - the page is not to be cached
     */
    public function testShouldNotCache()
    {
        $page = new CachedPage(self::$testCacheDir, self::$url, false);

        $this->assertFileNotExists(self::$filename);
        $page->cache('<p>Some content</p>');
        $this->assertFileNotExists(self::$filename);
    }

    /**
     * Return a page's cached content
     */
    public function testCachedVersion()
    {
        $page = new CachedPage(self::$testCacheDir, self::$url, true);

        $this->assertFileNotExists(self::$filename);
        $page->cache('<p>Some content</p>');
        $this->assertFileExists(self::$filename);
        $this->assertEquals(
            '<p>Some content</p>',
            $page->cachedVersion()
        );
    }

    /**
     * Return a page's cached content - the file does not exist
     */
    public function testCachedVersionNoFile()
    {
        $page = new CachedPage(self::$testCacheDir, self::$url, true);

        $this->assertFileNotExists(self::$filename);
        $this->assertEquals(
            null,
            $page->cachedVersion()
        );
    }

    /**
     * Return a page's cached content - the page is not to be cached
     */
    public function testNoCachedVersion()
    {
        $page = new CachedPage(self::$testCacheDir, self::$url, false);

        $this->assertFileNotExists(self::$filename);
        $this->assertEquals(
            null,
            $page->cachedVersion()
        );
    }
}

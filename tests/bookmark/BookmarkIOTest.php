<?php

namespace Shaarli\Bookmark;

use malkusch\lock\mutex\NoMutex;
use Shaarli\Config\ConfigManager;
use Shaarli\TestCase;

/**
 * Class BookmarkIOTest
 * @package Shaarli\Bookmark
 */
class BookmarkIOTest extends TestCase
{
    /** @var string Path of test data store */
    protected static $testDatastore = 'sandbox/datastore.php';

    /** @var string Path of the temporary file written before replacing the data store */
    protected static $testTmpDatastore = 'sandbox/datastore.tmp.php';

    /** @var string Path of test config file */
    protected static $testConf = 'sandbox/config';

    /** @var BookmarkIO instance */
    protected $bookmarkIO;

    /**
     * Initialize a BookmarkIO on an empty sandbox data store
     */
    public function setUp(): void
    {
        foreach ([self::$testDatastore, self::$testTmpDatastore] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        copy('tests/utils/config/configJson.json.php', self::$testConf . '.json.php');
        $conf = new ConfigManager(self::$testConf);
        $conf->set('resource.datastore', self::$testDatastore);
        $this->bookmarkIO = new BookmarkIO($conf, new NoMutex());
    }

    /**
     * Test that written bookmarks can be read back and no temporary file is left behind.
     */
    public function testWriteThenRead(): void
    {
        $this->bookmarkIO->write($this->buildBookmarks('first'));

        $this->assertFileNotExists(self::$testTmpDatastore);
        $this->assertSame('first', $this->bookmarkIO->read()[1]->getTitle());
    }

    /**
     * Test that the data store is replaced in one step instead of being truncated and rewritten:
     * a reader which opened the previous data store still gets its full content.
     */
    public function testWriteReplacesDatastoreAtomically(): void
    {
        $this->bookmarkIO->write($this->buildBookmarks('first'));
        $previousContent = file_get_contents(self::$testDatastore);
        $handle = fopen(self::$testDatastore, 'r');

        $this->bookmarkIO->write($this->buildBookmarks('second'));

        $this->assertSame($previousContent, stream_get_contents($handle));
        fclose($handle);
        $this->assertFileNotExists(self::$testTmpDatastore);
        $this->assertSame('second', $this->bookmarkIO->read()[1]->getTitle());
    }

    /**
     * @param string $title Title of the only bookmark
     *
     * @return BookmarkArray containing a single bookmark
     */
    protected function buildBookmarks(string $title): BookmarkArray
    {
        $bookmarks = new BookmarkArray();
        $bookmark = (new Bookmark())->setId(1)->setUrl('https://example.com/')->setTitle($title);
        $bookmarks[$bookmark->getId()] = $bookmark;

        return $bookmarks;
    }
}

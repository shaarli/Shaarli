<?php

namespace Shaarli\Netscape;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\History;

require_once 'tests/utils/ReferenceLinkDB.php';

/**
 * Netscape bookmark export
 */
class BookmarkExportTest extends TestCase
{
    /**
     * @var string datastore to test write operations
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var ConfigManager instance.
     */
    protected static $conf;

    /**
     * @var \ReferenceLinkDB instance.
     */
    protected static $refDb = null;

    /**
     * @var BookmarkFileService private instance.
     */
    protected static $bookmarkService = null;

    /**
     * @var BookmarkFormatter instance
     */
    protected static $formatter;

    /**
     * @var History instance
     */
    protected static $history;

    /**
     * @var NetscapeBookmarkUtils
     */
    protected $netscapeBookmarkUtils;

    /**
     * Instantiate reference data
     */
    public static function setUpBeforeClass()
    {
        static::$conf = new ConfigManager('tests/utils/config/configJson');
        static::$conf->set('resource.datastore', static::$testDatastore);
        static::$refDb = new \ReferenceLinkDB();
        static::$refDb->write(static::$testDatastore);
        static::$history = new History('sandbox/history.php');
        static::$bookmarkService = new BookmarkFileService(static::$conf, static::$history, true);
        $factory = new FormatterFactory(static::$conf, true);
        static::$formatter = $factory->getFormatter('raw');
    }

    public function setUp(): void
    {
        $this->netscapeBookmarkUtils = new NetscapeBookmarkUtils(
            static::$bookmarkService,
            static::$conf,
            static::$history
        );
    }

    /**
     * Attempt to export an invalid link selection
     * @expectedException              Exception
     * @expectedExceptionMessageRegExp /Invalid export selection/
     */
    public function testFilterAndFormatInvalid()
    {
        $this->netscapeBookmarkUtils->filterAndFormat(
            self::$formatter,
            'derp',
            false,
            ''
        );
    }

    /**
     * Prepare all bookmarks for export
     */
    public function testFilterAndFormatAll()
    {
        $links = $this->netscapeBookmarkUtils->filterAndFormat(
            self::$formatter,
            'all',
            false,
            ''
        );
        $this->assertEquals(self::$refDb->countLinks(), sizeof($links));
        foreach ($links as $link) {
            $date = $link['created'];
            $this->assertEquals(
                $date->getTimestamp(),
                $link['timestamp']
            );
            $this->assertEquals(
                str_replace(' ', ',', $link['tags']),
                $link['taglist']
            );
        }
    }

    /**
     * Prepare private bookmarks for export
     */
    public function testFilterAndFormatPrivate()
    {
        $links = $this->netscapeBookmarkUtils->filterAndFormat(
            self::$formatter,
            'private',
            false,
            ''
        );
        $this->assertEquals(self::$refDb->countPrivateLinks(), sizeof($links));
        foreach ($links as $link) {
            $date = $link['created'];
            $this->assertEquals(
                $date->getTimestamp(),
                $link['timestamp']
            );
            $this->assertEquals(
                str_replace(' ', ',', $link['tags']),
                $link['taglist']
            );
        }
    }

    /**
     * Prepare public bookmarks for export
     */
    public function testFilterAndFormatPublic()
    {
        $links = $this->netscapeBookmarkUtils->filterAndFormat(
            self::$formatter,
            'public',
            false,
            ''
        );
        $this->assertEquals(self::$refDb->countPublicLinks(), sizeof($links));
        foreach ($links as $link) {
            $date = $link['created'];
            $this->assertEquals(
                $date->getTimestamp(),
                $link['timestamp']
            );
            $this->assertEquals(
                str_replace(' ', ',', $link['tags']),
                $link['taglist']
            );
        }
    }

    /**
     * Do not prepend notes with the Shaarli index's URL
     */
    public function testFilterAndFormatDoNotPrependNoteUrl()
    {
        $links = $this->netscapeBookmarkUtils->filterAndFormat(
            self::$formatter,
            'public',
            false,
            ''
        );
        $this->assertEquals(
            '?WDWyig',
            $links[2]['url']
        );
    }

    /**
     * Prepend notes with the Shaarli index's URL
     */
    public function testFilterAndFormatPrependNoteUrl()
    {
        $indexUrl = 'http://localhost:7469/shaarli/';
        $links = $this->netscapeBookmarkUtils->filterAndFormat(
            self::$formatter,
            'public',
            true,
            $indexUrl
        );
        $this->assertEquals(
            $indexUrl . '?WDWyig',
            $links[2]['url']
        );
    }
}

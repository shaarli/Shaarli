<?php

namespace Shaarli\Feed;

use DateTime;
use ReferenceLinkDB;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\LinkDB;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\History;

/**
 * FeedBuilderTest class.
 *
 * Unit tests for FeedBuilder.
 */
class FeedBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string locale Basque (Spain).
     */
    public static $LOCALE = 'eu_ES';

    /**
     * @var string language in RSS format.
     */
    public static $RSS_LANGUAGE = 'eu-es';

    /**
     * @var string language in ATOM format.
     */
    public static $ATOM_LANGUAGUE = 'eu';

    protected static $testDatastore = 'sandbox/datastore.php';

    public static $bookmarkService;

    public static $formatter;

    public static $serverInfo;

    /**
     * Called before every test method.
     */
    public static function setUpBeforeClass()
    {
        $conf = new ConfigManager('tests/utils/config/configJson');
        $conf->set('resource.datastore', self::$testDatastore);
        $refLinkDB = new \ReferenceLinkDB();
        $refLinkDB->write(self::$testDatastore);
        $history = new History('sandbox/history.php');
        $factory = new FormatterFactory($conf, true);
        self::$formatter = $factory->getFormatter();
        self::$bookmarkService = new BookmarkFileService($conf, $history, true);

        self::$serverInfo = array(
            'HTTPS' => 'Off',
            'SERVER_NAME' => 'host.tld',
            'SERVER_PORT' => '80',
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/index.php?do=feed',
        );
    }

    /**
     * Test GetTypeLanguage().
     */
    public function testGetTypeLanguage()
    {
        $feedBuilder = new FeedBuilder(null, self::$formatter, FeedBuilder::$FEED_ATOM, null, null, false);
        $feedBuilder->setLocale(self::$LOCALE);
        $this->assertEquals(self::$ATOM_LANGUAGUE, $feedBuilder->getTypeLanguage());
        $feedBuilder = new FeedBuilder(null, self::$formatter, FeedBuilder::$FEED_RSS, null, null, false);
        $feedBuilder->setLocale(self::$LOCALE);
        $this->assertEquals(self::$RSS_LANGUAGE, $feedBuilder->getTypeLanguage());
        $feedBuilder = new FeedBuilder(null, self::$formatter, FeedBuilder::$FEED_ATOM, null, null, false);
        $this->assertEquals('en', $feedBuilder->getTypeLanguage());
        $feedBuilder = new FeedBuilder(null, self::$formatter, FeedBuilder::$FEED_RSS, null, null, false);
        $this->assertEquals('en-en', $feedBuilder->getTypeLanguage());
    }

    /**
     * Test buildData with RSS feed.
     */
    public function testRSSBuildData()
    {
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_RSS,
            self::$serverInfo,
            null,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $data = $feedBuilder->buildData();
        // Test headers (RSS)
        $this->assertEquals(self::$RSS_LANGUAGE, $data['language']);
        $this->assertRegExp('/Wed, 03 Aug 2016 09:30:33 \+\d{4}/', $data['last_update']);
        $this->assertEquals(true, $data['show_dates']);
        $this->assertEquals('http://host.tld/index.php?do=feed', $data['self_link']);
        $this->assertEquals('http://host.tld/', $data['index_url']);
        $this->assertFalse($data['usepermalinks']);
        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, count($data['links']));

        // Test first not pinned link (note link)
        $link = $data['links'][array_keys($data['links'])[2]];
        $this->assertEquals(41, $link['id']);
        $this->assertEquals(DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651'), $link['created']);
        $this->assertEquals('http://host.tld/?WDWyig', $link['guid']);
        $this->assertEquals('http://host.tld/?WDWyig', $link['url']);
        $this->assertRegExp('/Tue, 10 Mar 2015 11:46:51 \+\d{4}/', $link['pub_iso_date']);
        $pub = DateTime::createFromFormat(DateTime::RSS, $link['pub_iso_date']);
        $up = DateTime::createFromFormat(DateTime::ATOM, $link['up_iso_date']);
        $this->assertEquals($pub, $up);
        $this->assertContains('Stallman has a beard', $link['description']);
        $this->assertContains('Permalink', $link['description']);
        $this->assertContains('http://host.tld/?WDWyig', $link['description']);
        $this->assertEquals(1, count($link['taglist']));
        $this->assertEquals('sTuff', $link['taglist'][0]);

        // Test URL with external link.
        $this->assertEquals('https://static.fsf.org/nosvn/faif-2.0.pdf', $data['links'][8]['url']);

        // Test multitags.
        $this->assertEquals(5, count($data['links'][6]['taglist']));
        $this->assertEquals('css', $data['links'][6]['taglist'][0]);

        // Test update date
        $this->assertRegExp('/2016-08-03T09:30:33\+\d{2}:\d{2}/', $data['links'][8]['up_iso_date']);
    }

    /**
     * Test buildData with ATOM feed (test only specific to ATOM).
     */
    public function testAtomBuildData()
    {
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            self::$serverInfo,
            null,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $data = $feedBuilder->buildData();
        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, count($data['links']));
        $this->assertRegExp('/2016-08-03T09:30:33\+\d{2}:\d{2}/', $data['last_update']);
        $link = $data['links'][array_keys($data['links'])[2]];
        $this->assertRegExp('/2015-03-10T11:46:51\+\d{2}:\d{2}/', $link['pub_iso_date']);
        $this->assertRegExp('/2016-08-03T09:30:33\+\d{2}:\d{2}/', $data['links'][8]['up_iso_date']);
    }

    /**
     * Test buildData with search criteria.
     */
    public function testBuildDataFiltered()
    {
        $criteria = array(
            'searchtags' => 'stuff',
            'searchterm' => 'beard',
        );
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            self::$serverInfo,
            $criteria,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $data = $feedBuilder->buildData();
        $this->assertEquals(1, count($data['links']));
        $link = array_shift($data['links']);
        $this->assertEquals(41, $link['id']);
        $this->assertEquals(DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651'), $link['created']);
    }

    /**
     * Test buildData with nb limit.
     */
    public function testBuildDataCount()
    {
        $criteria = array(
            'nb' => '3',
        );
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            self::$serverInfo,
            $criteria,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $data = $feedBuilder->buildData();
        $this->assertEquals(3, count($data['links']));
        $link = $data['links'][array_keys($data['links'])[2]];
        $this->assertEquals(41, $link['id']);
        $this->assertEquals(DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651'), $link['created']);
    }

    /**
     * Test buildData with permalinks on.
     */
    public function testBuildDataPermalinks()
    {
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            self::$serverInfo,
            null,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $feedBuilder->setUsePermalinks(true);
        $data = $feedBuilder->buildData();
        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, count($data['links']));
        $this->assertTrue($data['usepermalinks']);
        // First link is a permalink
        $link = $data['links'][array_keys($data['links'])[2]];
        $this->assertEquals(41, $link['id']);
        $this->assertEquals(DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114651'), $link['created']);
        $this->assertEquals('http://host.tld/?WDWyig', $link['guid']);
        $this->assertEquals('http://host.tld/?WDWyig', $link['url']);
        $this->assertContains('Direct link', $link['description']);
        $this->assertContains('http://host.tld/?WDWyig', $link['description']);
        // Second link is a direct link
        $link = $data['links'][array_keys($data['links'])[3]];
        $this->assertEquals(8, $link['id']);
        $this->assertEquals(DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20150310_114633'), $link['created']);
        $this->assertEquals('http://host.tld/?RttfEw', $link['guid']);
        $this->assertEquals('https://static.fsf.org/nosvn/faif-2.0.pdf', $link['url']);
        $this->assertContains('Direct link', $link['description']);
        $this->assertContains('https://static.fsf.org/nosvn/faif-2.0.pdf', $link['description']);
    }

    /**
     * Test buildData with hide dates settings.
     */
    public function testBuildDataHideDates()
    {
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            self::$serverInfo,
            null,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $feedBuilder->setHideDates(true);
        $data = $feedBuilder->buildData();
        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, count($data['links']));
        $this->assertFalse($data['show_dates']);

        // Show dates while logged in
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            self::$serverInfo,
            null,
            true
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $feedBuilder->setHideDates(true);
        $data = $feedBuilder->buildData();
        $this->assertEquals(ReferenceLinkDB::$NB_LINKS_TOTAL, count($data['links']));
        $this->assertTrue($data['show_dates']);
    }

    /**
     * Test buildData when Shaarli is served from a subdirectory
     */
    public function testBuildDataServerSubdir()
    {
        $serverInfo = array(
            'HTTPS' => 'Off',
            'SERVER_NAME' => 'host.tld',
            'SERVER_PORT' => '8080',
            'SCRIPT_NAME' => '/~user/shaarli/index.php',
            'REQUEST_URI' => '/~user/shaarli/index.php?do=feed',
        );
        $feedBuilder = new FeedBuilder(
            self::$bookmarkService,
            self::$formatter,
            FeedBuilder::$FEED_ATOM,
            $serverInfo,
            null,
            false
        );
        $feedBuilder->setLocale(self::$LOCALE);
        $data = $feedBuilder->buildData();

        $this->assertEquals(
            'http://host.tld:8080/~user/shaarli/index.php?do=feed',
            $data['self_link']
        );

        // Test first link (note link)
        $link = $data['links'][array_keys($data['links'])[2]];
        $this->assertEquals('http://host.tld:8080/~user/shaarli/?WDWyig', $link['guid']);
        $this->assertEquals('http://host.tld:8080/~user/shaarli/?WDWyig', $link['url']);
        $this->assertContains('http://host.tld:8080/~user/shaarli/./add-tag/hashtag', $link['description']);
    }
}

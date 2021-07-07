<?php

/**
 * Link datastore tests
 */

namespace Shaarli\Bookmark;

use DateTime;
use malkusch\lock\mutex\NoMutex;
use ReflectionClass;
use Shaarli;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeBookmarkService;
use Shaarli\Tests\Utils\ReferenceLinkDB;

/**
 * Unitary tests for LegacyLinkDBTest
 */
class BookmarkFileServiceTest extends TestCase
{
    // datastore to test write operations
    protected static $testDatastore = 'sandbox/datastore.php';

    protected static $testConf = 'sandbox/config';

    protected static $testUpdates = 'sandbox/updates.txt';

    /**
     * @var ConfigManager instance.
     */
    protected $conf;

    /**
     * @var History instance.
     */
    protected $history;

    /**
     * @var ReferenceLinkDB instance.
     */
    protected $refDB = null;

    /**
     * @var BookmarkFileService public LinkDB instance.
     */
    protected $publicLinkDB = null;

    /**
     * @var BookmarkFileService private LinkDB instance.
     */
    protected $privateLinkDB = null;

    /** @var NoMutex */
    protected $mutex;

    /** @var PluginManager */
    protected $pluginManager;

    /**
     * Instantiates public and private LinkDBs with test data
     *
     * The reference datastore contains public and private bookmarks that
     * will be used to test LinkDB's methods:
     *  - access filtering (public/private),
     *  - link searches:
     *    - by day,
     *    - by tag,
     *    - by text,
     *  - etc.
     *
     * Resets test data for each test
     */
    protected function setUp(): void
    {
        $this->mutex = new NoMutex();

        if (file_exists(self::$testDatastore)) {
            unlink(self::$testDatastore);
        }

        if (file_exists(self::$testConf . '.json.php')) {
            unlink(self::$testConf . '.json.php');
        }

        if (file_exists(self::$testUpdates)) {
            unlink(self::$testUpdates);
        }

        copy('tests/utils/config/configJson.json.php', self::$testConf . '.json.php');
        $this->conf = new ConfigManager(self::$testConf);
        $this->conf->set('resource.datastore', self::$testDatastore);
        $this->conf->set('resource.updates', self::$testUpdates);
        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);
        $this->history = new History('sandbox/history.php');
        $this->pluginManager = new PluginManager($this->conf);
        $this->publicLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            false
        );
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );
    }

    /**
     * Test migrate() method with a legacy datastore.
     */
    public function testDatabaseMigration()
    {
        if (!defined('SHAARLI_VERSION')) {
            define('SHAARLI_VERSION', 'dev');
        }

        $this->refDB = new ReferenceLinkDB(true);
        $this->refDB->write(self::$testDatastore);
        $db = self::getMethod('migrate');
        $db->invokeArgs($this->privateLinkDB, []);

        $db = new FakeBookmarkService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );
        $this->assertInstanceOf(BookmarkArray::class, $db->getBookmarks());
        $this->assertEquals($this->refDB->countLinks(), $db->count());
    }

    /**
     * Test get() method for a defined and saved bookmark
     */
    public function testGetDefinedSaved()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
    }

    /**
     * Test get() method for a defined and not saved bookmark
     */
    public function testGetDefinedNotSaved()
    {
        $bookmark = new Bookmark();
        $this->privateLinkDB->add($bookmark);
        $createdBookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $createdBookmark->getId());
        $this->assertEmpty($createdBookmark->getDescription());
    }

    /**
     * Test get() method for an undefined bookmark
     */
    public function testGetUndefined()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        $this->privateLinkDB->get(666);
    }

    /**
     * Test add() method for a bookmark fully built
     */
    public function testAddFull()
    {
        $bookmark = new Bookmark();
        $bookmark->setUrl($url = 'https://domain.tld/index.php');
        $bookmark->setShortUrl('abc');
        $bookmark->setTitle($title = 'This a brand new bookmark');
        $bookmark->setDescription($desc = 'It should be created and written');
        $bookmark->setTags($tags = ['tag1', 'tagssss']);
        $bookmark->setThumbnail($thumb = 'http://thumb.tld/dle.png');
        $bookmark->setPrivate(true);
        $bookmark->setSticky(true);
        $bookmark->setCreated($created = DateTime::createFromFormat('Ymd_His', '20190518_140354'));
        $bookmark->setUpdated($updated = DateTime::createFromFormat('Ymd_His', '20190518_150354'));

        $this->privateLinkDB->add($bookmark);
        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());
        $this->assertEquals($url, $bookmark->getUrl());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($title, $bookmark->getTitle());
        $this->assertEquals($desc, $bookmark->getDescription());
        $this->assertEquals($tags, $bookmark->getTags());
        $this->assertEquals($thumb, $bookmark->getThumbnail());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertTrue($bookmark->isSticky());
        $this->assertEquals($created, $bookmark->getCreated());
        $this->assertEquals($updated, $bookmark->getUpdated());

        // reload from file
        $this->privateLinkDB = new FakeBookmarkService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());
        $this->assertEquals($url, $bookmark->getUrl());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($title, $bookmark->getTitle());
        $this->assertEquals($desc, $bookmark->getDescription());
        $this->assertEquals($tags, $bookmark->getTags());
        $this->assertEquals($thumb, $bookmark->getThumbnail());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertTrue($bookmark->isSticky());
        $this->assertEquals($created, $bookmark->getCreated());
        $this->assertEquals($updated, $bookmark->getUpdated());
    }

    /**
     * Test add() method for a bookmark without any field set
     */
    public function testAddMinimal()
    {
        $bookmark = new Bookmark();
        $this->privateLinkDB->add($bookmark);

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());
        $this->assertRegExp('#/shaare/[\w\-]{6}#', $bookmark->getUrl());
        $this->assertRegExp('/[\w\-]{6}/', $bookmark->getShortUrl());
        $this->assertEquals($bookmark->getUrl(), $bookmark->getTitle());
        $this->assertEmpty($bookmark->getDescription());
        $this->assertEmpty($bookmark->getTags());
        $this->assertEmpty($bookmark->getThumbnail());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertFalse($bookmark->isSticky());
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getCreated());
        $this->assertNull($bookmark->getUpdated());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());
        $this->assertRegExp('#/shaare/[\w\-]{6}#', $bookmark->getUrl());
        $this->assertRegExp('/[\w\-]{6}/', $bookmark->getShortUrl());
        $this->assertEquals($bookmark->getUrl(), $bookmark->getTitle());
        $this->assertEmpty($bookmark->getDescription());
        $this->assertEmpty($bookmark->getTags());
        $this->assertEmpty($bookmark->getThumbnail());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertFalse($bookmark->isSticky());
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getCreated());
        $this->assertNull($bookmark->getUpdated());
    }

    /**
     * Test add() method for a bookmark without any field set and without writing the data store
     */
    public function testAddMinimalNoWrite()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        $bookmark = new Bookmark();
        $this->privateLinkDB->add($bookmark, false);

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $this->privateLinkDB->get(43);
    }

    /**
     * Test add() method while logged out
     */
    public function testAddLoggedOut()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You\'re not authorized to alter the datastore');

        $this->publicLinkDB->add(new Bookmark());
    }

    /**
     * Test add() method with a Bookmark already containing an ID
     */
    public function testAddWithId()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This bookmarks already exists');

        $bookmark = new Bookmark();
        $bookmark->setId(43);
        $this->privateLinkDB->add($bookmark);
    }

    /**
     * Test set() method for a bookmark fully built
     */
    public function testSetFull()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $bookmark->setUrl($url = 'https://domain.tld/index.php');
        $bookmark->setShortUrl('abc');
        $bookmark->setTitle($title = 'This a brand new bookmark');
        $bookmark->setDescription($desc = 'It should be created and written');
        $bookmark->setTags($tags = ['tag1', 'tagssss']);
        $bookmark->setThumbnail($thumb = 'http://thumb.tld/dle.png');
        $bookmark->setPrivate(true);
        $bookmark->setSticky(true);
        $bookmark->setCreated($created = DateTime::createFromFormat('Ymd_His', '20190518_140354'));
        $bookmark->setUpdated($updated = DateTime::createFromFormat('Ymd_His', '20190518_150354'));

        $this->privateLinkDB->set($bookmark);
        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($url, $bookmark->getUrl());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($title, $bookmark->getTitle());
        $this->assertEquals($desc, $bookmark->getDescription());
        $this->assertEquals($tags, $bookmark->getTags());
        $this->assertEquals($thumb, $bookmark->getThumbnail());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertTrue($bookmark->isSticky());
        $this->assertEquals($created, $bookmark->getCreated());
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getUpdated());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($url, $bookmark->getUrl());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($title, $bookmark->getTitle());
        $this->assertEquals($desc, $bookmark->getDescription());
        $this->assertEquals($tags, $bookmark->getTags());
        $this->assertEquals($thumb, $bookmark->getThumbnail());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertTrue($bookmark->isSticky());
        $this->assertEquals($created, $bookmark->getCreated());
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getUpdated());
    }

    /**
     * Test set() method for a bookmark without any field set
     */
    public function testSetMinimal()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $this->privateLinkDB->set($bookmark);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('/shaare/WDWyig', $bookmark->getUrl());
        $this->assertEquals('1eYJ1Q', $bookmark->getShortUrl());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
        $this->assertEquals('Used to test bookmarks reordering.', $bookmark->getDescription());
        $this->assertEquals(['ut'], $bookmark->getTags());
        $this->assertFalse($bookmark->getThumbnail());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertFalse($bookmark->isSticky());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20100310_101010'),
            $bookmark->getCreated()
        );
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getUpdated());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('/shaare/WDWyig', $bookmark->getUrl());
        $this->assertEquals('1eYJ1Q', $bookmark->getShortUrl());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
        $this->assertEquals('Used to test bookmarks reordering.', $bookmark->getDescription());
        $this->assertEquals(['ut'], $bookmark->getTags());
        $this->assertFalse($bookmark->getThumbnail());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertFalse($bookmark->isSticky());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20100310_101010'),
            $bookmark->getCreated()
        );
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getUpdated());
    }

    /**
     * Test set() method for a bookmark without any field set and without writing the data store
     */
    public function testSetMinimalNoWrite()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $bookmark->setTitle($title = 'hi!');
        $this->privateLinkDB->set($bookmark, false);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($title, $bookmark->getTitle());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
    }

    /**
     * Test set() method while logged out
     */
    public function testSetLoggedOut()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You\'re not authorized to alter the datastore');

        $this->publicLinkDB->set(new Bookmark());
    }

    /**
     * Test set() method with a Bookmark without an ID defined.
     */
    public function testSetWithoutId()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        $bookmark = new Bookmark();
        $this->privateLinkDB->set($bookmark);
    }

    /**
     * Test set() method with a Bookmark with an unknow ID
     */
    public function testSetWithUnknownId()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        $bookmark = new Bookmark();
        $bookmark->setId(666);
        $this->privateLinkDB->set($bookmark);
    }

    /**
     * Test addOrSet() method with a new ID
     */
    public function testAddOrSetNew()
    {
        $bookmark = new Bookmark();
        $this->privateLinkDB->addOrSet($bookmark);

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());
    }

    /**
     * Test addOrSet() method with an existing ID
     */
    public function testAddOrSetExisting()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $bookmark->setTitle($title = 'hi!');
        $this->privateLinkDB->addOrSet($bookmark);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($title, $bookmark->getTitle());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($title, $bookmark->getTitle());
    }

    /**
     * Test addOrSet() method while logged out
     */
    public function testAddOrSetLoggedOut()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You\'re not authorized to alter the datastore');

        $this->publicLinkDB->addOrSet(new Bookmark());
    }

    /**
     * Test addOrSet() method for a bookmark without any field set and without writing the data store
     */
    public function testAddOrSetMinimalNoWrite()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $bookmark->setTitle($title = 'hi!');
        $this->privateLinkDB->addOrSet($bookmark, false);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($title, $bookmark->getTitle());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
    }

    /**
     * Test remove() method with an existing Bookmark
     */
    public function testRemoveExisting()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        $bookmark = $this->privateLinkDB->get(42);
        $this->privateLinkDB->remove($bookmark);

        $exception = null;
        try {
            $this->privateLinkDB->get(42);
        } catch (BookmarkNotFoundException $e) {
            $exception = $e;
        }
        $this->assertInstanceOf(BookmarkNotFoundException::class, $exception);

        // reload from file
        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            true
        );

        $this->privateLinkDB->get(42);
    }

    /**
     * Test remove() method while logged out
     */
    public function testRemoveLoggedOut()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You\'re not authorized to alter the datastore');

        $bookmark = $this->privateLinkDB->get(42);
        $this->publicLinkDB->remove($bookmark);
    }

    /**
     * Test remove() method with a Bookmark with an unknown ID
     */
    public function testRemoveWithUnknownId()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        $bookmark = new Bookmark();
        $bookmark->setId(666);
        $this->privateLinkDB->remove($bookmark);
    }

    /**
     * Test exists() method
     */
    public function testExists()
    {
        $this->assertTrue($this->privateLinkDB->exists(42)); // public
        $this->assertTrue($this->privateLinkDB->exists(6)); // private

        $this->assertTrue($this->privateLinkDB->exists(42, BookmarkFilter::$ALL));
        $this->assertTrue($this->privateLinkDB->exists(6, BookmarkFilter::$ALL));

        $this->assertTrue($this->privateLinkDB->exists(42, BookmarkFilter::$PUBLIC));
        $this->assertFalse($this->privateLinkDB->exists(6, BookmarkFilter::$PUBLIC));

        $this->assertFalse($this->privateLinkDB->exists(42, BookmarkFilter::$PRIVATE));
        $this->assertTrue($this->privateLinkDB->exists(6, BookmarkFilter::$PRIVATE));

        $this->assertTrue($this->publicLinkDB->exists(42));
        $this->assertFalse($this->publicLinkDB->exists(6));

        $this->assertTrue($this->publicLinkDB->exists(42, BookmarkFilter::$PUBLIC));
        $this->assertFalse($this->publicLinkDB->exists(6, BookmarkFilter::$PUBLIC));

        $this->assertFalse($this->publicLinkDB->exists(42, BookmarkFilter::$PRIVATE));
        $this->assertTrue($this->publicLinkDB->exists(6, BookmarkFilter::$PRIVATE));
    }

    /**
     * Test initialize() method
     */
    public function testInitialize()
    {
        $dbSize = $this->privateLinkDB->count();
        $this->privateLinkDB->initialize();
        $this->assertEquals($dbSize + 3, $this->privateLinkDB->count());
        $this->assertStringStartsWith(
            'Shaarli will automatically pick up the thumbnail for links to a variety of websites.',
            $this->privateLinkDB->get(43)->getDescription()
        );
        $this->assertStringStartsWith(
            'Adding a shaare without entering a URL creates a text-only "note" post such as this one.',
            $this->privateLinkDB->get(44)->getDescription()
        );
        $this->assertStringStartsWith(
            'Welcome to Shaarli!',
            $this->privateLinkDB->get(45)->getDescription()
        );
    }

    /*
     * The following tests have been taken from the legacy LinkDB test and adapted
     * to make sure that nothing have been broken in the migration process.
     * They mostly cover search/filters. Some of them might be redundant with the previous ones.
     */
    /**
     * Attempt to instantiate a LinkDB whereas the datastore is not writable
     */
    public function testConstructDatastoreNotWriteable()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\NotWritableDataStoreException::class);
        $this->expectExceptionMessageRegExp('#Couldn\'t load data from the data store file "null".*#');

        $conf = new ConfigManager('tests/utils/config/configJson');
        $conf->set('resource.datastore', 'null/store.db');
        new BookmarkFileService($conf, $this->pluginManager, $this->history, $this->mutex, true);
    }

    /**
     * The DB doesn't exist, ensure it is created with an empty datastore
     */
    public function testCheckDBNewLoggedIn()
    {
        unlink(self::$testDatastore);
        $this->assertFileNotExists(self::$testDatastore);
        new BookmarkFileService($this->conf, $this->pluginManager, $this->history, $this->mutex, true);
        $this->assertFileExists(self::$testDatastore);

        // ensure the correct data has been written
        $this->assertGreaterThan(0, filesize(self::$testDatastore));
    }

    /**
     * The DB doesn't exist, but not logged in, ensure it initialized, but the file is not written
     */
    public function testCheckDBNewLoggedOut()
    {
        unlink(self::$testDatastore);
        $this->assertFileNotExists(self::$testDatastore);
        $db = new FakeBookmarkService($this->conf, $this->pluginManager, $this->history, $this->mutex, false);
        $this->assertFileNotExists(self::$testDatastore);
        $this->assertInstanceOf(BookmarkArray::class, $db->getBookmarks());
        $this->assertCount(0, $db->getBookmarks());
    }

    /**
     * Load public bookmarks from the DB
     */
    public function testReadPublicDB()
    {
        $this->assertEquals(
            $this->refDB->countPublicLinks(),
            $this->publicLinkDB->count()
        );
    }

    /**
     * Load public and private bookmarks from the DB
     */
    public function testReadPrivateDB()
    {
        $this->assertEquals(
            $this->refDB->countLinks(),
            $this->privateLinkDB->count()
        );
    }

    /**
     * Save the bookmarks to the DB
     */
    public function testSave()
    {
        $testDB = new BookmarkFileService($this->conf, $this->pluginManager, $this->history, $this->mutex, true);
        $dbSize = $testDB->count();

        $bookmark = new Bookmark();
        $testDB->add($bookmark);

        $testDB = new BookmarkFileService($this->conf, $this->pluginManager, $this->history, $this->mutex, true);
        $this->assertEquals($dbSize + 1, $testDB->count());
    }

    /**
     * Count existing bookmarks - public bookmarks hidden
     */
    public function testCountHiddenPublic()
    {
        $this->conf->set('privacy.hide_public_links', true);
        $linkDB = new BookmarkFileService($this->conf, $this->pluginManager, $this->history, $this->mutex, false);

        $this->assertEquals(0, $linkDB->count());
    }

    /**
     * The URL corresponds to an existing entry in the DB
     */
    public function testGetKnownLinkFromURL()
    {
        $link = $this->publicLinkDB->findByUrl('http://mediagoblin.org/');

        $this->assertNotEquals(false, $link);
        $this->assertContainsPolyfill(
            'A free software media publishing platform',
            $link->getDescription()
        );
    }

    /**
     * The URL is not in the DB
     */
    public function testGetUnknownLinkFromURL()
    {
        $this->assertEquals(
            false,
            $this->publicLinkDB->findByUrl('http://dev.null')
        );
    }

    /**
     * Lists all tags
     */
    public function testAllTags()
    {
        $this->assertEquals(
            [
                'web' => 3,
                'cartoon' => 2,
                'gnu' => 2,
                'dev' => 1,
                'samba' => 1,
                'media' => 1,
                'software' => 1,
                'stallman' => 1,
                'free' => 1,
                '-exclude' => 1,
                'hashtag' => 2,
                // The DB contains a link with `sTuff` and another one with `stuff` tag.
                // They need to be grouped with the first case found - order by date DESC: `sTuff`.
                'sTuff' => 2,
                'ut' => 1,
                'assurance' => 1,
                'coding-style' => 1,
                'quality' => 1,
                'standards' => 1,
            ],
            $this->publicLinkDB->bookmarksCountPerTag()
        );

        $this->assertEquals(
            [
                'web' => 4,
                'cartoon' => 3,
                'gnu' => 2,
                'dev' => 2,
                'samba' => 1,
                'media' => 1,
                'software' => 1,
                'stallman' => 1,
                'free' => 1,
                'html' => 1,
                'w3c' => 1,
                'css' => 1,
                'Mercurial' => 1,
                'sTuff' => 2,
                '-exclude' => 1,
                '.hidden' => 1,
                'hashtag' => 2,
                'tag1' => 1,
                'tag2' => 1,
                'tag3' => 1,
                'tag4' => 1,
                'ut' => 1,
                'assurance' => 1,
                'coding-style' => 1,
                'quality' => 1,
                'standards' => 1,
            ],
            $this->privateLinkDB->bookmarksCountPerTag()
        );
        $this->assertEquals(
            [
                'cartoon' => 2,
                'gnu' => 1,
                'dev' => 1,
                'samba' => 1,
                'media' => 1,
                'html' => 1,
                'w3c' => 1,
                'css' => 1,
                'Mercurial' => 1,
                '.hidden' => 1,
                'hashtag' => 1,
            ],
            $this->privateLinkDB->bookmarksCountPerTag(['web'])
        );
        $this->assertEquals(
            [
                'html' => 1,
                'w3c' => 1,
                'css' => 1,
                'Mercurial' => 1,
            ],
            $this->privateLinkDB->bookmarksCountPerTag(['web'], 'private')
        );
    }

    /**
     * Test filter with string.
     */
    public function testFilterString()
    {
        $tags = 'dev cartoon';
        $request = ['searchtags' => $tags];
        $this->assertEquals(
            2,
            count($this->privateLinkDB->search($request, null, true)->getBookmarks())
        );
    }

    /**
     * Test filter with array.
     */
    public function testFilterArray()
    {
        $tags = ['dev', 'cartoon'];
        $request = ['searchtags' => $tags];
        $this->assertEquals(
            2,
            count($this->privateLinkDB->search($request, null, true)->getBookmarks())
        );
    }

    /**
     * Test hidden tags feature:
     *  tags starting with a dot '.' are only visible when logged in.
     */
    public function testHiddenTags()
    {
        $tags = '.hidden';
        $request = ['searchtags' => $tags];
        $this->assertEquals(
            1,
            count($this->privateLinkDB->search($request, 'all', true)->getBookmarks())
        );

        $this->assertEquals(
            0,
            count($this->publicLinkDB->search($request, 'public', true)->getBookmarks())
        );
    }

    /**
     * Test filterHash() with a valid smallhash.
     */
    public function testFilterHashValid()
    {
        $request = smallHash('20150310_114651');
        $this->assertSame(
            $request,
            $this->publicLinkDB->findByHash($request)->getShortUrl()
        );
        $request = smallHash('20150310_114633' . 8);
        $this->assertSame(
            $request,
            $this->publicLinkDB->findByHash($request)->getShortUrl()
        );
    }

    /**
     * Test filterHash() with an invalid smallhash.
     */
    public function testFilterHashInValid1()
    {
        $this->expectException(BookmarkNotFoundException::class);

        $request = 'blabla';
        $this->publicLinkDB->findByHash($request);
    }

    /**
     * Test filterHash() with an empty smallhash.
     */
    public function testFilterHashInValid()
    {
        $this->expectException(BookmarkNotFoundException::class);

        $this->publicLinkDB->findByHash('');
    }

    /**
     * Test filterHash() on a private bookmark while logged out.
     */
    public function testFilterHashPrivateWhileLoggedOut()
    {
        $this->expectException(BookmarkNotFoundException::class);
        $this->expectExceptionMessage('The link you are trying to reach does not exist or has been deleted');

        $hash = smallHash('20141125_084734' . 6);

        $this->publicLinkDB->findByHash($hash);
    }

    /**
     * Test filterHash() with private key.
     */
    public function testFilterHashWithPrivateKey()
    {
        $hash = smallHash('20141125_084734' . 6);
        $privateKey = 'this is usually auto generated';

        $bookmark = $this->privateLinkDB->findByHash($hash);
        $bookmark->setAdditionalContentEntry('private_key', $privateKey);
        $this->privateLinkDB->save();

        $this->privateLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            false
        );
        $bookmark = $this->privateLinkDB->findByHash($hash, $privateKey);

        static::assertSame(6, $bookmark->getId());
    }

    /**
     * Test linksCountPerTag all tags without filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountLinkPerTagAllNoFilter()
    {
        $expected = [
            'web' => 4,
            'cartoon' => 3,
            'dev' => 2,
            'gnu' => 2,
            'hashtag' => 2,
            'sTuff' => 2,
            '-exclude' => 1,
            '.hidden' => 1,
            'Mercurial' => 1,
            'css' => 1,
            'free' => 1,
            'html' => 1,
            'media' => 1,
            'samba' => 1,
            'software' => 1,
            'stallman' => 1,
            'tag1' => 1,
            'tag2' => 1,
            'tag3' => 1,
            'tag4' => 1,
            'ut' => 1,
            'w3c' => 1,
            'assurance' => 1,
            'coding-style' => 1,
            'quality' => 1,
            'standards' => 1,
        ];
        $tags = $this->privateLinkDB->bookmarksCountPerTag();

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test linksCountPerTag all tags with filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountLinkPerTagAllWithFilter()
    {
        $expected = [
            'hashtag' => 2,
            '-exclude' => 1,
            '.hidden' => 1,
            'free' => 1,
            'media' => 1,
            'software' => 1,
            'stallman' => 1,
            'stuff' => 1,
            'web' => 1,
        ];
        $tags = $this->privateLinkDB->bookmarksCountPerTag(['gnu']);

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test linksCountPerTag public tags with filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountLinkPerTagPublicWithFilter()
    {
        $expected = [
            'hashtag' => 2,
            '-exclude' => 1,
            '.hidden' => 1,
            'free' => 1,
            'media' => 1,
            'software' => 1,
            'stallman' => 1,
            'stuff' => 1,
            'web' => 1,
        ];
        $tags = $this->privateLinkDB->bookmarksCountPerTag(['gnu'], 'public');

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test linksCountPerTag public tags with filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountLinkPerTagPrivateWithFilter()
    {
        $expected = [
            'cartoon' => 1,
            'tag1' => 1,
            'tag2' => 1,
            'tag3' => 1,
            'tag4' => 1,
        ];
        $tags = $this->privateLinkDB->bookmarksCountPerTag(['dev'], 'private');

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test linksCountPerTag public tags with filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountTagsNoMarkdown()
    {
        $expected = [
            'cartoon' => 3,
            'dev' => 2,
            'tag1' => 1,
            'tag2' => 1,
            'tag3' => 1,
            'tag4' => 1,
            'web' => 4,
            'gnu' => 2,
            'hashtag' => 2,
            'sTuff' => 2,
            '-exclude' => 1,
            '.hidden' => 1,
            'Mercurial' => 1,
            'css' => 1,
            'free' => 1,
            'html' => 1,
            'media' => 1,
            'newTagToCount' => 1,
            'samba' => 1,
            'software' => 1,
            'stallman' => 1,
            'ut' => 1,
            'w3c' => 1,
            'assurance' => 1,
            'coding-style' => 1,
            'quality' => 1,
            'standards' => 1,
        ];
        $bookmark = new Bookmark();
        $bookmark->setTags(['newTagToCount', BookmarkMarkdownFormatter::NO_MD_TAG]);
        $this->privateLinkDB->add($bookmark);

        $tags = $this->privateLinkDB->bookmarksCountPerTag();

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test find by dates in the middle of the datastore (sorted by dates) with a single bookmark as a result.
     */
    public function testFilterByDateMidTimePeriodSingleBookmark(): void
    {
        $bookmarks = $this->privateLinkDB->findByDate(
            DateTime::createFromFormat('Ymd_His', '20121206_150000'),
            DateTime::createFromFormat('Ymd_His', '20121206_160000'),
            $before,
            $after
        );

        static::assertCount(1, $bookmarks);

        static::assertSame(9, $bookmarks[0]->getId());
        static::assertEquals(DateTime::createFromFormat('Ymd_His', '20121206_142300'), $before);
        static::assertEquals(DateTime::createFromFormat('Ymd_His', '20121206_172539'), $after);
    }

    /**
     * Test find by dates in the middle of the datastore (sorted by dates) with a multiple bookmarks as a result.
     */
    public function testFilterByDateMidTimePeriodMultipleBookmarks(): void
    {
        $bookmarks = $this->privateLinkDB->findByDate(
            DateTime::createFromFormat('Ymd_His', '20121206_150000'),
            DateTime::createFromFormat('Ymd_His', '20121206_180000'),
            $before,
            $after
        );

        static::assertCount(2, $bookmarks);

        static::assertSame(1, $bookmarks[0]->getId());
        static::assertSame(9, $bookmarks[1]->getId());
        static::assertEquals(DateTime::createFromFormat('Ymd_His', '20121206_142300'), $before);
        static::assertEquals(DateTime::createFromFormat('Ymd_His', '20121206_182539'), $after);
    }

    /**
     * Test find by dates at the end of the datastore (sorted by dates).
     */
    public function testFilterByDateLastTimePeriod(): void
    {
        $after = new DateTime();
        $bookmarks = $this->privateLinkDB->findByDate(
            DateTime::createFromFormat('Ymd_His', '20150310_114640'),
            DateTime::createFromFormat('Ymd_His', '20450101_010101'),
            $before,
            $after
        );

        static::assertCount(1, $bookmarks);

        static::assertSame(41, $bookmarks[0]->getId());
        static::assertEquals(DateTime::createFromFormat('Ymd_His', '20150310_114633'), $before);
        static::assertNull($after);
    }

    /**
     * Test find by dates at the beginning of the datastore (sorted by dates).
     */
    public function testFilterByDateFirstTimePeriod(): void
    {
        $before = new DateTime();
        $bookmarks = $this->privateLinkDB->findByDate(
            DateTime::createFromFormat('Ymd_His', '20000101_101010'),
            DateTime::createFromFormat('Ymd_His', '20100309_110000'),
            $before,
            $after
        );

        static::assertCount(1, $bookmarks);

        static::assertSame(11, $bookmarks[0]->getId());
        static::assertNull($before);
        static::assertEquals(DateTime::createFromFormat('Ymd_His', '20100310_101010'), $after);
    }

    /**
     * Test getLatest with a sticky bookmark: it should be ignored and return the latest by creation date instead.
     */
    public function testGetLatestWithSticky(): void
    {
        $bookmark = $this->publicLinkDB->getLatest();

        static::assertSame(41, $bookmark->getId());
    }

    /**
     * Test getLatest with a sticky bookmark: it should be ignored and return the latest by creation date instead.
     */
    public function testGetLatestEmptyDatastore(): void
    {
        unlink($this->conf->get('resource.datastore'));
        $this->publicLinkDB = new BookmarkFileService(
            $this->conf,
            $this->pluginManager,
            $this->history,
            $this->mutex,
            false
        );

        $bookmark = $this->publicLinkDB->getLatest();

        static::assertNull($bookmark);
    }

    /**
     * Allows to test LinkDB's private methods
     *
     * @see
     *  https://sebastian-bergmann.de/archives/881-Testing-Your-Privates.html
     *  http://stackoverflow.com/a/2798203
     */
    protected static function getMethod($name)
    {
        $class = new ReflectionClass('Shaarli\Bookmark\BookmarkFileService');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}

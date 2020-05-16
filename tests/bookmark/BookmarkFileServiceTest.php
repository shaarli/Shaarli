<?php
/**
 * Link datastore tests
 */

namespace Shaarli\Bookmark;

use DateTime;
use PHPUnit\Framework\TestCase;
use ReferenceLinkDB;
use ReflectionClass;
use Shaarli;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\History;

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
    protected function setUp()
    {
        if (file_exists(self::$testDatastore)) {
            unlink(self::$testDatastore);
        }

        if (file_exists(self::$testConf .'.json.php')) {
            unlink(self::$testConf .'.json.php');
        }

        if (file_exists(self::$testUpdates)) {
            unlink(self::$testUpdates);
        }

        copy('tests/utils/config/configJson.json.php', self::$testConf .'.json.php');
        $this->conf = new ConfigManager(self::$testConf);
        $this->conf->set('resource.datastore', self::$testDatastore);
        $this->conf->set('resource.updates', self::$testUpdates);
        $this->refDB = new \ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);
        $this->history = new History('sandbox/history.php');
        $this->publicLinkDB = new BookmarkFileService($this->conf, $this->history, false);
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);
    }

    /**
     * Test migrate() method with a legacy datastore.
     */
    public function testDatabaseMigration()
    {
        if (!defined('SHAARLI_VERSION')) {
            define('SHAARLI_VERSION', 'dev');
        }

        $this->refDB = new \ReferenceLinkDB(true);
        $this->refDB->write(self::$testDatastore);
        $db = self::getMethod('migrate');
        $db->invokeArgs($this->privateLinkDB, []);

        $db = new \FakeBookmarkService($this->conf, $this->history, true);
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
     *
     * @expectedException Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testGetUndefined()
    {
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

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
        $this->assertRegExp('/\?[\w\-]{6}/', $bookmark->getUrl());
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());
        $this->assertRegExp('/\?[\w\-]{6}/', $bookmark->getUrl());
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
     *
     * @expectedExceptionMessage Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testAddMinimalNoWrite()
    {
        $bookmark = new Bookmark();
        $this->privateLinkDB->add($bookmark);

        $bookmark = $this->privateLinkDB->get(43);
        $this->assertEquals(43, $bookmark->getId());

        // reload from file
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $this->privateLinkDB->get(43);
    }

    /**
     * Test add() method while logged out
     *
     * @expectedException \Exception
     * @expectedExceptionMessage You're not authorized to alter the datastore
     */
    public function testAddLoggedOut()
    {
        $this->publicLinkDB->add(new Bookmark());
    }

    /**
     * Test add() method with an entry which is not a bookmark instance
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Provided data is invalid
     */
    public function testAddNotABookmark()
    {
        $this->privateLinkDB->add(['title' => 'hi!']);
    }

    /**
     * Test add() method with a Bookmark already containing an ID
     *
     * @expectedException \Exception
     * @expectedExceptionMessage This bookmarks already exists
     */
    public function testAddWithId()
    {
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

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
        $this->assertEquals('?WDWyig', $bookmark->getUrl());
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('?WDWyig', $bookmark->getUrl());
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
    }

    /**
     * Test set() method while logged out
     *
     * @expectedException \Exception
     * @expectedExceptionMessage You're not authorized to alter the datastore
     */
    public function testSetLoggedOut()
    {
        $this->publicLinkDB->set(new Bookmark());
    }

    /**
     * Test set() method with an entry which is not a bookmark instance
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Provided data is invalid
     */
    public function testSetNotABookmark()
    {
        $this->privateLinkDB->set(['title' => 'hi!']);
    }

    /**
     * Test set() method with a Bookmark without an ID defined.
     *
     * @expectedException Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testSetWithoutId()
    {
        $bookmark = new Bookmark();
        $this->privateLinkDB->set($bookmark);
    }

    /**
     * Test set() method with a Bookmark with an unknow ID
     *
     * @expectedException Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testSetWithUnknownId()
    {
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals($title, $bookmark->getTitle());
    }

    /**
     * Test addOrSet() method while logged out
     *
     * @expectedException \Exception
     * @expectedExceptionMessage You're not authorized to alter the datastore
     */
    public function testAddOrSetLoggedOut()
    {
        $this->publicLinkDB->addOrSet(new Bookmark());
    }

    /**
     * Test addOrSet() method with an entry which is not a bookmark instance
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Provided data is invalid
     */
    public function testAddOrSetNotABookmark()
    {
        $this->privateLinkDB->addOrSet(['title' => 'hi!']);
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $bookmark = $this->privateLinkDB->get(42);
        $this->assertEquals(42, $bookmark->getId());
        $this->assertEquals('Note: I have a big ID but an old date', $bookmark->getTitle());
    }

    /**
     * Test remove() method with an existing Bookmark
     *
     * @expectedException Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testRemoveExisting()
    {
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
        $this->privateLinkDB = new BookmarkFileService($this->conf, $this->history, true);

        $this->privateLinkDB->get(42);
    }

    /**
     * Test remove() method while logged out
     *
     * @expectedException \Exception
     * @expectedExceptionMessage You're not authorized to alter the datastore
     */
    public function testRemoveLoggedOut()
    {
        $bookmark = $this->privateLinkDB->get(42);
        $this->publicLinkDB->remove($bookmark);
    }

    /**
     * Test remove() method with an entry which is not a bookmark instance
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Provided data is invalid
     */
    public function testRemoveNotABookmark()
    {
        $this->privateLinkDB->remove(['title' => 'hi!']);
    }

    /**
     * Test remove() method with a Bookmark with an unknown ID
     *
     * @expectedException Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testRemoveWithUnknownId()
    {
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
        $this->assertEquals($dbSize + 2, $this->privateLinkDB->count());
        $this->assertEquals(
            'My secret stuff... - Pastebin.com',
            $this->privateLinkDB->get(43)->getTitle()
        );
        $this->assertEquals(
            'The personal, minimalist, super-fast, database free, bookmarking service',
            $this->privateLinkDB->get(44)->getTitle()
        );
    }

    /*
     * The following tests have been taken from the legacy LinkDB test and adapted
     * to make sure that nothing have been broken in the migration process.
     * They mostly cover search/filters. Some of them might be redundant with the previous ones.
     */

    /**
     * Attempt to instantiate a LinkDB whereas the datastore is not writable
     *
     * @expectedException              Shaarli\Bookmark\Exception\NotWritableDataStoreException
     * @expectedExceptionMessageRegExp #Couldn't load data from the data store file "null".*#
     */
    public function testConstructDatastoreNotWriteable()
    {
        $conf = new ConfigManager('tests/utils/config/configJson');
        $conf->set('resource.datastore', 'null/store.db');
        new BookmarkFileService($conf, $this->history, true);
    }

    /**
     * The DB doesn't exist, ensure it is created with an empty datastore
     */
    public function testCheckDBNewLoggedIn()
    {
        unlink(self::$testDatastore);
        $this->assertFileNotExists(self::$testDatastore);
        new BookmarkFileService($this->conf, $this->history, true);
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
        $db = new \FakeBookmarkService($this->conf, $this->history, false);
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
        $testDB = new BookmarkFileService($this->conf, $this->history, true);
        $dbSize = $testDB->count();

        $bookmark = new Bookmark();
        $testDB->add($bookmark);

        $testDB = new BookmarkFileService($this->conf, $this->history, true);
        $this->assertEquals($dbSize + 1, $testDB->count());
    }

    /**
     * Count existing bookmarks - public bookmarks hidden
     */
    public function testCountHiddenPublic()
    {
        $this->conf->set('privacy.hide_public_links', true);
        $linkDB = new BookmarkFileService($this->conf, $this->history, false);

        $this->assertEquals(0, $linkDB->count());
    }

    /**
     * List the days for which bookmarks have been posted
     */
    public function testDays()
    {
        $this->assertEquals(
            ['20100309', '20100310', '20121206', '20121207', '20130614', '20150310'],
            $this->publicLinkDB->days()
        );

        $this->assertEquals(
            ['20100309', '20100310', '20121206', '20121207', '20130614', '20141125', '20150310'],
            $this->privateLinkDB->days()
        );
    }

    /**
     * The URL corresponds to an existing entry in the DB
     */
    public function testGetKnownLinkFromURL()
    {
        $link = $this->publicLinkDB->findByUrl('http://mediagoblin.org/');

        $this->assertNotEquals(false, $link);
        $this->assertContains(
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
            count($this->privateLinkDB->search($request, null, true))
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
            count($this->privateLinkDB->search($request, null, true))
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
            count($this->privateLinkDB->search($request, 'all', true))
        );

        $this->assertEquals(
            0,
            count($this->publicLinkDB->search($request, 'public', true))
        );
    }

    /**
     * Test filterHash() with a valid smallhash.
     */
    public function testFilterHashValid()
    {
        $request = smallHash('20150310_114651');
        $this->assertEquals(
            1,
            count($this->publicLinkDB->findByHash($request))
        );
        $request = smallHash('20150310_114633' . 8);
        $this->assertEquals(
            1,
            count($this->publicLinkDB->findByHash($request))
        );
    }

    /**
     * Test filterHash() with an invalid smallhash.
     *
     * @expectedException \Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testFilterHashInValid1()
    {
        $request = 'blabla';
        $this->publicLinkDB->findByHash($request);
    }

    /**
     * Test filterHash() with an empty smallhash.
     *
     * @expectedException \Shaarli\Bookmark\Exception\BookmarkNotFoundException
     */
    public function testFilterHashInValid()
    {
        $this->publicLinkDB->findByHash('');
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
        ];
        $bookmark = new Bookmark();
        $bookmark->setTags(['newTagToCount', BookmarkMarkdownFormatter::NO_MD_TAG]);
        $this->privateLinkDB->add($bookmark);

        $tags = $this->privateLinkDB->bookmarksCountPerTag();

        $this->assertEquals($expected, $tags, var_export($tags, true));
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

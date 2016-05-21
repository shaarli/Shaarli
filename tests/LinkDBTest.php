<?php
/**
 * Link datastore tests
 */

require_once 'application/Cache.php';
require_once 'application/FileUtils.php';
require_once 'application/LinkDB.php';
require_once 'application/Utils.php';
require_once 'tests/utils/ReferenceLinkDB.php';


/**
 * Unitary tests for LinkDB
 */
class LinkDBTest extends PHPUnit_Framework_TestCase
{
    // datastore to test write operations
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var ReferenceLinkDB instance.
     */
    protected static $refDB = null;

    /**
     * @var LinkDB public LinkDB instance.
     */
    protected static $publicLinkDB = null;

    /**
     * @var LinkDB private LinkDB instance.
     */
    protected static $privateLinkDB = null;

    /**
     * Instantiates public and private LinkDBs with test data
     *
     * The reference datastore contains public and private links that
     * will be used to test LinkDB's methods:
     *  - access filtering (public/private),
     *  - link searches:
     *    - by day,
     *    - by tag,
     *    - by text,
     *  - etc.
     */
    public static function setUpBeforeClass()
    {
        self::$refDB = new ReferenceLinkDB();
        self::$refDB->write(self::$testDatastore);

        self::$publicLinkDB = new LinkDB(self::$testDatastore, false, false);
        self::$privateLinkDB = new LinkDB(self::$testDatastore, true, false);
    }

    /**
     * Resets test data for each test
     */
    protected function setUp()
    {
        if (file_exists(self::$testDatastore)) {
            unlink(self::$testDatastore);
        }
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
        $class = new ReflectionClass('LinkDB');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Instantiate LinkDB objects - logged in user
     */
    public function testConstructLoggedIn()
    {
        new LinkDB(self::$testDatastore, true, false);
        $this->assertFileExists(self::$testDatastore);
    }

    /**
     * Instantiate LinkDB objects - logged out or public instance
     */
    public function testConstructLoggedOut()
    {
        new LinkDB(self::$testDatastore, false, false);
        $this->assertFileExists(self::$testDatastore);
    }

    /**
     * Attempt to instantiate a LinkDB whereas the datastore is not writable
     *
     * @expectedException              IOException
     * @expectedExceptionMessageRegExp /Error accessing null/
     */
    public function testConstructDatastoreNotWriteable()
    {
        new LinkDB('null/store.db', false, false);
    }

    /**
     * The DB doesn't exist, ensure it is created with dummy content
     */
    public function testCheckDBNew()
    {
        $linkDB = new LinkDB(self::$testDatastore, false, false);
        unlink(self::$testDatastore);
        $this->assertFileNotExists(self::$testDatastore);

        $checkDB = self::getMethod('_checkDB');
        $checkDB->invokeArgs($linkDB, array());
        $this->assertFileExists(self::$testDatastore);

        // ensure the correct data has been written
        $this->assertGreaterThan(0, filesize(self::$testDatastore));
    }

    /**
     * The DB exists, don't do anything
     */
    public function testCheckDBLoad()
    {
        $linkDB = new LinkDB(self::$testDatastore, false, false);
        $datastoreSize = filesize(self::$testDatastore);
        $this->assertGreaterThan(0, $datastoreSize);

        $checkDB = self::getMethod('_checkDB');
        $checkDB->invokeArgs($linkDB, array());

        // ensure the datastore is left unmodified
        $this->assertEquals(
            $datastoreSize,
            filesize(self::$testDatastore)
        );
    }

    /**
     * Load an empty DB
     */
    public function testReadEmptyDB()
    {
        file_put_contents(self::$testDatastore, '<?php /* S7QysKquBQA= */ ?>');
        $emptyDB = new LinkDB(self::$testDatastore, false, false);
        $this->assertEquals(0, sizeof($emptyDB));
        $this->assertEquals(0, count($emptyDB));
    }

    /**
     * Load public links from the DB
     */
    public function testReadPublicDB()
    {
        $this->assertEquals(
            self::$refDB->countPublicLinks(),
            sizeof(self::$publicLinkDB)
        );
    }

    /**
     * Load public and private links from the DB
     */
    public function testReadPrivateDB()
    {
        $this->assertEquals(
            self::$refDB->countLinks(),
            sizeof(self::$privateLinkDB)
        );
    }

    /**
     * Save the links to the DB
     */
    public function testSaveDB()
    {
        $testDB = new LinkDB(self::$testDatastore, true, false);
        $dbSize = sizeof($testDB);

        $link = array(
            'title'=>'an additional link',
            'url'=>'http://dum.my',
            'description'=>'One more',
            'private'=>0,
            'linkdate'=>'20150518_190000',
            'tags'=>'unit test'
        );
        $testDB[$link['linkdate']] = $link;
        $testDB->savedb('tests');

        $testDB = new LinkDB(self::$testDatastore, true, false);
        $this->assertEquals($dbSize + 1, sizeof($testDB));
    }

    /**
     * Count existing links
     */
    public function testCount()
    {
        $this->assertEquals(
            self::$refDB->countPublicLinks(),
            self::$publicLinkDB->count()
        );
        $this->assertEquals(
            self::$refDB->countLinks(),
            self::$privateLinkDB->count()
        );
    }

    /**
     * Count existing links - public links hidden
     */
    public function testCountHiddenPublic()
    {
        $linkDB = new LinkDB(self::$testDatastore, false, true);

        $this->assertEquals(
            0,
            $linkDB->count()
        );
        $this->assertEquals(
            0,
            $linkDB->count()
        );
    }

    /**
     * List the days for which links have been posted
     */
    public function testDays()
    {
        $this->assertEquals(
            array('20121206', '20130614', '20150310'),
            self::$publicLinkDB->days()
        );

        $this->assertEquals(
            array('20121206', '20130614', '20141125', '20150310'),
            self::$privateLinkDB->days()
        );
    }

    /**
     * The URL corresponds to an existing entry in the DB
     */
    public function testGetKnownLinkFromURL()
    {
        $link = self::$publicLinkDB->getLinkFromUrl('http://mediagoblin.org/');

        $this->assertNotEquals(false, $link);
        $this->assertEquals(
            'A free software media publishing platform',
            $link['description']
        );
    }

    /**
     * The URL is not in the DB
     */
    public function testGetUnknownLinkFromURL()
    {
        $this->assertEquals(
            false,
            self::$publicLinkDB->getLinkFromUrl('http://dev.null')
        );
    }

    /**
     * Lists all tags
     */
    public function testAllTags()
    {
        $this->assertEquals(
            array(
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
                'stuff' => 2,
            ),
            self::$publicLinkDB->allTags()
        );

        $this->assertEquals(
            array(
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
                'stuff' => 2,
                '-exclude' => 1,
                '.hidden' => 1,
            ),
            self::$privateLinkDB->allTags()
        );
    }

    /**
     * Test real_url without redirector.
     */
    public function testLinkRealUrlWithoutRedirector()
    {
        $db = new LinkDB(self::$testDatastore, false, false);
        foreach($db as $link) {
            $this->assertEquals($link['url'], $link['real_url']);
        }
    }

    /**
     * Test real_url with redirector.
     */
    public function testLinkRealUrlWithRedirector()
    {
        $redirector = 'http://redirector.to?';
        $db = new LinkDB(self::$testDatastore, false, false, $redirector);
        foreach($db as $link) {
            $this->assertStringStartsWith($redirector, $link['real_url']);
            $this->assertNotFalse(strpos($link['real_url'], urlencode('://')));
        }

        $db = new LinkDB(self::$testDatastore, false, false, $redirector, false);
        foreach($db as $link) {
            $this->assertStringStartsWith($redirector, $link['real_url']);
            $this->assertFalse(strpos($link['real_url'], urlencode('://')));
        }
    }

    /**
     * Test filter with string.
     */
    public function testFilterString()
    {
        $tags = 'dev cartoon';
        $request = array('searchtags' => $tags);
        $this->assertEquals(
            2,
            count(self::$privateLinkDB->filterSearch($request, true, false))
        );
    }

    /**
     * Test filter with string.
     */
    public function testFilterArray()
    {
        $tags = array('dev', 'cartoon');
        $request = array('searchtags' => $tags);
        $this->assertEquals(
            2,
            count(self::$privateLinkDB->filterSearch($request, true, false))
        );
    }

    /**
     * Test hidden tags feature:
     *  tags starting with a dot '.' are only visible when logged in.
     */
    public function testHiddenTags()
    {
        $tags = '.hidden';
        $request = array('searchtags' => $tags);
        $this->assertEquals(
            1,
            count(self::$privateLinkDB->filterSearch($request, true, false))
        );

        $this->assertEquals(
            0,
            count(self::$publicLinkDB->filterSearch($request, true, false))
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
            count(self::$publicLinkDB->filterHash($request))
        );
    }

    /**
     * Test filterHash() with an invalid smallhash.
     *
     * @expectedException LinkNotFoundException
     */
    public function testFilterHashInValid1()
    {
        $request = 'blabla';
        self::$publicLinkDB->filterHash($request);
    }

    /**
     * Test filterHash() with an empty smallhash.
     *
     * @expectedException LinkNotFoundException
     */
    public function testFilterHashInValid()
    {
        self::$publicLinkDB->filterHash('');
    }

    /**
     * test whitespace handling in tags.
     * split on whitespace runs and don't allow empty tags.
     */
    public function testReadTagsWithWhitespace()
    {
        // test load contains "tags": " tag1  tag2   tag3  tag4   "
        $ds  = '<?php /* ';
        $ds .= 'fY5BDoMwDAS/gvwB4hRKMY9BEaASNQKUuL1U/L22D721Odheez';
        $ds .= 'baQEjvQtgSeIdX12I/onfyYAjU6e1GkOL2mAMvMPxAC8mWIydD';
        $ds .= 'enHsU0jrXlj1heCZk5k7gpX5oLr+ErUdkGBeypTjwXHfdOUItD';
        $ds .= 'XycbgXHb24KxFYafWVtYvVRoQiQhw5vixrJDdY/LyENP5PcJ4f */ ?>';
        file_put_contents(self::$testDatastore, $ds);
        self::$publicLinkDB = new LinkDB(self::$testDatastore, false, false);

        $this->assertEquals(
            array(
                'tag1' => 1,
                'tag2' => 1,
                'tag3' => 1,
                'tag4' => 1
            ),
            self::$publicLinkDB->allTags()
        );
    }
}

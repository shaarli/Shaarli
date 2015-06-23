<?php
/**
 * Link datastore tests
 */

require_once 'application/LinkDB.php';
require_once 'application/Utils.php';
require_once 'tests/utils/ReferenceLinkDB.php';

define('PHPPREFIX', '<?php /* ');
define('PHPSUFFIX', ' */ ?>');


/**
 * Unitary tests for LinkDB
 */
class LinkDBTest extends PHPUnit_Framework_TestCase
{
    // datastore to test write operations
    protected static $testDatastore = 'tests/datastore.php';
    protected static $dummyDatastoreSHA1 = 'e3edea8ea7bb50be4bcb404df53fbb4546a7156e';
    protected static $refDB = null;
    protected static $publicLinkDB = null;
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
        $GLOBALS['config']['DATASTORE'] = self::$testDatastore;
    }

    /**
     * Resets test data for each test
     */
    protected function setUp()
    {
        self::$refDB = new ReferenceLinkDB();
        self::$refDB->write(self::$testDatastore, PHPPREFIX, PHPSUFFIX);

        $GLOBALS['config']['HIDE_PUBLIC_LINKS'] = false;
        self::$publicLinkDB = new LinkDB(false);
        self::$privateLinkDB = new LinkDB(true);

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
        new LinkDB(true);
        $this->assertFileExists(self::$testDatastore);
    }

    /**
     * Instantiate LinkDB objects - logged out or public instance
     */
    public function testConstructLoggedOut()
    {
        new LinkDB(false);
        $this->assertFileExists(self::$testDatastore);
    }

    /**
     * Attempt to instantiate a LinkDB whereas the datastore is not writable
     *
     * @expectedException              PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessageRegExp /failed to open stream: No such file or directory/
     */
    public function testConstructDatastoreNotWriteable()
    {
        $GLOBALS['config']['DATASTORE'] = 'null/store.db';
        new LinkDB(false);
    }

    /**
     * The DB doesn't exist, ensure it is created with dummy content
     */
    public function testCheckDBNew()
    {
        $linkDB = new LinkDB(false);
        unlink(self::$testDatastore);
        $this->assertFileNotExists(self::$testDatastore);

        $checkDB = self::getMethod('checkDB');
        $checkDB->invokeArgs($linkDB, array());
        $this->assertFileExists(self::$testDatastore);

        // ensure the correct data has been written
        $this->assertEquals(
            self::$dummyDatastoreSHA1,
            sha1_file(self::$testDatastore)
        );
    }

    /**
     * The DB exists, don't do anything
     */
    public function testCheckDBLoad()
    {
        $linkDB = new LinkDB(false);
        $this->assertEquals(
            self::$dummyDatastoreSHA1,
            sha1_file(self::$testDatastore)
        );

        $checkDB = self::getMethod('checkDB');
        $checkDB->invokeArgs($linkDB, array());

        // ensure the datastore is left unmodified
        $this->assertEquals(
            self::$dummyDatastoreSHA1,
            sha1_file(self::$testDatastore)
        );
    }

    /**
     * Load an empty DB
     */
    public function testReadEmptyDB()
    {
        file_put_contents(self::$testDatastore, PHPPREFIX.'S7QysKquBQA='.PHPSUFFIX);
        $emptyDB = new LinkDB(false);
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
        $testDB = new LinkDB(true);
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

        // TODO: move PageCache to a proper class/file
        function invalidateCaches() {}

        $testDB->savedb();

        $testDB = new LinkDB(true);
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
     * Count existing links
     */
    public function testCountWithHiddenPublic()
    {
        $GLOBALS['config']['HIDE_PUBLIC_LINKS'] = true;
        self::$publicLinkDB = new LinkDB(false);
        $this->assertEquals(
            0,
            self::$publicLinkDB->count()
        );
    }

    /**
     * List the days for which links have been posted
     */
    public function testDays()
    {
        $this->assertEquals(
            ['20121206', '20130614', '20150310'],
            self::$publicLinkDB->days()
        );

        $this->assertEquals(
            ['20121206', '20130614', '20141125', '20150310'],
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
            [
                'web' => 3,
                'cartoon' => 2,
                'gnu' => 2,
                'dev' => 1,
                'samba' => 1,
                'media' => 1,
                'software' => 1,
                'stallman' => 1,
                'free' => 1
            ],
            self::$publicLinkDB->allTags()
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
                'Mercurial' => 1
            ],
            self::$privateLinkDB->allTags()
        );
    }

    /**
     * Filter links using a tag
     */
    public function testFilterOneTag()
    {
        $this->assertEquals(
            3,
            sizeof(self::$publicLinkDB->filterTags('web', false))
        );

        $this->assertEquals(
            4,
            sizeof(self::$privateLinkDB->filterTags('web', false))
        );
    }

    /**
     * Filter links using a tag - case-sensitive
     */
    public function testFilterCaseSensitiveTag()
    {
        $this->assertEquals(
            0,
            sizeof(self::$privateLinkDB->filterTags('mercurial', true))
        );

        $this->assertEquals(
            1,
            sizeof(self::$privateLinkDB->filterTags('Mercurial', true))
        );
    }

    /**
     * Filter links using a tag combination
     */
    public function testFilterMultipleTags()
    {
        $this->assertEquals(
            1,
            sizeof(self::$publicLinkDB->filterTags('dev cartoon', false))
        );

        $this->assertEquals(
            2,
            sizeof(self::$privateLinkDB->filterTags('dev cartoon', false))
        );
    }

    /**
     * Filter links using a non-existent tag
     */
    public function testFilterUnknownTag()
    {
        $this->assertEquals(
            0,
            sizeof(self::$publicLinkDB->filterTags('null', false))
        );
    }

    /**
     * Return links for a given day
     */
    public function testFilterDay()
    {
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterDay('20121206'))
        );

        $this->assertEquals(
            3,
            sizeof(self::$privateLinkDB->filterDay('20121206'))
        );
    }

    /**
     * 404 - day not found
     */
    public function testFilterUnknownDay()
    {
        $this->assertEquals(
            0,
            sizeof(self::$publicLinkDB->filterDay('19700101'))
        );

        $this->assertEquals(
            0,
            sizeof(self::$privateLinkDB->filterDay('19700101'))
        );
    }

    /**
     * Use an invalid date format
     */
    public function testFilterInvalidDay()
    {
        $this->assertEquals(
            0,
            sizeof(self::$privateLinkDB->filterDay('Rainy day, dream away'))
        );

        // TODO: check input format
        $this->assertEquals(
            6,
            sizeof(self::$privateLinkDB->filterDay('20'))
        );
    }

    /**
     * Retrieve a link entry with its hash
     */
    public function testFilterSmallHash()
    {
        $links = self::$privateLinkDB->filterSmallHash('IuWvgA');

        $this->assertEquals(
            1,
            sizeof($links)
        );

        $this->assertEquals(
            'MediaGoblin',
            $links['20130614_184135']['title']
        );
        
    }

    /**
     * No link for this hash
     */
    public function testFilterUnknownSmallHash()
    {
        $this->assertEquals(
            0,
            sizeof(self::$privateLinkDB->filterSmallHash('Iblaah'))
        );
    }

    /**
     * Full-text search - result from a link's URL
     */
    public function testFilterFullTextURL()
    {
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('ars.userfriendly.org'))
        );
    }

    /**
     * Full-text search - result from a link's title only
     */
    public function testFilterFullTextTitle()
    {
        // use miscellaneous cases
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('userfriendly -'))
        );
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('UserFriendly -'))
        );
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('uSeRFrIendlY -'))
        );

        // use miscellaneous case and offset
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('RFrIendL'))
        );
    }

    /**
     * Full-text search - result from the link's description only
     */
    public function testFilterFullTextDescription()
    {
        $this->assertEquals(
            1,
            sizeof(self::$publicLinkDB->filterFullText('media publishing'))
        );
    }

    /**
     * Full-text search - result from the link's tags only
     */
    public function testFilterFullTextTags()
    {
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('gnu'))
        );
    }

    /**
     * Full-text search - result set from mixed sources
     */
    public function testFilterFullTextMixed()
    {
        $this->assertEquals(
            2,
            sizeof(self::$publicLinkDB->filterFullText('free software'))
        );
    }
}
?>

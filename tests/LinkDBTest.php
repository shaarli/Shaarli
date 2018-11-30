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
     * @expectedExceptionMessageRegExp /Error accessing "null"/
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

        $checkDB = self::getMethod('check');
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

        $checkDB = self::getMethod('check');
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
    public function testSave()
    {
        $testDB = new LinkDB(self::$testDatastore, true, false);
        $dbSize = sizeof($testDB);

        $link = array(
            'id' => 42,
            'title'=>'an additional link',
            'url'=>'http://dum.my',
            'description'=>'One more',
            'private'=>0,
            'created'=> DateTime::createFromFormat(LinkDB::LINK_DATE_FORMAT, '20150518_190000'),
            'tags'=>'unit test'
        );
        $testDB[$link['id']] = $link;
        $testDB->save('tests');

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
            array('20100309', '20100310', '20121206', '20121207', '20130614', '20150310'),
            self::$publicLinkDB->days()
        );

        $this->assertEquals(
            array('20100309', '20100310', '20121206', '20121207', '20130614', '20141125', '20150310'),
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
        $this->assertContains(
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
                'hashtag' => 2,
                // The DB contains a link with `sTuff` and another one with `stuff` tag.
                // They need to be grouped with the first case found - order by date DESC: `sTuff`.
                'sTuff' => 2,
                'ut' => 1,
            ),
            self::$publicLinkDB->linksCountPerTag()
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
                'sTuff' => 2,
                '-exclude' => 1,
                '.hidden' => 1,
                'hashtag' => 2,
                'tag1' => 1,
                'tag2' => 1,
                'tag3' => 1,
                'tag4' => 1,
                'ut' => 1,
            ),
            self::$privateLinkDB->linksCountPerTag()
        );
        $this->assertEquals(
            array(
                'web' => 4,
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
            ),
            self::$privateLinkDB->linksCountPerTag(['web'])
        );
        $this->assertEquals(
            array(
                'web' => 1,
                'html' => 1,
                'w3c' => 1,
                'css' => 1,
                'Mercurial' => 1,
            ),
            self::$privateLinkDB->linksCountPerTag(['web'], 'private')
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
        $request = smallHash('20150310_114633' . 8);
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
     * Test reorder with asc/desc parameter.
     */
    public function testReorderLinksDesc()
    {
        self::$privateLinkDB->reorder('ASC');
        $stickyIds = [11, 10];
        $standardIds = [42, 4, 9, 1, 0, 7, 6, 8, 41];
        $linkIds = array_merge($stickyIds, $standardIds);
        $cpt = 0;
        foreach (self::$privateLinkDB as $key => $value) {
            $this->assertEquals($linkIds[$cpt++], $key);
        }
        self::$privateLinkDB->reorder('DESC');
        $linkIds = array_merge(array_reverse($stickyIds), array_reverse($standardIds));
        $cpt = 0;
        foreach (self::$privateLinkDB as $key => $value) {
            $this->assertEquals($linkIds[$cpt++], $key);
        }
    }

    /**
     * Test rename tag with a valid value present in multiple links
     */
    public function testRenameTagMultiple()
    {
        self::$refDB->write(self::$testDatastore);
        $linkDB = new LinkDB(self::$testDatastore, true, false);

        $res = $linkDB->renameTag('cartoon', 'Taz');
        $this->assertEquals(3, count($res));
        $this->assertContains(' Taz ', $linkDB[4]['tags']);
        $this->assertContains(' Taz ', $linkDB[1]['tags']);
        $this->assertContains(' Taz ', $linkDB[0]['tags']);
    }

    /**
     * Test rename tag with a valid value
     */
    public function testRenameTagCaseSensitive()
    {
        self::$refDB->write(self::$testDatastore);
        $linkDB = new LinkDB(self::$testDatastore, true, false, '');

        $res = $linkDB->renameTag('sTuff', 'Taz');
        $this->assertEquals(1, count($res));
        $this->assertEquals('Taz', $linkDB[41]['tags']);
    }

    /**
     * Test rename tag with invalid values
     */
    public function testRenameTagInvalid()
    {
        $linkDB = new LinkDB(self::$testDatastore, false, false);

        $this->assertFalse($linkDB->renameTag('', 'test'));
        $this->assertFalse($linkDB->renameTag('', ''));
        // tag non existent
        $this->assertEquals([], $linkDB->renameTag('test', ''));
        $this->assertEquals([], $linkDB->renameTag('test', 'retest'));
    }

    /**
     * Test delete tag with a valid value
     */
    public function testDeleteTag()
    {
        self::$refDB->write(self::$testDatastore);
        $linkDB = new LinkDB(self::$testDatastore, true, false);

        $res = $linkDB->renameTag('cartoon', null);
        $this->assertEquals(3, count($res));
        $this->assertNotContains('cartoon', $linkDB[4]['tags']);
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
        $tags = self::$privateLinkDB->linksCountPerTag();

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test linksCountPerTag all tags with filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountLinkPerTagAllWithFilter()
    {
        $expected = [
            'gnu' => 2,
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
        $tags = self::$privateLinkDB->linksCountPerTag(['gnu']);

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }

    /**
     * Test linksCountPerTag public tags with filter.
     * Equal occurrences should be sorted alphabetically.
     */
    public function testCountLinkPerTagPublicWithFilter()
    {
        $expected = [
            'gnu' => 2,
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
        $tags = self::$privateLinkDB->linksCountPerTag(['gnu'], 'public');

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
            'dev' => 1,
            'tag1' => 1,
            'tag2' => 1,
            'tag3' => 1,
            'tag4' => 1,
        ];
        $tags = self::$privateLinkDB->linksCountPerTag(['dev'], 'private');

        $this->assertEquals($expected, $tags, var_export($tags, true));
    }
}

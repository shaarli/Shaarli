<?php

namespace Shaarli\Netscape;

use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkFileService;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Slim\Http\UploadedFile;

/**
 * Utility function to load a file's metadata in a $_FILES-like array
 *
 * @param string $filename Basename of the file
 *
 * @return UploadedFileInterface Upload file in PSR-7 compatible object
 */
function file2array($filename)
{
    return new UploadedFile(
        __DIR__ . '/input/' . $filename,
        $filename,
        null,
        filesize(__DIR__ . '/input/' . $filename)
    );
}


/**
 * Netscape bookmark import
 */
class BookmarkImportTest extends TestCase
{
    /**
     * @var string datastore to test write operations
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var string History file path
     */
    protected static $historyFilePath = 'sandbox/history.php';

    /**
     * @var BookmarkFileService private LinkDB instance
     */
    protected $bookmarkService = null;

    /**
     * @var string Dummy page cache
     */
    protected $pagecache = 'tests';

    /**
     * @var ConfigManager instance.
     */
    protected $conf;

    /**
     * @var History instance.
     */
    protected $history;

    /**
     * @var NetscapeBookmarkUtils
     */
    protected $netscapeBookmarkUtils;

    /**
     * @var string Save the current timezone.
     */
    protected static $defaultTimeZone;

    public static function setUpBeforeClass()
    {
        self::$defaultTimeZone = date_default_timezone_get();
        // Timezone without DST for test consistency
        date_default_timezone_set('Africa/Nairobi');
    }

    /**
     * Resets test data before each test
     */
    protected function setUp()
    {
        if (file_exists(self::$testDatastore)) {
            unlink(self::$testDatastore);
        }
        // start with an empty datastore
        file_put_contents(self::$testDatastore, '<?php /* S7QysKquBQA= */ ?>');

        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('resource.page_cache', $this->pagecache);
        $this->conf->set('resource.datastore', self::$testDatastore);
        $this->history = new History(self::$historyFilePath);
        $this->bookmarkService = new BookmarkFileService($this->conf, $this->history, true);
        $this->netscapeBookmarkUtils = new NetscapeBookmarkUtils($this->bookmarkService, $this->conf, $this->history);
    }

    /**
     * Delete history file.
     */
    public function tearDown()
    {
        @unlink(self::$historyFilePath);
    }

    public static function tearDownAfterClass()
    {
        date_default_timezone_set(self::$defaultTimeZone);
    }

    /**
     * Attempt to import bookmarks from an empty file
     */
    public function testImportEmptyData()
    {
        $files = file2array('empty.htm');
        $this->assertEquals(
            'File empty.htm (0 bytes) has an unknown file format.'
            .' Nothing was imported.',
            $this->netscapeBookmarkUtils->import(null, $files)
        );
        $this->assertEquals(0, $this->bookmarkService->count());
    }

    /**
     * Attempt to import bookmarks from a file with no Doctype
     */
    public function testImportNoDoctype()
    {
        $files = file2array('no_doctype.htm');
        $this->assertEquals(
            'File no_doctype.htm (350 bytes) has an unknown file format. Nothing was imported.',
            $this->netscapeBookmarkUtils->import(null, $files)
        );
        $this->assertEquals(0, $this->bookmarkService->count());
    }

    /**
     * Attempt to import bookmarks from a file with a lowercase Doctype
     */
    public function testImportLowecaseDoctype()
    {
        $files = file2array('lowercase_doctype.htm');
        $this->assertStringMatchesFormat(
            'File lowercase_doctype.htm (386 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import(null, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
    }


    /**
     * Ensure IE dumps are supported
     */
    public function testImportInternetExplorerEncoding()
    {
        $files = file2array('internet_explorer_encoding.htm');
        $this->assertStringMatchesFormat(
            'File internet_explorer_encoding.htm (356 bytes) was successfully processed in %d seconds:'
            .' 1 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import([], $files)
        );
        $this->assertEquals(1, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));

        $bookmark = $this->bookmarkService->findByUrl('http://hginit.com/');
        $this->assertEquals(0, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160618_203944'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Hg Init a Mercurial tutorial by Joel Spolsky', $bookmark->getTitle());
        $this->assertEquals('http://hginit.com/', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('', $bookmark->getTagsString());
        $this->assertEquals('La37cg', $bookmark->getShortUrl());
    }

    /**
     * Import bookmarks nested in a folder hierarchy
     */
    public function testImportNested()
    {
        $files = file2array('netscape_nested.htm');
        $this->assertStringMatchesFormat(
            'File netscape_nested.htm (1337 bytes) was successfully processed in %d seconds:'
            .' 8 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import([], $files)
        );
        $this->assertEquals(8, $this->bookmarkService->count());
        $this->assertEquals(2, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/1');
        $this->assertEquals(0, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160225_235541'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 1', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/1', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('tag1 tag2', $bookmark->getTagsString());
        $this->assertEquals('KyDNKA', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/1-1');
        $this->assertEquals(1, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160225_235542'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 1-1', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/1-1', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('folder1 tag1 tag2', $bookmark->getTagsString());
        $this->assertEquals('T2LnXg', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/1-2');
        $this->assertEquals(2, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160225_235547'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 1-2', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/1-2', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('folder1 tag3 tag4', $bookmark->getTagsString());
        $this->assertEquals('46SZxA', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/2-1');
        $this->assertEquals(3, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160202_202222'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 2-1', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/2-1', $bookmark->getUrl());
        $this->assertEquals('First link of the second section', $bookmark->getDescription());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertEquals('folder2', $bookmark->getTagsString());
        $this->assertEquals('4UHOSw', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/2-2');
        $this->assertEquals(4, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160119_230227'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 2-2', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/2-2', $bookmark->getUrl());
        $this->assertEquals('Second link of the second section', $bookmark->getDescription());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertEquals('folder2', $bookmark->getTagsString());
        $this->assertEquals('yfzwbw', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/3-1');
        $this->assertEquals(5, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160202_202222'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 3-1', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/3-1', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('folder3 folder3-1 tag3', $bookmark->getTagsString());
        $this->assertEquals('UwxIUQ', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/3-2');
        $this->assertEquals(6, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160119_230227'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 3-2', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/3-2', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('folder3 folder3-1', $bookmark->getTagsString());
        $this->assertEquals('p8dyZg', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://nest.ed/2');
        $this->assertEquals(7, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160229_111541'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Nested 2', $bookmark->getTitle());
        $this->assertEquals('http://nest.ed/2', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('tag4', $bookmark->getTagsString());
        $this->assertEquals('Gt3Uug', $bookmark->getShortUrl());
    }

    /**
     * Import bookmarks with the default privacy setting (reuse from file)
     *
     * The $_POST array is not set.
     */
    public function testImportDefaultPrivacyNoPost()
    {
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import([], $files)
        );

        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(1, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));

        $bookmark = $this->bookmarkService->findByUrl('https://private.tld');
        $this->assertEquals(0, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20001010_135536'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Secret stuff', $bookmark->getTitle());
        $this->assertEquals('https://private.tld', $bookmark->getUrl());
        $this->assertEquals('Super-secret stuff you\'re not supposed to know about', $bookmark->getDescription());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertEquals('private secret', $bookmark->getTagsString());
        $this->assertEquals('EokDtA', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://public.tld');
        $this->assertEquals(1, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160225_235548'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Public stuff', $bookmark->getTitle());
        $this->assertEquals('http://public.tld', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('public hello world', $bookmark->getTagsString());
        $this->assertEquals('Er9ddA', $bookmark->getShortUrl());
    }

    /**
     * Import bookmarks with the default privacy setting (reuse from file)
     */
    public function testImportKeepPrivacy()
    {
        $post = array('privacy' => 'default');
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );

        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(1, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));

        $bookmark = $this->bookmarkService->findByUrl('https://private.tld');
        $this->assertEquals(0, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20001010_135536'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Secret stuff', $bookmark->getTitle());
        $this->assertEquals('https://private.tld', $bookmark->getUrl());
        $this->assertEquals('Super-secret stuff you\'re not supposed to know about', $bookmark->getDescription());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertEquals('private secret', $bookmark->getTagsString());
        $this->assertEquals('EokDtA', $bookmark->getShortUrl());

        $bookmark = $this->bookmarkService->findByUrl('http://public.tld');
        $this->assertEquals(1, $bookmark->getId());
        $this->assertEquals(
            DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, '20160225_235548'),
            $bookmark->getCreated()
        );
        $this->assertEquals('Public stuff', $bookmark->getTitle());
        $this->assertEquals('http://public.tld', $bookmark->getUrl());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertEquals('public hello world', $bookmark->getTagsString());
        $this->assertEquals('Er9ddA', $bookmark->getShortUrl());
    }

    /**
     * Import bookmarks as public
     */
    public function testImportAsPublic()
    {
        $post = array('privacy' => 'public');
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertFalse($this->bookmarkService->get(0)->isPrivate());
        $this->assertFalse($this->bookmarkService->get(1)->isPrivate());
    }

    /**
     * Import bookmarks as private
     */
    public function testImportAsPrivate()
    {
        $post = array('privacy' => 'private');
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(2, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertTrue($this->bookmarkService->get(0)->isPrivate());
        $this->assertTrue($this->bookmarkService->get(1)->isPrivate());
    }

    /**
     * Overwrite private bookmarks so they become public
     */
    public function testOverwriteAsPublic()
    {
        $files = file2array('netscape_basic.htm');

        // import bookmarks as private
        $post = array('privacy' => 'private');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(2, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertTrue($this->bookmarkService->get(0)->isPrivate());
        $this->assertTrue($this->bookmarkService->get(1)->isPrivate());

        // re-import as public, enable overwriting
        $post = array(
            'privacy' => 'public',
            'overwrite' => 'true'
        );
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 2 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertFalse($this->bookmarkService->get(0)->isPrivate());
        $this->assertFalse($this->bookmarkService->get(1)->isPrivate());
    }

    /**
     * Overwrite public bookmarks so they become private
     */
    public function testOverwriteAsPrivate()
    {
        $files = file2array('netscape_basic.htm');

        // import bookmarks as public
        $post = array('privacy' => 'public');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertFalse($this->bookmarkService->get(0)->isPrivate());
        $this->assertFalse($this->bookmarkService->get(1)->isPrivate());

        // re-import as private, enable overwriting
        $post = array(
            'privacy' => 'private',
            'overwrite' => 'true'
        );
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 2 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(2, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertTrue($this->bookmarkService->get(0)->isPrivate());
        $this->assertTrue($this->bookmarkService->get(1)->isPrivate());
    }

    /**
     * Attept to import the same bookmarks twice without enabling overwriting
     */
    public function testSkipOverwrite()
    {
        $post = array('privacy' => 'public');
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));

        // re-import as private, DO NOT enable overwriting
        $post = array('privacy' => 'private');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 0 bookmarks imported, 0 bookmarks overwritten, 2 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
    }

    /**
     * Add user-specified tags to all imported bookmarks
     */
    public function testSetDefaultTags()
    {
        $post = array(
            'privacy' => 'public',
            'default_tags' => 'tag1,tag2 tag3'
        );
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertEquals('tag1 tag2 tag3 private secret', $this->bookmarkService->get(0)->getTagsString());
        $this->assertEquals('tag1 tag2 tag3 public hello world', $this->bookmarkService->get(1)->getTagsString());
    }

    /**
     * The user-specified tags contain characters to be escaped
     */
    public function testSanitizeDefaultTags()
    {
        $post = array(
            'privacy' => 'public',
            'default_tags' => 'tag1&,tag2 "tag3"'
        );
        $files = file2array('netscape_basic.htm');
        $this->assertStringMatchesFormat(
            'File netscape_basic.htm (482 bytes) was successfully processed in %d seconds:'
            .' 2 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import($post, $files)
        );
        $this->assertEquals(2, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertEquals(
            'tag1&amp; tag2 &quot;tag3&quot; private secret',
            $this->bookmarkService->get(0)->getTagsString()
        );
        $this->assertEquals(
            'tag1&amp; tag2 &quot;tag3&quot; public hello world',
            $this->bookmarkService->get(1)->getTagsString()
        );
    }

    /**
     * Ensure each imported bookmark has a unique id
     *
     * See https://github.com/shaarli/Shaarli/issues/351
     */
    public function testImportSameDate()
    {
        $files = file2array('same_date.htm');
        $this->assertStringMatchesFormat(
            'File same_date.htm (453 bytes) was successfully processed in %d seconds:'
            .' 3 bookmarks imported, 0 bookmarks overwritten, 0 bookmarks skipped.',
            $this->netscapeBookmarkUtils->import(array(), $files)
        );
        $this->assertEquals(3, $this->bookmarkService->count());
        $this->assertEquals(0, $this->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assertEquals(0, $this->bookmarkService->get(0)->getId());
        $this->assertEquals(1, $this->bookmarkService->get(1)->getId());
        $this->assertEquals(2, $this->bookmarkService->get(2)->getId());
    }

    public function testImportCreateUpdateHistory()
    {
        $post = [
            'privacy' => 'public',
            'overwrite' => 'true',
        ];
        $files = file2array('netscape_basic.htm');
        $this->netscapeBookmarkUtils->import($post, $files);
        $history = $this->history->getHistory();
        $this->assertEquals(1, count($history));
        $this->assertEquals(History::IMPORT, $history[0]['event']);
        $this->assertTrue(new DateTime('-5 seconds') < $history[0]['datetime']);

        // re-import as private, enable overwriting
        $this->netscapeBookmarkUtils->import($post, $files);
        $history = $this->history->getHistory();
        $this->assertEquals(2, count($history));
        $this->assertEquals(History::IMPORT, $history[0]['event']);
        $this->assertTrue(new DateTime('-5 seconds') < $history[0]['datetime']);
        $this->assertEquals(History::IMPORT, $history[1]['event']);
        $this->assertTrue(new DateTime('-5 seconds') < $history[1]['datetime']);
    }
}

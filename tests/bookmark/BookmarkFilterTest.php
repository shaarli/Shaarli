<?php

namespace Shaarli\Bookmark;

use malkusch\lock\mutex\NoMutex;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeBookmarkService;
use Shaarli\Tests\Utils\ReferenceLinkDB;

/**
 * Class BookmarkFilterTest.
 */
class BookmarkFilterTest extends TestCase
{
    /**
     * @var string Test datastore path.
     */
    protected static $testDatastore = 'sandbox/datastore.php';
    /**
     * @var BookmarkFilter instance.
     */
    protected static $linkFilter;

    /**
     * @var ReferenceLinkDB instance
     */
    protected static $refDB;

    /**
     * @var BookmarkFileService instance
     */
    protected static $bookmarkService;

    /** @var PluginManager */
    protected static $pluginManager;

    /**
     * Instantiate linkFilter with ReferenceLinkDB data.
     */
    public static function setUpBeforeClass(): void
    {

        $mutex = new NoMutex();
        $conf = new ConfigManager('tests/utils/config/configJson');
        $conf->set('resource.datastore', self::$testDatastore);
        static::$pluginManager = new PluginManager($conf);
        self::$refDB = new ReferenceLinkDB();
        self::$refDB->write(self::$testDatastore);
        $history = new History('sandbox/history.php');
        self::$bookmarkService = new FakeBookmarkService($conf, static::$pluginManager, $history, $mutex, true);
        self::$linkFilter = new BookmarkFilter(self::$bookmarkService->getBookmarks(), $conf, static::$pluginManager);
    }

    /**
     * Blank filter.
     */
    public function testFilter()
    {
        $this->assertEquals(
            self::$refDB->countLinks(),
            count(self::$linkFilter->filter('', ''))
        );

        $this->assertEquals(
            self::$refDB->countLinks(),
            count(self::$linkFilter->filter('', '', 'all'))
        );

        $this->assertEquals(
            self::$refDB->countLinks(),
            count(self::$linkFilter->filter('', '', 'randomstr'))
        );

        // Private only.
        $this->assertEquals(
            self::$refDB->countPrivateLinks(),
            count(self::$linkFilter->filter('', '', false, 'private'))
        );

        // Public only.
        $this->assertEquals(
            self::$refDB->countPublicLinks(),
            count(self::$linkFilter->filter('', '', false, 'public'))
        );

        $this->assertEquals(
            ReferenceLinkDB::$NB_LINKS_TOTAL,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, ''))
        );

        $this->assertEquals(
            self::$refDB->countUntaggedLinks(),
            count(
                self::$linkFilter->filter(
                    BookmarkFilter::$FILTER_TAG,
                    /*$request=*/
                    '',
                    /*$casesensitive=*/
                    false,
                    /*$visibility=*/
                    'all',
                    /*$untaggedonly=*/
                    true
                )
            )
        );

        $this->assertEquals(
            ReferenceLinkDB::$NB_LINKS_TOTAL,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, ''))
        );
    }

    /**
     * Filter bookmarks using a tag
     */
    public function testFilterOneTag()
    {
        $this->assertEquals(
            4,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web', false))
        );

        $this->assertEquals(
            4,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '+web', false))
        );

        $this->assertEquals(
            4,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web', false, 'all'))
        );

        $this->assertEquals(
            4,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web', false, 'default-blabla'))
        );

        // Private only.
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web', false, 'private'))
        );

        // Public only.
        $this->assertEquals(
            3,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'web', false, 'public'))
        );
    }

    /**
     * Filter bookmarks using a tag - case-sensitive
     */
    public function testFilterCaseSensitiveTag()
    {
        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'mercurial', true))
        );

        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'Mercurial', true))
        );
    }

    /**
     * Filter bookmarks using a tag combination
     */
    public function testFilterMultipleTags()
    {
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'dev cartoon', false))
        );
    }

    /**
     * Filter bookmarks using a non-existent tag
     */
    public function testFilterUnknownTag()
    {
        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'null', false))
        );
    }

    /**
     * Searching for the tag "0" must not be mistaken for an empty search
     */
    public function testFilterTagZeroIsNotAnEmptySearch()
    {
        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '0', false))
        );
        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
                ['0', ''],
                false
            ))
        );
    }

    /**
     * Retrieve a link entry with its hash
     */
    public function testFilterSmallHash()
    {
        $links = self::$linkFilter->filter(BookmarkFilter::$FILTER_HASH, 'IuWvgA');

        $this->assertEquals(
            1,
            count($links)
        );

        $this->assertEquals(
            'MediaGoblin',
            $links[7]->getTitle()
        );
    }

    /**
     * No link for this hash
     */
    public function testFilterUnknownSmallHash()
    {
        $this->expectException(\Shaarli\Bookmark\Exception\BookmarkNotFoundException::class);

        self::$linkFilter->filter(BookmarkFilter::$FILTER_HASH, 'Iblaah');
    }

    /**
     * Full-text search - no result found.
     */
    public function testFilterFullTextNoResult()
    {
        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'azertyuiop'))
        );
    }

    /**
     * Full-text search - result from a link's URL
     */
    public function testFilterFullTextURL()
    {
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'ars.userfriendly.org'))
        );

        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'ars org'))
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
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'userfriendly -'))
        );
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'UserFriendly -'))
        );
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'uSeRFrIendlY -'))
        );

        // use miscellaneous case and offset
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'RFrIendL'))
        );
    }

    /**
     * Full-text search - result from the link's description only
     */
    public function testFilterFullTextDescription()
    {
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'publishing media'))
        );

        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'mercurial w3c'))
        );

        $this->assertEquals(
            3,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, '"free software"'))
        );
    }

    /**
     * Full-text search - result from the link's tags only
     */
    public function testFilterFullTextTags()
    {
        $this->assertEquals(
            6,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'web'))
        );

        $this->assertEquals(
            6,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'web', 'all'))
        );

        $this->assertEquals(
            6,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'web', 'bla'))
        );

        // Private only.
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'web', false, 'private'))
        );

        // Public only.
        $this->assertEquals(
            5,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'web', false, 'public'))
        );
    }

    /**
     * Full-text search - result set from mixed sources
     */
    public function testFilterFullTextMixed()
    {
        $this->assertEquals(
            3,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'free software'))
        );
    }

    /**
     * Full-text search - test exclusion with '-'.
     */
    public function testExcludeSearch()
    {
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, 'free -gnu'))
        );

        $this->assertEquals(
            ReferenceLinkDB::$NB_LINKS_TOTAL - 1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TEXT, '-revolution'))
        );
    }

    /**
     * Full-text search - test AND, exact terms and exclusion combined, across fields.
     */
    public function testMultiSearch()
    {
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TEXT,
                '"Free Software " stallman "read this" @website stuff'
            ))
        );

        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TEXT,
                '"free software " stallman "read this" -beard @website stuff'
            ))
        );
    }

    /**
     * Full-text search - make sure that exact search won't work across fields.
     */
    public function testSearchExactTermMultiFieldsKo()
    {
        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TEXT,
                '"designer naming"'
            ))
        );

        $this->assertEquals(
            0,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TEXT,
                '"designernaming"'
            ))
        );
    }

    /**
     * Tag search with exclusion.
     */
    public function testTagFilterWithExclusion()
    {
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, 'gnu -free'))
        );

        $this->assertEquals(
            ReferenceLinkDB::$NB_LINKS_TOTAL - 1,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '-free'))
        );
    }

    /**
     * Test crossed search (terms + tags).
     */
    public function testFilterCrossedSearch()
    {
        $terms = '"Free Software " stallman "read this" @website stuff';
        $tags = 'free';
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
                [$tags, $terms]
            ))
        );
        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
                ['', $terms]
            ))
        );
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
                [false, 'PSR-2']
            ))
        );
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
                [$tags, '']
            ))
        );
        $this->assertEquals(
            ReferenceLinkDB::$NB_LINKS_TOTAL,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG | BookmarkFilter::$FILTER_TEXT,
                ''
            ))
        );
    }

    /**
     * Tag search with OR optional tags.
     */
    public function testTagFilterOr()
    {
        $this->assertEquals(
            5,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '~cartoon ~web'))
        );

        $this->assertEquals(
            6,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '~c*t*n ~st*'))
        );

        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '~cartoon ~web dev'))
        );

        $this->assertEquals(
            2,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '~cartoon ~web +dev'))
        );

        $this->assertEquals(
            4,
            count(self::$linkFilter->filter(BookmarkFilter::$FILTER_TAG, '~cartoon ~web -samba'))
        );
    }

    /**
     * Filter bookmarks by #hashtag.
     */
    public function testFilterByHashtag()
    {
        $hashtag = 'hashtag';
        $this->assertEquals(
            3,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG,
                $hashtag
            ))
        );

        $hashtag = 'private';
        $this->assertEquals(
            1,
            count(self::$linkFilter->filter(
                BookmarkFilter::$FILTER_TAG,
                $hashtag,
                false,
                'private'
            ))
        );
    }

    /**
     * Test search result highlights in every field of bookmark reference #9.
     */
    public function testFullTextSearchHighlight(): void
    {
        $bookmarks = self::$linkFilter->filter(
            BookmarkFilter::$FILTER_TEXT,
            '"psr-2" coding guide http fig "psr-2/" "This guide" basic standard. coding-style quality assurance'
        );

        static::assertCount(1, $bookmarks);
        static::assertArrayHasKey(9, $bookmarks);

        $bookmark = $bookmarks[9];
        $expectedHighlights = [
            'title' => [
                ['start' => 0, 'end' => 5], // "psr-2"
                ['start' => 7, 'end' => 13], // coding
                ['start' => 20, 'end' => 25], // guide
            ],
            'description' => [
                ['start' => 0, 'end' => 10], // "This guide"
                ['start' => 45, 'end' => 50], // basic
                ['start' => 58, 'end' => 67], // standard.
            ],
            'url' => [
                ['start' => 0, 'end' => 4], // http
                ['start' => 15, 'end' => 18], // fig
                ['start' => 27, 'end' => 33], // "psr-2/"
            ],
            'tags' => [
                ['start' => 0, 'end' => 12], // coding-style
                ['start' => 23, 'end' => 30], // quality
                ['start' => 31, 'end' => 40], // assurance
            ],
        ];
        static::assertSame($expectedHighlights, $bookmark->getAdditionalContentEntry('search_highlight'));
    }
}

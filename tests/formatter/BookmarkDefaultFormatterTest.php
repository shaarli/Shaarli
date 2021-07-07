<?php

namespace Shaarli\Formatter;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\TestCase;

/**
 * Class BookmarkDefaultFormatterTest
 * @package Shaarli\Formatter
 */
class BookmarkDefaultFormatterTest extends TestCase
{
    /** @var string Path of test config file */
    protected static $testConf = 'sandbox/config';

    /** @var BookmarkFormatter */
    protected $formatter;

    /** @var ConfigManager instance */
    protected $conf;

    /**
     * Initialize formatter instance.
     */
    protected function setUp(): void
    {
        copy('tests/utils/config/configJson.json.php', self::$testConf . '.json.php');
        $this->conf = new ConfigManager(self::$testConf);
        $this->formatter = new BookmarkDefaultFormatter($this->conf, true);
    }

    /**
     * Test formatting a bookmark with all its attribute filled.
     */
    public function testFormatFull()
    {
        $bookmark = new Bookmark();
        $bookmark->setId($id = 11);
        $bookmark->setShortUrl($short = 'abcdef');
        $bookmark->setUrl('https://sub.domain.tld?query=here&for=real#hash');
        $bookmark->setTitle($title = 'This is a <strong>bookmark</strong>');
        $bookmark->setDescription($desc = '<h2>Content</h2><p>`Here is some content</p>');
        $bookmark->setTags($tags = ['tag1', 'bookmark', 'other', '<script>alert("xss");</script>']);
        $bookmark->setThumbnail('http://domain2.tdl2/?type=img&name=file.png');
        $bookmark->setSticky(true);
        $bookmark->setCreated($created = DateTime::createFromFormat('Ymd_His', '20190521_190412'));
        $bookmark->setUpdated($updated = DateTime::createFromFormat('Ymd_His', '20190521_191213'));
        $bookmark->setPrivate(true);

        $link = $this->formatter->format($bookmark);
        $this->assertEquals($id, $link['id']);
        $this->assertEquals($short, $link['shorturl']);
        $this->assertEquals('https://sub.domain.tld?query=here&amp;for=real#hash', $link['url']);
        $this->assertEquals(
            'https://sub.domain.tld?query=here&amp;for=real#hash',
            $link['real_url']
        );
        $this->assertEquals('This is a &lt;strong&gt;bookmark&lt;/strong&gt;', $link['title']);
        $this->assertEquals(
            '&lt;h2&gt;Content&lt;/h2&gt;&lt;p&gt;`Here is some content&lt;/p&gt;',
            $link['description']
        );
        $tags[3] = '&lt;script&gt;alert(&quot;xss&quot;);&lt;/script&gt;';
        $this->assertEquals($tags, $link['taglist']);
        $this->assertEquals(implode(' ', $tags), $link['tags']);
        $this->assertEquals(
            'http://domain2.tdl2/?type=img&amp;name=file.png',
            $link['thumbnail']
        );
        $this->assertEquals($created, $link['created']);
        $this->assertEquals($created->getTimestamp(), $link['timestamp']);
        $this->assertEquals($updated, $link['updated']);
        $this->assertEquals($updated->getTimestamp(), $link['updated_timestamp']);
        $this->assertTrue($link['private']);
        $this->assertTrue($link['sticky']);
        $this->assertEquals('private', $link['class']);
    }

    /**
     * Test formatting a bookmark with all its attribute filled.
     */
    public function testFormatMinimal()
    {
        $bookmark = new Bookmark();

        $link = $this->formatter->format($bookmark);
        $this->assertEmpty($link['id']);
        $this->assertEmpty($link['shorturl']);
        $this->assertEmpty($link['url']);
        $this->assertEmpty($link['real_url']);
        $this->assertEmpty($link['title']);
        $this->assertEmpty($link['description']);
        $this->assertEmpty($link['taglist']);
        $this->assertEmpty($link['tags']);
        $this->assertEmpty($link['thumbnail']);
        $this->assertEmpty($link['created']);
        $this->assertEmpty($link['timestamp']);
        $this->assertEmpty($link['updated']);
        $this->assertEmpty($link['updated_timestamp']);
        $this->assertFalse($link['private']);
        $this->assertFalse($link['sticky']);
        $this->assertEmpty($link['class']);
    }

    /**
     * Make sure that the description is properly formatted by the default formatter.
     */
    public function testFormatDescription()
    {
        $description = [];
        $description[] = 'This a <strong>description</strong>' . PHP_EOL;
        $description[] = 'text https://sub.domain.tld?query=here&for=real#hash more text' . PHP_EOL;
        $description[] = 'Also, there is an #hashtag added' . PHP_EOL;
        $description[] = '    A  N  D KEEP     SPACES    !   ' . PHP_EOL;

        $bookmark = new Bookmark();
        $bookmark->setDescription(implode('', $description));
        $link = $this->formatter->format($bookmark);

        $description[0] = 'This a &lt;strong&gt;description&lt;/strong&gt;<br />';
        $url = 'https://sub.domain.tld?query=here&amp;for=real#hash';
        $description[1] = 'text <a href="' . $url . '">' . $url . '</a> more text<br />';
        $description[2] = 'Also, there is an <a href="./add-tag/hashtag" ' .
            'title="Hashtag hashtag">#hashtag</a> added<br />';
        $description[3] = '&nbsp; &nbsp; A &nbsp;N &nbsp;D KEEP &nbsp; &nbsp; ' .
            'SPACES &nbsp; &nbsp;! &nbsp; <br />';

        $this->assertEquals(implode(PHP_EOL, $description) . PHP_EOL, $link['description']);
    }

    /**
     * Test formatting URL with an index_url set
     * It should prepend relative links.
     */
    public function testFormatNoteWithIndexUrl()
    {
        $bookmark = new Bookmark();
        $bookmark->setUrl($short = '?abcdef');
        $description = 'Text #hashtag more text';
        $bookmark->setDescription($description);

        $this->formatter->addContextData('index_url', $root = 'https://domain.tld/hithere/');

        $link = $this->formatter->format($bookmark);
        $this->assertEquals($root . $short, $link['url']);
        $this->assertEquals($root . $short, $link['real_url']);
        $this->assertEquals(
            'Text <a href="' . $root . './add-tag/hashtag" title="Hashtag hashtag">' .
            '#hashtag</a> more text',
            $link['description']
        );
    }

    /**
     * Make sure that private tags are properly filtered out when the user is logged out.
     */
    public function testFormatTagListRemovePrivate(): void
    {
        $this->formatter = new BookmarkDefaultFormatter($this->conf, false);

        $bookmark = new Bookmark();
        $bookmark->setId($id = 11);
        $bookmark->setTags($tags = ['bookmark', '.private', 'othertag']);

        $link = $this->formatter->format($bookmark);

        unset($tags[1]);
        $tags = array_values($tags);

        $this->assertSame(11, $link['id']);
        $this->assertSame($tags, $link['taglist']);
        $this->assertSame(implode(' ', $tags), $link['tags']);
    }

    /**
     * Test formatTitleHtml with search result highlight.
     */
    public function testFormatTitleHtmlWithSearchHighlight(): void
    {
        $this->formatter = new BookmarkDefaultFormatter($this->conf, false);

        $bookmark = new Bookmark();
        $bookmark->setTitle('PSR-2: Coding Style Guide');
        $bookmark->setAdditionalContentEntry(
            'search_highlight',
            ['title' => [
                ['start' => 0, 'end' => 5], // "psr-2"
                ['start' => 7, 'end' => 13], // coding
                ['start' => 20, 'end' => 25], // guide
            ]]
        );

        $link = $this->formatter->format($bookmark);

        $this->assertSame(
            '<span class="search-highlight">PSR-2</span>: ' .
            '<span class="search-highlight">Coding</span> Style ' .
            '<span class="search-highlight">Guide</span>',
            $link['title_html']
        );
    }

    /**
     * Test formatDescription with search result highlight.
     */
    public function testFormatDescriptionWithSearchHighlight(): void
    {
        $this->formatter = new BookmarkDefaultFormatter($this->conf, false);

        $bookmark = new Bookmark();
        $bookmark->setDescription(
            'This guide extends and expands on PSR-1, the basic coding standard.' . PHP_EOL .
            'https://www.php-fig.org/psr/psr-1/'
        );
        $bookmark->setAdditionalContentEntry(
            'search_highlight',
            ['description' => [
                ['start' => 0, 'end' => 10], // "This guide"
                ['start' => 45, 'end' => 50], // basic
                ['start' => 58, 'end' => 67], // standard.
                ['start' => 84, 'end' => 87], // fig
            ]]
        );

        $link = $this->formatter->format($bookmark);

        $this->assertSame(
            '<span class="search-highlight">This guide</span> extends and expands on PSR-1, the ' .
            '<span class="search-highlight">basic</span> coding ' .
            '<span class="search-highlight">standard.</span><br />' . PHP_EOL .
            '<a href="https://www.php-fig.org/psr/psr-1/">' .
                'https://www.php-<span class="search-highlight">fig</span>.org/psr/psr-1/' .
            '</a>',
            $link['description']
        );
    }

    /**
     * Test formatUrlHtml with search result highlight.
     */
    public function testFormatUrlHtmlWithSearchHighlight(): void
    {
        $this->formatter = new BookmarkDefaultFormatter($this->conf, false);

        $bookmark = new Bookmark();
        $bookmark->setUrl('http://www.php-fig.org/psr/psr-2/');
        $bookmark->setAdditionalContentEntry(
            'search_highlight',
            ['url' => [
                ['start' => 0, 'end' => 4], // http
                ['start' => 15, 'end' => 18], // fig
                ['start' => 27, 'end' => 33], // "psr-2/"
            ]]
        );

        $link = $this->formatter->format($bookmark);

        $this->assertSame(
            '<span class="search-highlight">http</span>://www.php-' .
            '<span class="search-highlight">fig</span>.org/psr/' .
            '<span class="search-highlight">psr-2/</span>',
            $link['url_html']
        );
    }

    /**
     * Test formatTagListHtml with search result highlight.
     */
    public function testFormatTagListHtmlWithSearchHighlight(): void
    {
        $this->formatter = new BookmarkDefaultFormatter($this->conf, false);

        $bookmark = new Bookmark();
        $bookmark->setTagsString('coding-style standards quality assurance');
        $bookmark->setAdditionalContentEntry(
            'search_highlight',
            ['tags' => [
                ['start' => 0, 'end' => 12], // coding-style
                ['start' => 23, 'end' => 30], // quality
                ['start' => 31, 'end' => 40], // assurance
            ],]
        );

        $link = $this->formatter->format($bookmark);

        $this->assertSame(
            [
                '<span class="search-highlight">coding-style</span>',
                'standards',
                '<span class="search-highlight">quality</span>',
                '<span class="search-highlight">assurance</span>',
            ],
            $link['taglist_html']
        );
    }

    /**
     * Test default formatting with formatter_settings.autolink set to false:
     *   URLs and hashtags should not be transformed
     */
    public function testFormatDescriptionWithoutLinkification(): void
    {
        $this->conf->set('formatter_settings.autolink', false);
        $this->formatter = new BookmarkDefaultFormatter($this->conf, false);

        $bookmark = new Bookmark();
        $bookmark->setDescription('Hi!' . PHP_EOL . 'https://thisisaurl.tld  #hashtag');

        $link = $this->formatter->format($bookmark);

        static::assertSame(
            'Hi!<br />' . PHP_EOL . 'https://thisisaurl.tld &nbsp;#hashtag',
            $link['description']
        );
    }
}

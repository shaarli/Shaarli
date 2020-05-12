<?php

namespace Shaarli\Formatter;

use DateTime;
use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;

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
    public function setUp()
    {
        copy('tests/utils/config/configJson.json.php', self::$testConf .'.json.php');
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
        $description[] = 'text https://sub.domain.tld?query=here&for=real#hash more text'. PHP_EOL;
        $description[] = 'Also, there is an #hashtag added'. PHP_EOL;
        $description[] = '    A  N  D KEEP     SPACES    !   '. PHP_EOL;

        $bookmark = new Bookmark();
        $bookmark->setDescription(implode('', $description));
        $link = $this->formatter->format($bookmark);

        $description[0] = 'This a &lt;strong&gt;description&lt;/strong&gt;<br />';
        $url = 'https://sub.domain.tld?query=here&amp;for=real#hash';
        $description[1] = 'text <a href="'. $url .'">'. $url .'</a> more text<br />';
        $description[2] = 'Also, there is an <a href="./add-tag/hashtag" '.
            'title="Hashtag hashtag">#hashtag</a> added<br />';
        $description[3] = '&nbsp; &nbsp; A &nbsp;N &nbsp;D KEEP &nbsp; &nbsp; '.
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
            'Text <a href="'. $root .'./add-tag/hashtag" title="Hashtag hashtag">'.
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
}

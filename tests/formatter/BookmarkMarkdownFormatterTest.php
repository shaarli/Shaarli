<?php

namespace Shaarli\Formatter;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\TestCase;

/**
 * Class BookmarkMarkdownFormatterTest
 * @package Shaarli\Formatter
 */
class BookmarkMarkdownFormatterTest extends TestCase
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
        $this->formatter = new BookmarkMarkdownFormatter($this->conf, true);
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
        $bookmark->setDescription('<h2>Content</h2><p>`Here is some content</p>');
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
            '<div class="markdown"><p>' .
                '&lt;h2&gt;Content&lt;/h2&gt;&lt;p&gt;`Here is some content&lt;/p&gt;' .
            '</p></div>',
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
        $description = 'This a <strong>description</strong>' . PHP_EOL;
        $description .= 'text https://sub.domain.tld?query=here&for=real#hash more text' . PHP_EOL;
        $description .= 'Also, there is an #hashtag added' . PHP_EOL;
        $description .= '    A  N  D KEEP     SPACES    !   ' . PHP_EOL;

        $bookmark = new Bookmark();
        $bookmark->setDescription($description);
        $link = $this->formatter->format($bookmark);

        $description = '<div class="markdown"><p>';
        $description .= 'This a &lt;strong&gt;description&lt;/strong&gt;<br />' . PHP_EOL;
        $url = 'https://sub.domain.tld?query=here&amp;for=real#hash';
        $description .= 'text <a href="' . $url . '">' . $url . '</a> more text<br />' . PHP_EOL;
        $description .= 'Also, there is an <a href="./add-tag/hashtag">#hashtag</a> added<br />' . PHP_EOL;
        $description .= 'A  N  D KEEP     SPACES    !   ';
        $description .= '</p></div>';

        $this->assertEquals($description, $link['description']);
    }

    /**
     * Make sure that the description is properly formatted by the default formatter.
     */
    public function testFormatDescriptionWithSearchHighlight()
    {
        $description = 'This a <strong>description</strong>' . PHP_EOL;
        $description .= 'text https://sub.domain.tld?query=here&for=real#hash more text' . PHP_EOL;
        $description .= 'Also, there is an #hashtag added' . PHP_EOL;
        $description .= '    A  N  D KEEP     SPACES    !   ' . PHP_EOL;
        $description .= 'And [yet another link](https://other.domain.tld)' . PHP_EOL;

        $bookmark = new Bookmark();
        $bookmark->setDescription($description);
        $bookmark->setAdditionalContentEntry(
            'search_highlight',
            ['description' => [
                ['start' => 18, 'end' => 26], // cription
                ['start' => 49, 'end' => 52], // sub
                ['start' => 84, 'end' => 88], // hash
                ['start' => 118, 'end' => 123], // hasht
                ['start' => 203, 'end' => 215], // other.domain
            ]]
        );

        $link = $this->formatter->format($bookmark);

        $description = '<div class="markdown"><p>';
        $description .= 'This a &lt;strong&gt;des<span class="search-highlight">cription</span>&lt;/strong&gt;<br />' .
            PHP_EOL;
        $url = 'https://sub.domain.tld?query=here&amp;for=real#hash';
        $highlighted = 'https://<span class="search-highlight">sub</span>.domain.tld';
        $highlighted .= '?query=here&amp;for=real#<span class="search-highlight">hash</span>';
        $description .= 'text <a href="' . $url . '">' . $highlighted . '</a> more text<br />' . PHP_EOL;
        $description .= 'Also, there is an <a href="./add-tag/hashtag">#<span class="search-highlight">hasht</span>' .
            'ag</a> added<br />' . PHP_EOL;
        $description .= 'A  N  D KEEP     SPACES    !<br />' . PHP_EOL;
        $description .= 'And <a href="https://other.domain.tld">' .
            '<span class="search-highlight">yet another link</span></a>';
        $description .= '</p></div>';

        $this->assertEquals($description, $link['description']);
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

        $description = '<div class="markdown"><p>';
        $description .= 'Text <a href="' . $root . './add-tag/hashtag">#hashtag</a> more text';
        $description .= '</p></div>';

        $link = $this->formatter->format($bookmark);
        $this->assertEquals($root . $short, $link['url']);
        $this->assertEquals($root . $short, $link['real_url']);
        $this->assertEquals(
            $description,
            $link['description']
        );
    }
}

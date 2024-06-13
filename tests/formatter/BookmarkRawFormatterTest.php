<?php

namespace Shaarli\Formatter;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\TestCase;

/**
 * Class BookmarkRawFormatterTest
 * @package Shaarli\Formatter
 */
class BookmarkRawFormatterTest extends TestCase
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
        copy(__DIR__ . '/../utils/config/configJson.json.php', self::$testConf . '.json.php');
        $this->conf = new ConfigManager(self::$testConf);
        $this->formatter = new BookmarkRawFormatter($this->conf, true);
    }

    /**
     * Test formatting a bookmark with all its attribute filled.
     */
    public function testFormatFull()
    {
        $bookmark = new Bookmark();
        $bookmark->setId($id = 11);
        $bookmark->setShortUrl($short = 'abcdef');
        $bookmark->setUrl($url = 'https://sub.domain.tld?query=here&for=real#hash');
        $bookmark->setTitle($title = 'This is a <strong>bookmark</strong>');
        $bookmark->setDescription($desc = '<h2>Content</h2><p>`Here is some content</p>');
        $bookmark->setTags($tags = ['tag1', 'bookmark', 'other', '<script>alert("xss");</script>']);
        $bookmark->setThumbnail($thumb = 'http://domain2.tdl2/file.png');
        $bookmark->setSticky(true);
        $bookmark->setCreated($created = DateTime::createFromFormat('Ymd_His', '20190521_190412'));
        $bookmark->setUpdated($updated = DateTime::createFromFormat('Ymd_His', '20190521_191213'));
        $bookmark->setPrivate(true);

        $link = $this->formatter->format($bookmark);
        $this->assertEquals($id, $link['id']);
        $this->assertEquals($short, $link['shorturl']);
        $this->assertEquals($url, $link['url']);
        $this->assertEquals($url, $link['real_url']);
        $this->assertEquals($title, $link['title']);
        $this->assertEquals($desc, $link['description']);
        $this->assertEquals($tags, $link['taglist']);
        $this->assertEquals(implode(' ', $tags), $link['tags']);
        $this->assertEquals($thumb, $link['thumbnail']);
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
}

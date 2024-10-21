<?php

namespace Shaarli\Bookmark;

use Shaarli\Bookmark\Exception\InvalidBookmarkException;
use Shaarli\TestCase;

/**
 * Class BookmarkTest
 */
class BookmarkTest extends TestCase
{
    /**
     * Test fromArray() with a link with full data
     */
    public function testFromArrayFull()
    {
        $data = [
            'id' => 1,
            'shorturl' => 'abc',
            'url' => 'https://domain.tld/oof.html?param=value#anchor',
            'title' => 'This is an array link',
            'description' => 'HTML desc<br><p>hi!</p>',
            'thumbnail' => 'https://domain.tld/pic.png',
            'sticky' => true,
            'created' => new \DateTime('-1 minute'),
            'tags' => ['tag1', 'tag2', 'chair'],
            'updated' => new \DateTime(),
            'private' => true,
        ];

        $bookmark = (new Bookmark())->fromArray($data);
        $this->assertEquals($data['id'], $bookmark->getId());
        $this->assertEquals($data['shorturl'], $bookmark->getShortUrl());
        $this->assertEquals($data['url'], $bookmark->getUrl());
        $this->assertEquals($data['title'], $bookmark->getTitle());
        $this->assertEquals($data['description'], $bookmark->getDescription());
        $this->assertEquals($data['thumbnail'], $bookmark->getThumbnail());
        $this->assertEquals($data['sticky'], $bookmark->isSticky());
        $this->assertEquals($data['created'], $bookmark->getCreated());
        $this->assertEquals($data['tags'], $bookmark->getTags());
        $this->assertEquals('tag1 tag2 chair', $bookmark->getTagsString());
        $this->assertEquals($data['updated'], $bookmark->getUpdated());
        $this->assertEquals($data['private'], $bookmark->isPrivate());
        $this->assertFalse($bookmark->isNote());
    }

    /**
     * Test fromArray() with a link with minimal data.
     * Note that I use null values everywhere but this should not happen in the real world.
     */
    public function testFromArrayMinimal()
    {
        $data = [
            'id' => null,
            'shorturl' => null,
            'url' => null,
            'title' => null,
            'description' => null,
            'created' => null,
            'tags' => null,
            'private' => null,
        ];

        $bookmark = (new Bookmark())->fromArray($data);
        $this->assertNull($bookmark->getId());
        $this->assertNull($bookmark->getShortUrl());
        $this->assertNull($bookmark->getUrl());
        $this->assertNull($bookmark->getTitle());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertNull($bookmark->getCreated());
        $this->assertEquals([], $bookmark->getTags());
        $this->assertEquals('', $bookmark->getTagsString());
        $this->assertNull($bookmark->getUpdated());
        $this->assertFalse($bookmark->getThumbnail());
        $this->assertFalse($bookmark->isSticky());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertTrue($bookmark->isNote());
    }

    /**
     * Test fromArray() with a link with a custom tags separator
     */
    public function testFromArrayCustomTagsSeparator()
    {
        $data = [
            'id' => 1,
            'tags' => ['tag1', 'tag2', 'chair'],
        ];

        $bookmark = (new Bookmark())->fromArray($data, '@');
        $this->assertEquals($data['id'], $bookmark->getId());
        $this->assertEquals($data['tags'], $bookmark->getTags());
        $this->assertEquals('tag1@tag2@chair', $bookmark->getTagsString('@'));
    }


    /**
     * Test validate() with a valid minimal bookmark
     */
    public function testValidateValidFullBookmark()
    {
        $bookmark = new Bookmark();
        $bookmark->setId(2);
        $bookmark->setShortUrl('abc');
        $bookmark->setCreated($date = \DateTime::createFromFormat('Ymd_His', '20190514_200102'));
        $bookmark->setUpdated($dateUp = \DateTime::createFromFormat('Ymd_His', '20190514_210203'));
        $bookmark->setUrl($url = 'https://domain.tld/oof.html?param=value#anchor');
        $bookmark->setTitle($title = 'This is an array link');
        $bookmark->setDescription($desc = 'HTML desc<br><p>hi!</p>');
        $bookmark->setTags($tags = ['tag1', 'tag2', 'chair']);
        $bookmark->setThumbnail($thumb = 'https://domain.tld/pic.png');
        $bookmark->setPrivate(true);
        $bookmark->validate();

        $this->assertEquals(2, $bookmark->getId());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($date, $bookmark->getCreated());
        $this->assertEquals($dateUp, $bookmark->getUpdated());
        $this->assertEquals($url, $bookmark->getUrl());
        $this->assertEquals($title, $bookmark->getTitle());
        $this->assertEquals($desc, $bookmark->getDescription());
        $this->assertEquals($tags, $bookmark->getTags());
        $this->assertEquals(implode(' ', $tags), $bookmark->getTagsString());
        $this->assertEquals($thumb, $bookmark->getThumbnail());
        $this->assertTrue($bookmark->isPrivate());
        $this->assertFalse($bookmark->isNote());
    }

    /**
     * Test validate() with a valid minimal bookmark
     */
    public function testValidateValidMinimalBookmark()
    {
        $bookmark = new Bookmark();
        $bookmark->setId(1);
        $bookmark->setShortUrl('abc');
        $bookmark->setCreated($date = \DateTime::createFromFormat('Ymd_His', '20190514_200102'));
        $bookmark->validate();

        $this->assertEquals(1, $bookmark->getId());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($date, $bookmark->getCreated());
        $this->assertEquals('/shaare/abc', $bookmark->getUrl());
        $this->assertEquals('/shaare/abc', $bookmark->getTitle());
        $this->assertEquals('', $bookmark->getDescription());
        $this->assertEquals([], $bookmark->getTags());
        $this->assertEquals('', $bookmark->getTagsString());
        $this->assertFalse($bookmark->getThumbnail());
        $this->assertFalse($bookmark->isPrivate());
        $this->assertTrue($bookmark->isNote());
        $this->assertNull($bookmark->getUpdated());
    }

    /**
     * Test validate() with a a bookmark without ID.
     */
    public function testValidateNotValidNoId()
    {
        $bookmark = new Bookmark();
        $bookmark->setShortUrl('abc');
        $bookmark->setCreated(\DateTime::createFromFormat('Ymd_His', '20190514_200102'));
        $exception = null;
        try {
            $bookmark->validate();
        } catch (InvalidBookmarkException $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertContainsPolyfill('- ID: ' . PHP_EOL, $exception->getMessage());
    }

    /**
     * Test validate() with a a bookmark without short url.
     */
    public function testValidateNotValidNoShortUrl()
    {
        $bookmark = new Bookmark();
        $bookmark->setId(1);
        $bookmark->setCreated(\DateTime::createFromFormat('Ymd_His', '20190514_200102'));
        $bookmark->setShortUrl(null);
        $exception = null;
        try {
            $bookmark->validate();
        } catch (InvalidBookmarkException $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertContainsPolyfill('- ShortUrl: ' . PHP_EOL, $exception->getMessage());
    }

    /**
     * Test validate() with a a bookmark without created datetime.
     */
    public function testValidateNotValidNoCreated()
    {
        $bookmark = new Bookmark();
        $bookmark->setId(1);
        $bookmark->setShortUrl('abc');
        $bookmark->setCreated(null);
        $exception = null;
        try {
            $bookmark->validate();
        } catch (InvalidBookmarkException $e) {
            $exception = $e;
        }
        $this->assertNotNull($exception);
        $this->assertContainsPolyfill('- Created: ' . PHP_EOL, $exception->getMessage());
    }

    /**
     * Test setId() and make sure that default fields are generated.
     */
    public function testSetIdEmptyGeneratedFields()
    {
        $bookmark = new Bookmark();
        $bookmark->setId(2);

        $this->assertEquals(2, $bookmark->getId());
        $this->assertRegExp('/[\w\-]{6}/', $bookmark->getShortUrl());
        $this->assertTrue(new \DateTime('5 seconds ago') < $bookmark->getCreated());
    }

    /**
     * Test setId() and with generated fields already set.
     */
    public function testSetIdSetGeneratedFields()
    {
        $bookmark = new Bookmark();
        $bookmark->setShortUrl('abc');
        $bookmark->setCreated($date = \DateTime::createFromFormat('Ymd_His', '20190514_200102'));
        $bookmark->setId(2);

        $this->assertEquals(2, $bookmark->getId());
        $this->assertEquals('abc', $bookmark->getShortUrl());
        $this->assertEquals($date, $bookmark->getCreated());
    }

    /**
     * Test setUrl() and make sure it accepts custom protocols
     */
    public function testGetUrlWithValidProtocols()
    {
        $bookmark = new Bookmark();
        $bookmark->setUrl($url = 'myprotocol://helloworld', ['myprotocol']);
        $this->assertEquals($url, $bookmark->getUrl());

        $bookmark->setUrl($url = 'https://helloworld.tld', ['myprotocol']);
        $this->assertEquals($url, $bookmark->getUrl());
    }

    /**
     * Test setUrl() and make sure it accepts custom protocols
     */
    public function testGetUrlWithNotValidProtocols()
    {
        $bookmark = new Bookmark();
        $bookmark->setUrl('myprotocol://helloworld', []);
        $this->assertEquals('http://helloworld', $bookmark->getUrl());

        $bookmark->setUrl($url = 'https://helloworld.tld', []);
        $this->assertEquals($url, $bookmark->getUrl());
    }

    /**
     * Test addTag() and DeleteTag()
     */

    public function testAddDeleteTags()
    {
        $bookmark = new Bookmark();

        $bookmark->addTag('tag1');
        $this->assertEquals(
            [
                'tag1',
            ],
            $bookmark->getTags()
        );

        // Ignore if tag is already present
        $bookmark->addTag('tag2');
        $bookmark->addTag('tag1');
        $this->assertEquals(
            [
                'tag1',
                'tag2',
            ],
            $bookmark->getTags()
        );

        // Ignore deleting tags not present
        $bookmark->deleteTag('tag5');
        $this->assertEquals(
            [
                'tag1',
                'tag2',
            ],
            $bookmark->getTags()
        );

        // Delete multiples
        $bookmark->setTags(['tag3', 'tag1', 'tag4', 'tag3', 'tag3', 'tag4']);
        $bookmark->deleteTag('tag3');
        $this->assertEquals(
            [
                'tag1',
                'tag4',
                'tag4',
            ],
            $bookmark->getTags()
        );
        $bookmark->deleteTag('tag4');
        $this->assertEquals(
            [
                'tag1',
            ],
            $bookmark->getTags()
        );
    }

    /**
     * Test setTagsString() with exotic data
     */
    public function testSetTagsString()
    {
        $bookmark = new Bookmark();

        $str = 'tag1    tag2 tag3.tag3-2 tag4     -tag5   ';
        $bookmark->setTagsString($str);
        $this->assertEquals(
            [
                'tag1',
                'tag2',
                'tag3.tag3-2',
                'tag4',
                'tag5',
            ],
            $bookmark->getTags()
        );
    }

    /**
     * Test setTags() with exotic data
     */
    public function testSetTags()
    {
        $bookmark = new Bookmark();

        $array = [
            'tag1    ',
            '     tag2',
            'tag3.tag3-2',
            '  tag4',
            '  ',
            '-tag5   ',
        ];
        $bookmark->setTags($array);
        $this->assertEquals(
            [
                'tag1',
                'tag2',
                'tag3.tag3-2',
                'tag4',
                'tag5',
            ],
            $bookmark->getTags()
        );
    }

    /**
     * Test renameTag()
     */
    public function testRenameTag()
    {
        $bookmark = new Bookmark();
        $bookmark->renameTag('chair', 'table');
        $this->assertEquals([], $bookmark->getTags());

        $bookmark->setTags(['tag1', 'tag2', 'chair']);
        $bookmark->renameTag('chair', 'table');
        $this->assertEquals(['tag1', 'tag2', 'table'], $bookmark->getTags());
        $bookmark->renameTag('tag1', 'tag42');
        $this->assertEquals(['tag42', 'tag2', 'table'], $bookmark->getTags());
        $bookmark->renameTag('tag42', 'tag43');
        $this->assertEquals(['tag43', 'tag2', 'table'], $bookmark->getTags());
        $bookmark->renameTag('table', 'desk');
        $this->assertEquals(['tag43', 'tag2', 'desk'], $bookmark->getTags());
    }

    /**
     * Test renameTag() with a tag that is not present in the bookmark
     */
    public function testRenameTagNotExists()
    {
        $bookmark = new Bookmark();
        $bookmark->setTags(['tag1', 'tag2', 'chair']);
        $bookmark->renameTag('nope', 'table');
        $this->assertEquals(['tag1', 'tag2', 'chair'], $bookmark->getTags());
    }

    /**
     * Test renameTag() avoid duplicating existing tag
     */
    public function testRenameTagNotDuplicated()
    {
        $bookmark = new Bookmark();
        $bookmark->setTags(['tag1', 'tag2', 'chair']);
        $bookmark->renameTag('tag1', 'chair');
        $this->assertEquals(['tag2', 'chair'], $bookmark->getTags());
    }

    /**
     * Test deleteTag()
     */
    public function testDeleteTag()
    {
        $bookmark = new Bookmark();
        $bookmark->setTags(['tag1', 'tag2', 'chair']);
        $bookmark->deleteTag('chair');
        $this->assertEquals(['tag1', 'tag2'], $bookmark->getTags());
        $bookmark->deleteTag('tag1');
        $this->assertEquals(['tag2'], $bookmark->getTags());
        $bookmark->deleteTag('tag2');
        $this->assertEquals([], $bookmark->getTags());
    }

    /**
     * Test deleteTag() with a tag that is not present in the bookmark
     */
    public function testDeleteTagNotExists()
    {
        $bookmark = new Bookmark();
        $bookmark->setTags(['tag1', 'tag2', 'chair']);
        $bookmark->deleteTag('nope');
        $this->assertEquals(['tag1', 'tag2', 'chair'], $bookmark->getTags());
    }

    /**
     * Test shouldUpdateThumbnail() with bookmarks needing an update.
     */
    public function testShouldUpdateThumbnail(): void
    {
        $bookmark = (new Bookmark())->setUrl('http://domain.tld/with-image');

        static::assertTrue($bookmark->shouldUpdateThumbnail());

        $bookmark = (new Bookmark())
            ->setUrl('http://domain.tld/with-image')
            ->setThumbnail('unknown file')
        ;

        static::assertTrue($bookmark->shouldUpdateThumbnail());
    }

    /**
     * Test shouldUpdateThumbnail() with bookmarks that should not update.
     */
    public function testShouldNotUpdateThumbnail(): void
    {
        $bookmark = (new Bookmark());

        static::assertFalse($bookmark->shouldUpdateThumbnail());

        $bookmark = (new Bookmark())
            ->setUrl('ftp://domain.tld/other-protocol', ['ftp'])
        ;

        static::assertFalse($bookmark->shouldUpdateThumbnail());

        $bookmark = (new Bookmark())
            ->setUrl('http://domain.tld/with-image')
            ->setThumbnail(__FILE__)
        ;

        static::assertFalse($bookmark->shouldUpdateThumbnail());

        $bookmark = (new Bookmark())->setUrl('/shaare/abcdef');

        static::assertFalse($bookmark->shouldUpdateThumbnail());
    }
}

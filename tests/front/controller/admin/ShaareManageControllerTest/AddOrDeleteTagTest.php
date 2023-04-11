<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaareManageControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaareManageController;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class AddOrDeleteTagTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaareManageController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->set('httpAccess', $this->createMock(HttpAccess::class));
        $this->controller = new ShaareManageController($this->container);
    }

    /**
     * Add 1 tag to 1 bookmark
     */
    public function testAddOneTagOnOneBookmark(): void
    {
        $parameters = ['id' => '123', 'tag' => 'newtag', 'action' => 'add'];

        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);

        $response = new SlimResponse();
        $bookmark = (new Bookmark())
            ->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setTagsString('first second');

        static::assertSame(['first', 'second'], $bookmark->getTags());

        $this->container->get('bookmarkService')->expects(static::once())->method('get')->with(123)
            ->willReturn($bookmark);
        $this->container->get('bookmarkService')->expects(static::once())->method('set')->with($bookmark, false);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function () use ($bookmark): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->get('conf'), true);
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(['first', 'second', 'newtag'], $bookmark->getTags());

        static::assertSame(302, $result->getStatusCode());
        $a = $result->getHeader('location');
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Add 2 tags to 2 bookmarks
     */
    public function testAddTwoTagsOnTwoBookmarks(): void
    {
        $parameters = ['id' => '123 456', 'tag' => 'newtag@othertag', 'action' => 'add'];

        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);

        $response = new SlimResponse();
        $bookmark1 = (new Bookmark())
            ->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setTagsString('first second');
        $bookmark2 = (new Bookmark())
            ->setId(456)->setUrl('http://domain.tld')->setTitle('Title 123');

        static::assertSame(['first', 'second'], $bookmark1->getTags());
        static::assertSame([], $bookmark2->getTags());

        $this->container->get('bookmarkService')->expects(static::exactly(2))->method('get')
            ->withConsecutive([123], [456])
            ->willReturnOnConsecutiveCalls($bookmark1, $bookmark2);
        $this->container->get('bookmarkService')->expects(static::exactly(2))->method('set')
            ->withConsecutive([$bookmark1, false], [$bookmark2, false]);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function (): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->get('conf'), true);
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::exactly(2))
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(['first', 'second', 'newtag', 'othertag'], $bookmark1->getTags());
        static::assertSame(['newtag', 'othertag'], $bookmark2->getTags());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete 1 tag to 1 bookmark
     */
    public function testDeleteOneTagOnOneBookmark(): void
    {
        $parameters = ['id' => '123', 'tag' => 'second', 'action' => 'delete'];

        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();
        $bookmark = (new Bookmark())
            ->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setTagsString('first second third');

        static::assertSame(['first', 'second', 'third'], $bookmark->getTags());

        $this->container->get('bookmarkService')->expects(static::once())->method('get')->with(123)
            ->willReturn($bookmark);
        $this->container->get('bookmarkService')->expects(static::once())->method('set')->with($bookmark, false);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function () use ($bookmark): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->get('conf'), true);
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(['first', 'third'], $bookmark->getTags());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete 2 tags to 2 bookmarks
     */
    public function testDeleteTwoTagOnTwoBookmarks(): void
    {
        $parameters = ['id' => '123 456', 'tag' => 'second@first', 'action' => 'delete'];

        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();
        $bookmark1 = (new Bookmark())
            ->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setTagsString('first second third other');
        $bookmark2 = (new Bookmark())
            ->setId(456)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setTagsString('first second');

        static::assertSame(['first', 'second', 'third', 'other'], $bookmark1->getTags());
        static::assertSame(['first', 'second'], $bookmark2->getTags());

        $this->container->get('bookmarkService')->expects(static::exactly(2))->method('get')
            ->withConsecutive([123], [456])
            ->willReturnOnConsecutiveCalls($bookmark1, $bookmark2);
        $this->container->get('bookmarkService')->expects(static::exactly(2))->method('set')
            ->withConsecutive([$bookmark1, false], [$bookmark2, false]);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function (): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->get('conf'), true);
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::exactly(2))
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(['third', 'other'], $bookmark1->getTags());
        static::assertSame([], $bookmark2->getTags());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test add a tag without passing an ID.
     */
    public function testAddTagWithoutId(): void
    {
        $parameters = ['tag' => 'newtag', 'action' => 'add'];
        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid bookmark ID provided.'])
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test add a tag without passing an ID.
     */
    public function testDeleteTagWithoutId(): void
    {
        $parameters = ['tag' => 'newtag', 'action' => 'delete'];
        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid bookmark ID provided.'])
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test add a tag without passing an action.
     */
    public function testAddTagWithoutAction(): void
    {
        $parameters = ['id' => '123', 'tag' => 'newtag'];
        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid action provided.'])
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test add a tag without passing a tag string value.
     */
    public function testAddTagWithoutValue(): void
    {
        $parameters = ['id' => '123', 'tag' => '', 'action' => 'add'];
        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid tag name provided.'])
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test delete a tag without passing a tag string value.
     */
    public function testDeleteTagWithoutValue(): void
    {
        $parameters = ['id' => '123', 'tag' => '', 'action' => 'delete'];
        $request = (new FakeRequest(
            'POST',
            new Uri('', '', 80, '')
        ))->withParsedBody($parameters);
        $response = new SlimResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid tag name provided.'])
        ;

        $result = $this->controller->addOrDeleteTags($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaareManageControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaareManageController;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class ChangeVisibilityBookmarkTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaareManageController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->container->set('httpAccess', $this->createMock(HttpAccess::class));
        $this->controller = new ShaareManageController($this->container);
    }

    /**
     * Change bookmark visibility - Set private - Single public bookmark with valid parameters
     */
    public function testSetSingleBookmarkPrivate(): void
    {
        $parameters = ['id' => '123', 'newVisibility' => 'private'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $bookmark = (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setPrivate(false);

        static::assertFalse($bookmark->isPrivate());

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

        $result = $this->controller->changeVisibility($request, $response);

        static::assertTrue($bookmark->isPrivate());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Set public - Single private bookmark with valid parameters
     */
    public function testSetSingleBookmarkPublic(): void
    {
        $parameters = ['id' => '123', 'newVisibility' => 'public'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $bookmark = (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')->setPrivate(true);

        static::assertTrue($bookmark->isPrivate());

        $this->container->get('bookmarkService')->expects(static::once())->method('get')->with(123)
            ->willReturn($bookmark);
        $this->container->get('bookmarkService')->expects(static::once())->method('set')->with($bookmark, false);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturn(new BookmarkRawFormatter($this->container->get('conf'), true))
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertFalse($bookmark->isPrivate());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Set private on single already private bookmark
     */
    public function testSetSinglePrivateBookmarkPrivate(): void
    {
        $parameters = ['id' => '123', 'newVisibility' => 'private'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query)


        ;
        $response = $this->responseFactory->createResponse();

        $bookmark = (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
            ->setPrivate(true);

        static::assertTrue($bookmark->isPrivate());

        $this->container->get('bookmarkService')->expects(static::once())->method('get')->with(123)
            ->willReturn($bookmark);
        $this->container->get('bookmarkService')->expects(static::once())->method('set')->with($bookmark, false);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturn(new BookmarkRawFormatter($this->container->get('conf'), true))
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertTrue($bookmark->isPrivate());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Set multiple bookmarks private
     */
    public function testSetMultipleBookmarksPrivate(): void
    {
        $parameters = ['id' => '123 456 789', 'newVisibility' => 'private'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $bookmarks = [
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')->setPrivate(false),
            (new Bookmark())->setId(456)->setUrl('http://domain.tld')->setTitle('Title 456')->setPrivate(true),
            (new Bookmark())->setId(789)->setUrl('http://domain.tld')->setTitle('Title 789')->setPrivate(false),
        ];

        $this->container->get('bookmarkService')
            ->expects(static::exactly(3))
            ->method('get')
            ->withConsecutive([123], [456], [789])
            ->willReturnOnConsecutiveCalls(...$bookmarks)
        ;
        $this->container->get('bookmarkService')
            ->expects(static::exactly(3))
            ->method('set')
            ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                return [$bookmark, false];
            }, $bookmarks))
        ;
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturn(new BookmarkRawFormatter($this->container->get('conf'), true))
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::exactly(3))
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertTrue($bookmarks[0]->isPrivate());
        static::assertTrue($bookmarks[1]->isPrivate());
        static::assertTrue($bookmarks[2]->isPrivate());

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Single bookmark not found.
     */
    public function testChangeVisibilitySingleBookmarkNotFound(): void
    {
        $parameters = ['id' => '123', 'newVisibility' => 'private'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('get')
            ->willThrowException(new BookmarkNotFoundException())
        ;
        $this->container->get('bookmarkService')->expects(static::never())->method('set');
        $this->container->get('bookmarkService')->expects(static::never())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturn(new BookmarkRawFormatter($this->container->get('conf'), true))
        ;

        // Make sure that PluginManager hook is not triggered
        $this->container->get('pluginManager')
            ->expects(static::never())
            ->method('executeHooks')
            ->with('save_link')
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Multiple bookmarks with one not found.
     */
    public function testChangeVisibilityMultipleBookmarksOneNotFound(): void
    {
        $parameters = ['id' => '123 456 789', 'newVisibility' => 'public'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $bookmarks = [
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')->setPrivate(true),
            (new Bookmark())->setId(789)->setUrl('http://domain.tld')->setTitle('Title 789')->setPrivate(false),
        ];

        $this->container->get('bookmarkService')
            ->expects(static::exactly(3))
            ->method('get')
            ->withConsecutive([123], [456], [789])
            ->willReturnCallback(function (int $id) use ($bookmarks): Bookmark {
                if ($id === 123) {
                    return $bookmarks[0];
                }
                if ($id === 789) {
                    return $bookmarks[1];
                }
                throw new BookmarkNotFoundException();
            })
        ;
        $this->container->get('bookmarkService')
            ->expects(static::exactly(2))
            ->method('set')
            ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                return [$bookmark, false];
            }, $bookmarks))
        ;
        $this->container->get('bookmarkService')->expects(static::once())->method('save');

        // Make sure that PluginManager hook is not triggered
        $this->container->get('pluginManager')
            ->expects(static::exactly(2))
            ->method('executeHooks')
            ->with('save_link')
        ;

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier 456 could not be found.'])
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Invalid ID
     */
    public function testChangeVisibilityInvalidId(): void
    {
        $parameters = ['id' => 'nope not an ID', 'newVisibility' => 'private'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid bookmark ID provided.'])
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - Empty ID
     */
    public function testChangeVisibilityEmptyId(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid bookmark ID provided.'])
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Change bookmark visibility - with invalid visibility
     */
    public function testChangeVisibilityWithInvalidVisibility(): void
    {
        $parameters = ['id' => '123', 'newVisibility' => 'invalid'];

        $query = http_build_query($parameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid visibility provided.'])
        ;

        $result = $this->controller->changeVisibility($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ManageShaareControllerTest;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ManageShaareController;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

class DeleteBookmarkTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ManageShaareController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new ManageShaareController($this->container);
    }

    /**
     * Delete bookmark - Single bookmark with valid parameters
     */
    public function testDeleteSingleBookmark(): void
    {
        $parameters = ['id' => '123'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $bookmark = (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123');

        $this->container->bookmarkService->expects(static::once())->method('get')->with(123)->willReturn($bookmark);
        $this->container->bookmarkService->expects(static::once())->method('remove')->with($bookmark, false);
        $this->container->bookmarkService->expects(static::once())->method('save');
        $this->container->formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function () use ($bookmark): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);

                $formatter->expects(static::once())->method('format')->with($bookmark);

                return $formatter;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::once())
            ->method('executeHooks')
            ->with('delete_link')
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - Multiple bookmarks with valid parameters
     */
    public function testDeleteMultipleBookmarks(): void
    {
        $parameters = ['id' => '123 456 789'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $bookmarks = [
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123'),
            (new Bookmark())->setId(456)->setUrl('http://domain.tld')->setTitle('Title 456'),
            (new Bookmark())->setId(789)->setUrl('http://domain.tld')->setTitle('Title 789'),
        ];

        $this->container->bookmarkService
            ->expects(static::exactly(3))
            ->method('get')
            ->withConsecutive([123], [456], [789])
            ->willReturnOnConsecutiveCalls(...$bookmarks)
        ;
        $this->container->bookmarkService
            ->expects(static::exactly(3))
            ->method('remove')
            ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                return [$bookmark, false];
            }, $bookmarks))
        ;
        $this->container->bookmarkService->expects(static::once())->method('save');
        $this->container->formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function () use ($bookmarks): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);

                $formatter
                    ->expects(static::exactly(3))
                    ->method('format')
                    ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                        return [$bookmark];
                    }, $bookmarks))
                ;

                return $formatter;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::exactly(3))
            ->method('executeHooks')
            ->with('delete_link')
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - Single bookmark not found in the data store
     */
    public function testDeleteSingleBookmarkNotFound(): void
    {
        $parameters = ['id' => '123'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('get')
            ->willThrowException(new BookmarkNotFoundException())
        ;
        $this->container->bookmarkService->expects(static::never())->method('remove');
        $this->container->bookmarkService->expects(static::never())->method('save');
        $this->container->formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function (): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);

                $formatter->expects(static::never())->method('format');

                return $formatter;
            })
        ;
        // Make sure that PluginManager hook is not triggered
        $this->container->pluginManager
            ->expects(static::never())
            ->method('executeHooks')
            ->with('delete_link')
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - Multiple bookmarks with one not found in the data store
     */
    public function testDeleteMultipleBookmarksOneNotFound(): void
    {
        $parameters = ['id' => '123 456 789'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $bookmarks = [
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123'),
            (new Bookmark())->setId(789)->setUrl('http://domain.tld')->setTitle('Title 789'),
        ];

        $this->container->bookmarkService
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
        $this->container->bookmarkService
            ->expects(static::exactly(2))
            ->method('remove')
            ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                return [$bookmark, false];
            }, $bookmarks))
        ;
        $this->container->bookmarkService->expects(static::once())->method('save');
        $this->container->formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function () use ($bookmarks): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);

                $formatter
                    ->expects(static::exactly(2))
                    ->method('format')
                    ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                        return [$bookmark];
                    }, $bookmarks))
                ;

                return $formatter;
            })
        ;

        // Make sure that PluginManager hook is not triggered
        $this->container->pluginManager
            ->expects(static::exactly(2))
            ->method('executeHooks')
            ->with('delete_link')
        ;

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Bookmark with identifier 456 could not be found.'])
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - Invalid ID
     */
    public function testDeleteInvalidId(): void
    {
        $parameters = ['id' => 'nope not an ID'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid bookmark ID provided.'])
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - Empty ID
     */
    public function testDeleteEmptyId(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Invalid bookmark ID provided.'])
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - from bookmarklet
     */
    public function testDeleteBookmarkFromBookmarklet(): void
    {
        $parameters = [
            'id' => '123',
            'source' => 'bookmarklet',
        ];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory
            ->expects(static::once())
            ->method('getFormatter')
            ->willReturn($this->createMock(BookmarkFormatter::class))
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('<script>self.close();</script>', (string) $result->getBody('location'));
    }
}

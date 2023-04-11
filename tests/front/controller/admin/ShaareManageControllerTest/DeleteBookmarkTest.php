<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaareManageControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaareManageController;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class DeleteBookmarkTest extends TestCase
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
     * Delete bookmark - Single bookmark with valid parameters
     */
    public function testDeleteSingleBookmark(): void
    {
        $parameters = ['id' => '123'];

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery($query)
        ));
        $response = new SlimResponse();

        $bookmark = (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123');

        $this->container->get('bookmarkService')->expects(static::once())->method('get')->with(123)
            ->willReturn($bookmark);
        $this->container->get('bookmarkService')->expects(static::once())->method('remove')->with($bookmark, false);
        $this->container->get('bookmarkService')->expects(static::once())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->with('raw')
            ->willReturnCallback(function () use ($bookmark): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);
                $formatter
                    ->expects(static::once())
                    ->method('format')
                    ->with($bookmark)
                    ->willReturn(['formatted' => $bookmark])
                ;

                return $formatter;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::once())
            ->method('executeHooks')
            ->with('delete_link', ['formatted' => $bookmark])
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

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('http', 'shaarli', 80, '/subfolder', ''))
                ->withQuery($query)
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'HTTP_REFERER' => 'http://shaarli/subfolder/?searchtags=abcdef',
            'SCRIPT_NAME' => '/subfolder/index.php',
        ]);
        $response = new SlimResponse();

        $bookmarks = [
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123'),
            (new Bookmark())->setId(456)->setUrl('http://domain.tld')->setTitle('Title 456'),
            (new Bookmark())->setId(789)->setUrl('http://domain.tld')->setTitle('Title 789'),
        ];

        $this->container->get('bookmarkService')
            ->expects(static::exactly(3))
            ->method('get')
            ->withConsecutive([123], [456], [789])
            ->willReturnOnConsecutiveCalls(...$bookmarks)
        ;
        $this->container->get('bookmarkService')
            ->expects(static::exactly(3))
            ->method('remove')
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
            ->willReturnCallback(function () use ($bookmarks): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);

                $formatter
                    ->expects(static::exactly(3))
                    ->method('format')
                    ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                        return [$bookmark];
                    }, $bookmarks))
                    ->willReturnOnConsecutiveCalls(...array_map(function (Bookmark $bookmark): array {
                        return ['formatted' => $bookmark];
                    }, $bookmarks))
                ;

                return $formatter;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::exactly(3))
            ->method('executeHooks')
            ->with('delete_link')
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/?searchtags=abcdef'], $result->getHeader('location'));
    }

    /**
     * Delete bookmark - Single bookmark not found in the data store
     */
    public function testDeleteSingleBookmarkNotFound(): void
    {
        $parameters = ['id' => '123'];

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery($query)
        ));
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('get')
            ->willThrowException(new BookmarkNotFoundException())
        ;
        $this->container->get('bookmarkService')->expects(static::never())->method('remove');
        $this->container->get('bookmarkService')->expects(static::never())->method('save');
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
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
        $this->container->get('pluginManager')
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

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery($query)
        ));
        $response = new SlimResponse();

        $bookmarks = [
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123'),
            (new Bookmark())->setId(789)->setUrl('http://domain.tld')->setTitle('Title 789'),
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
            ->method('remove')
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
            ->willReturnCallback(function () use ($bookmarks): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);

                $formatter
                    ->expects(static::exactly(2))
                    ->method('format')
                    ->withConsecutive(...array_map(function (Bookmark $bookmark): array {
                        return [$bookmark];
                    }, $bookmarks))
                    ->willReturnOnConsecutiveCalls(...array_map(function (Bookmark $bookmark): array {
                        return ['formatted' => $bookmark];
                    }, $bookmarks))
                ;

                return $formatter;
            })
        ;

        // Make sure that PluginManager hook is not triggered
        $this->container->get('pluginManager')
            ->expects(static::exactly(2))
            ->method('executeHooks')
            ->with('delete_link')
        ;

        $this->container->get('sessionManager')
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

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery($query)
        ));
        $response = new SlimResponse();

        $this->container->get('sessionManager')
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
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('sessionManager')
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

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery($query)
        ));
        $response = new SlimResponse();

        $this->container->get('bookmarkService')->method('get')->with('123')->willReturn(
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
        );
        $this->container->get('bookmarkService')->expects(static::once())->method('remove');

        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->willReturnCallback(function (): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);
                $formatter->method('format')->willReturn(['formatted']);

                return $formatter;
            })
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('<script>self.close();</script>', (string) $result->getBody());
    }

    /**
     * Delete bookmark - from batch view
     */
    public function testDeleteBookmarkFromBatch(): void
    {
        $parameters = [
            'id' => '123',
            'source' => 'batch',
        ];

        $query = http_build_query($parameters);
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))
                ->withQuery($query)
        ));
        $response = new SlimResponse();

        $this->container->get('bookmarkService')->method('get')->with('123')->willReturn(
            (new Bookmark())->setId(123)->setUrl('http://domain.tld')->setTitle('Title 123')
        );
        $this->container->get('bookmarkService')->expects(static::once())->method('remove');

        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->expects(static::once())
            ->method('getFormatter')
            ->willReturnCallback(function (): BookmarkFormatter {
                $formatter = $this->createMock(BookmarkFormatter::class);
                $formatter->method('format')->willReturn(['formatted']);

                return $formatter;
            })
        ;

        $result = $this->controller->deleteBookmark($request, $response);

        static::assertSame(204, $result->getStatusCode());
        static::assertEmpty((string) $result->getBody());
    }
}

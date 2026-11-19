<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin\ShaarePublishControllerTest;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Controller\Admin\FrontAdminControllerMockHelper;
use Shaarli\Front\Controller\Admin\ShaarePublishController;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Http\HttpAccess;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class SaveBookmarkTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaarePublishController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new ShaarePublishController($this->container);
    }

    /**
     * Test save a new bookmark
     */
    public function testSaveBookmark(): void
    {
        $id = 21;
        $parameters = [
            'lf_url' => 'http://url.tld/other?part=3#hash',
            'lf_title' => 'Provided Title',
            'lf_description' => 'Provided description.',
            'lf_tags' => 'abc def',
            'lf_private' => '1',
            'returnurl' => 'http://shaarli/subfolder/admin/add-shaare'
        ];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $checkBookmark = function (Bookmark $bookmark) use ($parameters) {
            static::assertSame($parameters['lf_url'], $bookmark->getUrl());
            static::assertSame($parameters['lf_title'], $bookmark->getTitle());
            static::assertSame($parameters['lf_description'], $bookmark->getDescription());
            static::assertSame($parameters['lf_tags'], $bookmark->getTagsString());
            static::assertTrue($bookmark->isPrivate());
        };

        $capturedBookmark = null;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(
                function (Bookmark $bookmark, bool $save) use (&$capturedBookmark, $checkBookmark, $id): Bookmark {
                    static::assertFalse($save);
                    $checkBookmark($bookmark);
                    $bookmark->setId($id);
                    $capturedBookmark = $bookmark;
                    return $bookmark;
                }
            )
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('save')
            ->willReturnCallback(
                function () use (&$capturedBookmark, $checkBookmark, $id): void {
                    static::assertNotNull($capturedBookmark);
                    static::assertSame($id, $capturedBookmark->getId());
                    $checkBookmark($capturedBookmark);
                }
            )
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['save_link'])
            ->willReturnCallback(function (string $hook, array $data) use ($parameters, $id): array {
                if ('save_link' === $hook) {
                    static::assertSame($id, $data['id']);
                    static::assertSame($parameters['lf_url'], $data['url']);
                    static::assertSame($parameters['lf_title'], $data['title']);
                    static::assertSame($parameters['lf_description'], $data['description']);
                    static::assertSame($parameters['lf_tags'], $data['tags']);
                    static::assertTrue($data['private']);
                }

                return $data;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertRegExp('@/subfolder/#[\w\-]{6}@', $result->getHeader('location')[0]);
    }


    /**
     * Test save an existing bookmark
     */
    public function testSaveExistingBookmark(): void
    {
        $id = 21;
        $parameters = [
            'lf_id' => (string) $id,
            'lf_url' => 'http://url.tld/other?part=3#hash',
            'lf_title' => 'Provided Title',
            'lf_description' => 'Provided description.',
            'lf_tags' => 'abc def',
            'lf_private' => '1',
            'returnurl' => 'http://shaarli/subfolder/?page=2'
        ];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $checkBookmark = function (Bookmark $bookmark) use ($parameters, $id) {
            static::assertSame($id, $bookmark->getId());
            static::assertSame($parameters['lf_url'], $bookmark->getUrl());
            static::assertSame($parameters['lf_title'], $bookmark->getTitle());
            static::assertSame($parameters['lf_description'], $bookmark->getDescription());
            static::assertSame($parameters['lf_tags'], $bookmark->getTagsString());
            static::assertTrue($bookmark->isPrivate());
        };

        $this->container->bookmarkService->expects(static::atLeastOnce())->method('exists')->willReturn(true);
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('get')
            ->willReturn((new Bookmark())->setId($id)->setUrl('http://other.url'))
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($checkBookmark, $id): Bookmark {
                static::assertFalse($save);

                $checkBookmark($bookmark);

                return $bookmark;
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('set')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($checkBookmark, $id): Bookmark {
                static::assertTrue($save);

                $checkBookmark($bookmark);

                static::assertSame($id, $bookmark->getId());

                return $bookmark;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['save_link'])
            ->willReturnCallback(function (string $hook, array $data) use ($parameters, $id): array {
                if ('save_link' === $hook) {
                    static::assertSame($id, $data['id']);
                    static::assertSame($parameters['lf_url'], $data['url']);
                    static::assertSame($parameters['lf_title'], $data['title']);
                    static::assertSame($parameters['lf_description'], $data['description']);
                    static::assertSame($parameters['lf_tags'], $data['tags']);
                    static::assertTrue($data['private']);
                }

                return $data;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertRegExp('@/subfolder/\?page=2#[\w\-]{6}@', $result->getHeader('location')[0]);
    }

    /**
     * Test save a bookmark - try to retrieve the thumbnail
     */
    public function testSaveBookmarkWithThumbnailSync(): void
    {
        $parameters = ['lf_url' => 'http://url.tld/other?part=3#hash'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key, $default) {
            if ($key === 'thumbnails.mode') {
                return Thumbnailer::MODE_ALL;
            } elseif ($key === 'general.enable_async_metadata') {
                return false;
            }

            return $default;
        });

        $this->container->thumbnailer = $this->createMock(Thumbnailer::class);
        $this->container->thumbnailer
            ->expects(static::once())
            ->method('get')
            ->with($parameters['lf_url'])
            ->willReturn($thumb = 'http://thumb.url')
        ;

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(function (Bookmark $bookmark, bool $save) use ($thumb): Bookmark {
                static::assertSame($thumb, $bookmark->getThumbnail());

                return $bookmark;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
    }

    /**
     * Test save a bookmark - with ID #0
     */
    public function testSaveBookmarkWithIdZero(): void
    {
        $parameters = ['lf_id' => '0'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->bookmarkService->expects(static::once())->method('exists')->with(0)->willReturn(true);
        $this->container->bookmarkService->expects(static::once())->method('get')->with(0)->willReturn(new Bookmark());

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
    }

    /**
     * Test save a bookmark - do not attempt to retrieve thumbnails if async mode is enabled.
     */
    public function testSaveBookmarkWithThumbnailAsync(): void
    {
        $parameters = ['lf_url' => 'http://url.tld/other?part=3#hash'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key, $default) {
            if ($key === 'thumbnails.mode') {
                return Thumbnailer::MODE_ALL;
            } elseif ($key === 'general.enable_async_metadata') {
                return true;
            }

            return $default;
        });

        $this->container->thumbnailer = $this->createMock(Thumbnailer::class);
        $this->container->thumbnailer->expects(static::never())->method('get');

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('addOrSet')
            ->willReturnCallback(function (Bookmark $bookmark): Bookmark {
                static::assertNull($bookmark->getThumbnail());

                return $bookmark;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
    }

    /**
     * Change the password with a wrong existing password
     */
    public function testSaveBookmarkFromBookmarklet(): void
    {
        $parameters = ['source' => 'bookmarklet'];

        $request = $this->createMock(Request::class);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($parameters): ?string {
                return $parameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('<script>self.close();</script>', (string) $result->getBody());
    }

    /**
     * Change the password with a wrong existing password
     */
    public function testSaveBookmarkWrongToken(): void
    {
        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(false);

        $this->container->bookmarkService->expects(static::never())->method('addOrSet');
        $this->container->bookmarkService->expects(static::never())->method('set');

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(WrongTokenException::class);

        $this->controller->save($request, $response);
    }
}

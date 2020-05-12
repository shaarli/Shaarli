<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Security\LoginManager;
use Slim\Http\Request;
use Slim\Http\Response;

class TagControllerTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var TagController */
    protected $controller;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->controller = new TagController($this->container);
    }

    public function testAddTagWithReferer(): void
    {
        $this->createValidContainerMockSet();
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndExistingSearch(): void
    {
        $this->createValidContainerMockSet();
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def+abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutRefererAndExistingSearch(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagRemoveLegacyQueryParam(): void
    {
        $this->createValidContainerMockSet();
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&addtag=abc'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def+abc'], $result->getHeader('location'));
    }

    public function testAddTagResetPagination(): void
    {
        $this->createValidContainerMockSet();
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&page=12'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def+abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndEmptySearch(): void
    {
        $this->createValidContainerMockSet();
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags='];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithReferer(): void
    {
        $this->createValidContainerMockSet();
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithoutReferer(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }

    protected function createValidContainerMockSet(): void
    {
        // User logged out
        $loginManager = $this->createMock(LoginManager::class);
        $loginManager->method('isLoggedIn')->willReturn(false);
        $loginManager->method('canLogin')->willReturn(true);
        $this->container->loginManager = $loginManager;

        // Config
        $conf = $this->createMock(ConfigManager::class);
        $conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            return $default;
        });
        $this->container->conf = $conf;

        // PageBuilder
        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder
            ->method('render')
            ->willReturnCallback(function (string $template): string {
                return $template;
            })
        ;
        $this->container->pageBuilder = $pageBuilder;

        $pluginManager = $this->createMock(PluginManager::class);
        $this->container->pluginManager = $pluginManager;
        $bookmarkService = $this->createMock(BookmarkServiceInterface::class);
        $this->container->bookmarkService = $bookmarkService;
    }
}

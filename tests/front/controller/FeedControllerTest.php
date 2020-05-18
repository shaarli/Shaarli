<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Slim\Http\Request;
use Slim\Http\Response;

class FeedControllerTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var FeedController */
    protected $controller;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->controller = new FeedController($this->container);
    }

    /**
     * Feed Controller - RSS default behaviour
     */
    public function testDefaultRssController(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->feedBuilder->expects(static::once())->method('setLocale');
        $this->container->feedBuilder->expects(static::once())->method('setHideDates')->with(false);
        $this->container->feedBuilder->expects(static::once())->method('setUsePermalinks')->with(true);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->feedBuilder->method('buildData')->willReturn(['content' => 'data']);

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): void {
                static::assertSame('render_feed', $hook);
                static::assertSame('data', $data['content']);

                static::assertArrayHasKey('loggedin', $param);
                static::assertSame('rss', $param['target']);
            })
        ;

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('feed.rss', (string) $result->getBody());
        static::assertSame('data', $assignedVariables['content']);
    }

    /**
     * Feed Controller - ATOM default behaviour
     */
    public function testDefaultAtomController(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->feedBuilder->expects(static::once())->method('setLocale');
        $this->container->feedBuilder->expects(static::once())->method('setHideDates')->with(false);
        $this->container->feedBuilder->expects(static::once())->method('setUsePermalinks')->with(true);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->feedBuilder->method('buildData')->willReturn(['content' => 'data']);

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): void {
                static::assertSame('render_feed', $hook);
                static::assertSame('data', $data['content']);

                static::assertArrayHasKey('loggedin', $param);
                static::assertSame('atom', $param['target']);
            })
        ;

        $result = $this->controller->atom($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/atom', $result->getHeader('Content-Type')[0]);
        static::assertSame('feed.atom', (string) $result->getBody());
        static::assertSame('data', $assignedVariables['content']);
    }

    /**
     * Feed Controller - ATOM with parameters
     */
    public function testAtomControllerWithParameters(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $request->method('getParams')->willReturn(['parameter' => 'value']);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->feedBuilder
            ->method('buildData')
            ->with('atom', ['parameter' => 'value'])
            ->willReturn(['content' => 'data'])
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): void {
                static::assertSame('render_feed', $hook);
                static::assertSame('data', $data['content']);

                static::assertArrayHasKey('loggedin', $param);
                static::assertSame('atom', $param['target']);
            })
        ;

        $result = $this->controller->atom($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/atom', $result->getHeader('Content-Type')[0]);
        static::assertSame('feed.atom', (string) $result->getBody());
        static::assertSame('data', $assignedVariables['content']);
    }

    protected function createValidContainerMockSet(): void
    {
        $loginManager = $this->createMock(LoginManager::class);
        $this->container->loginManager = $loginManager;

        // Config
        $conf = $this->createMock(ConfigManager::class);
        $this->container->conf = $conf;
        $this->container->conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            return $default;
        });

        // PageBuilder
        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder
            ->method('render')
            ->willReturnCallback(function (string $template): string {
                return $template;
            })
        ;
        $this->container->pageBuilder = $pageBuilder;

        $bookmarkService = $this->createMock(BookmarkServiceInterface::class);
        $this->container->bookmarkService = $bookmarkService;

        // Plugin Manager
        $pluginManager = $this->createMock(PluginManager::class);
        $this->container->pluginManager = $pluginManager;

        // Formatter
        $formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory = $formatterFactory;

        // CacheManager
        $pageCacheManager = $this->createMock(PageCacheManager::class);
        $this->container->pageCacheManager = $pageCacheManager;

        // FeedBuilder
        $feedBuilder = $this->createMock(FeedBuilder::class);
        $this->container->feedBuilder = $feedBuilder;

        // $_SERVER
        $this->container->environment = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'REQUEST_URI' => '/daily-rss',
        ];
    }

    protected function assignTemplateVars(array &$variables): void
    {
        $this->container->pageBuilder
            ->expects(static::atLeastOnce())
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$variables) {
                $variables[$key] = $value;

                return $this;
            })
        ;
    }
}

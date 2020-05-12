<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Exception\ThumbnailsDisabledException;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Security\LoginManager;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class PictureWallControllerTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var PictureWallController */
    protected $controller;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->controller = new PictureWallController($this->container);
    }

    public function testValidControllerInvokeDefault(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $request->expects(static::once())->method('getQueryParams')->willReturn([]);
        $response = new Response();

        // ConfigManager: thumbnails are enabled
        $this->container->conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'thumbnails.mode') {
                return Thumbnailer::MODE_COMMON;
            }

            return $default;
        });

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->container->pageBuilder
            ->expects(static::atLeastOnce())
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$assignedVariables) {
                $assignedVariables[$key] = $value;

                return $this;
            })
        ;

        // Links dataset: 2 links with thumbnails
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('search')
            ->willReturnCallback(function (array $parameters, ?string $visibility): array {
                // Visibility is set through the container, not the call
                static::assertNull($visibility);

                // No query parameters
                if (count($parameters) === 0) {
                    return [
                        (new Bookmark())->setId(1)->setUrl('http://url.tld')->setThumbnail('thumb1'),
                        (new Bookmark())->setId(2)->setUrl('http://url2.tld'),
                        (new Bookmark())->setId(3)->setUrl('http://url3.tld')->setThumbnail('thumb2'),
                    ];
                }
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_picwall', $hook);
                static::assertArrayHasKey('linksToDisplay', $data);
                static::assertCount(2, $data['linksToDisplay']);
                static::assertSame(1, $data['linksToDisplay'][0]['id']);
                static::assertSame(3, $data['linksToDisplay'][1]['id']);
                static::assertArrayHasKey('loggedin', $param);

                return $data;
            });

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('picwall', (string) $result->getBody());
        static::assertSame('Picture wall - Shaarli', $assignedVariables['pagetitle']);
        static::assertCount(2, $assignedVariables['linksToDisplay']);

        $link = $assignedVariables['linksToDisplay'][0];

        static::assertSame(1, $link['id']);
        static::assertSame('http://url.tld', $link['url']);
        static::assertSame('thumb1', $link['thumbnail']);

        $link = $assignedVariables['linksToDisplay'][1];

        static::assertSame(3, $link['id']);
        static::assertSame('http://url3.tld', $link['url']);
        static::assertSame('thumb2', $link['thumbnail']);
    }

    public function testControllerWithThumbnailsDisabled(): void
    {
        $this->expectException(ThumbnailsDisabledException::class);

        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        // ConfigManager: thumbnails are disabled
        $this->container->conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'thumbnails.mode') {
                return Thumbnailer::MODE_NONE;
            }

            return $default;
        });

        $this->controller->index($request, $response);
    }

    protected function createValidContainerMockSet(): void
    {
        $loginManager = $this->createMock(LoginManager::class);
        $this->container->loginManager = $loginManager;

        // Config
        $conf = $this->createMock(ConfigManager::class);
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

        // Plugin Manager
        $pluginManager = $this->createMock(PluginManager::class);
        $this->container->pluginManager = $pluginManager;

        // BookmarkService
        $bookmarkService = $this->createMock(BookmarkServiceInterface::class);
        $this->container->bookmarkService = $bookmarkService;

        // Formatter
        $formatterFactory = $this->createMock(FormatterFactory::class);
        $formatterFactory
            ->method('getFormatter')
            ->willReturnCallback(function (string $type): BookmarkFormatter {
                if ($type === 'raw') {
                    return new BookmarkRawFormatter($this->container->conf, true);
                }
            })
        ;
        $this->container->formatterFactory = $formatterFactory;
    }
}

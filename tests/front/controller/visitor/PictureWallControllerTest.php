<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\ThumbnailsDisabledException;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class PictureWallControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var PictureWallController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new PictureWallController($this->container);
    }

    public function testValidControllerInvokeDefault(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects(static::once())->method('getQueryParams')->willReturn([]);
        $response = new Response();

        // ConfigManager: thumbnails are enabled
        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'thumbnails.mode') {
                return Thumbnailer::MODE_COMMON;
            }

            return $default;
        });

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

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
}

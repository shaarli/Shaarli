<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\SearchResult;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\ThumbnailsDisabledException;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Thumbnailer;

class PictureWallControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var PictureWallController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new PictureWallController($this->container);
    }

    public function testValidControllerInvokeDefault(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        // ConfigManager: thumbnails are enabled
        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'thumbnails.mode') {
                return Thumbnailer::MODE_COMMON;
            }

            return $default;
        });

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        // Links dataset: 2 links with thumbnails
        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('search')
            ->willReturnCallback(function (array $parameters, ?string $visibility): SearchResult {
                // Visibility is set through the container, not the call
                static::assertNull($visibility);

                // No query parameters
                if (count($parameters) === 0) {
                    return SearchResult::getSearchResult([
                        (new Bookmark())->setId(1)->setUrl('http://url.tld')->setThumbnail('thumb1'),
                        (new Bookmark())->setId(2)->setUrl('http://url2.tld'),
                        (new Bookmark())->setId(3)->setUrl('http://url3.tld')->setThumbnail('thumb2'),
                    ]);
                }
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_picwall'])
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                if ('render_picwall' === $hook) {
                    static::assertArrayHasKey('linksToDisplay', $data);
                    static::assertCount(2, $data['linksToDisplay']);
                    static::assertSame(1, $data['linksToDisplay'][0]['id']);
                    static::assertSame(3, $data['linksToDisplay'][1]['id']);
                    static::assertArrayHasKey('loggedin', $param);
                }

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

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        // ConfigManager: thumbnails are disabled
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'thumbnails.mode') {
                return Thumbnailer::MODE_NONE;
            }

            return $default;
        });

        $this->controller->index($request, $response);
    }
}

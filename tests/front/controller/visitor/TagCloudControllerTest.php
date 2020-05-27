<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkFilter;
use Slim\Http\Request;
use Slim\Http\Response;

class TagCloudControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var TagCloudController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new TagCloudController($this->container);
    }

    /**
     * Tag Cloud - default parameters
     */
    public function testValidCloudControllerInvokeDefault(): void
    {
        $allTags = [
            'ghi' => 1,
            'abc' => 3,
            'def' => 12,
        ];
        $expectedOrder = ['abc', 'def', 'ghi'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->with([], null)
            ->willReturnCallback(function () use ($allTags): array {
                return $allTags;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_tagcloud', $hook);
                static::assertSame('', $data['search_tags']);
                static::assertCount(3, $data['tags']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            })
        ;

        $result = $this->controller->cloud($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tag.cloud', (string) $result->getBody());
        static::assertSame('Tag cloud - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame('', $assignedVariables['search_tags']);
        static::assertCount(3, $assignedVariables['tags']);
        static::assertSame($expectedOrder, array_keys($assignedVariables['tags']));

        foreach ($allTags as $tag => $count) {
            static::assertArrayHasKey($tag, $assignedVariables['tags']);
            static::assertSame($count, $assignedVariables['tags'][$tag]['count']);
            static::assertGreaterThan(0, $assignedVariables['tags'][$tag]['size']);
            static::assertLessThan(5, $assignedVariables['tags'][$tag]['size']);
        }
    }

    /**
     * Tag Cloud - Additional parameters:
     *   - logged in
     *   - visibility private
     *   - search tags: `ghi` and `def` (note that filtered tags are not displayed anymore)
     */
    public function testValidCloudControllerInvokeWithParameters(): void
    {
        $request = $this->createMock(Request::class);
        $request
            ->method('getQueryParam')
            ->with()
            ->willReturnCallback(function (string $key): ?string {
                if ('searchtags' === $key) {
                    return 'ghi def';
                }

                return null;
            })
        ;
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->loginManager->method('isLoggedin')->willReturn(true);
        $this->container->sessionManager->expects(static::once())->method('getSessionParameter')->willReturn('private');

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->with(['ghi', 'def'], BookmarkFilter::$PRIVATE)
            ->willReturnCallback(function (): array {
                return ['abc' => 3];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_tagcloud', $hook);
                static::assertSame('ghi def', $data['search_tags']);
                static::assertCount(1, $data['tags']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            })
        ;

        $result = $this->controller->cloud($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tag.cloud', (string) $result->getBody());
        static::assertSame('ghi def - Tag cloud - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame('ghi def', $assignedVariables['search_tags']);
        static::assertCount(1, $assignedVariables['tags']);

        static::assertArrayHasKey('abc', $assignedVariables['tags']);
        static::assertSame(3, $assignedVariables['tags']['abc']['count']);
        static::assertGreaterThan(0, $assignedVariables['tags']['abc']['size']);
        static::assertLessThan(5, $assignedVariables['tags']['abc']['size']);
    }

    /**
     * Tag Cloud - empty
     */
    public function testEmptyCloud(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->with([], null)
            ->willReturnCallback(function (array $parameters, ?string $visibility): array {
                return [];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_tagcloud', $hook);
                static::assertSame('', $data['search_tags']);
                static::assertCount(0, $data['tags']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            })
        ;

        $result = $this->controller->cloud($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tag.cloud', (string) $result->getBody());
        static::assertSame('Tag cloud - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame('', $assignedVariables['search_tags']);
        static::assertCount(0, $assignedVariables['tags']);
    }

    /**
     * Tag List - Default sort is by usage DESC
     */
    public function testValidListControllerInvokeDefault(): void
    {
        $allTags = [
            'def' => 12,
            'abc' => 3,
            'ghi' => 1,
        ];

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->with([], null)
            ->willReturnCallback(function () use ($allTags): array {
                return $allTags;
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_taglist', $hook);
                static::assertSame('', $data['search_tags']);
                static::assertCount(3, $data['tags']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            })
        ;

        $result = $this->controller->list($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tag.list', (string) $result->getBody());
        static::assertSame('Tag list - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame('', $assignedVariables['search_tags']);
        static::assertCount(3, $assignedVariables['tags']);

        foreach ($allTags as $tag => $count) {
            static::assertSame($count, $assignedVariables['tags'][$tag]);
        }
    }

    /**
     * Tag List - Additional parameters:
     *   - logged in
     *   - visibility private
     *   - search tags: `ghi` and `def` (note that filtered tags are not displayed anymore)
     *   - sort alphabetically
     */
    public function testValidListControllerInvokeWithParameters(): void
    {
        $request = $this->createMock(Request::class);
        $request
            ->method('getQueryParam')
            ->with()
            ->willReturnCallback(function (string $key): ?string {
                if ('searchtags' === $key) {
                    return 'ghi def';
                } elseif ('sort' === $key) {
                    return 'alpha';
                }

                return null;
            })
        ;
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->loginManager->method('isLoggedin')->willReturn(true);
        $this->container->sessionManager->expects(static::once())->method('getSessionParameter')->willReturn('private');

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->with(['ghi', 'def'], BookmarkFilter::$PRIVATE)
            ->willReturnCallback(function (): array {
                return ['abc' => 3];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_taglist', $hook);
                static::assertSame('ghi def', $data['search_tags']);
                static::assertCount(1, $data['tags']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            })
        ;

        $result = $this->controller->list($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tag.list', (string) $result->getBody());
        static::assertSame('ghi def - Tag list - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame('ghi def', $assignedVariables['search_tags']);
        static::assertCount(1, $assignedVariables['tags']);
        static::assertSame(3, $assignedVariables['tags']['abc']);
    }

    /**
     * Tag List - empty
     */
    public function testEmptyList(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->with([], null)
            ->willReturnCallback(function (array $parameters, ?string $visibility): array {
                return [];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                static::assertSame('render_taglist', $hook);
                static::assertSame('', $data['search_tags']);
                static::assertCount(0, $data['tags']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            })
        ;

        $result = $this->controller->list($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tag.list', (string) $result->getBody());
        static::assertSame('Tag list - Shaarli', $assignedVariables['pagetitle']);

        static::assertSame('', $assignedVariables['search_tags']);
        static::assertCount(0, $assignedVariables['tags']);
    }
}

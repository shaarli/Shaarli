<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Shaarli\Feed\FeedBuilder;
use Slim\Http\Request;
use Slim\Http\Response;

class FeedControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var FeedController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->feedBuilder = $this->createMock(FeedBuilder::class);

        $this->controller = new FeedController($this->container);
    }

    /**
     * Feed Controller - RSS default behaviour
     */
    public function testDefaultRssController(): void
    {
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
}

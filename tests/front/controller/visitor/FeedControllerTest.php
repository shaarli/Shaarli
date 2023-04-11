<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Feed\FeedBuilder;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class FeedControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var FeedController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->set('feedBuilder', $this->createMock(FeedBuilder::class));

        $this->controller = new FeedController($this->container);
    }

    /**
     * Feed Controller - RSS default behaviour
     */
    public function testDefaultRssController(): void
    {
        $request = (new FakeRequest())->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
        ]);
        $response = new SlimResponse();

        $this->container->get('feedBuilder')->expects(static::once())->method('setLocale');
        $this->container->get('feedBuilder')->expects(static::once())->method('setHideDates')->with(false);
        $this->container->get('feedBuilder')->expects(static::once())->method('setUsePermalinks')->with(true);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('feedBuilder')->method('buildData')->willReturn(['content' => 'data']);

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_feed'])
            ->willReturnCallback(function (string $hook, array $data, array $param): void {
                if ('render_feed' === $hook) {
                    static::assertSame('data', $data['content']);

                    static::assertArrayHasKey('loggedin', $param);
                    static::assertSame('feed.rss', $param['target']);
                }
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
        $request = (new FakeRequest())->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
        ]);
        $response = new SlimResponse();

        $this->container->get('feedBuilder')->expects(static::once())->method('setLocale');
        $this->container->get('feedBuilder')->expects(static::once())->method('setHideDates')->with(false);
        $this->container->get('feedBuilder')->expects(static::once())->method('setUsePermalinks')->with(true);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('feedBuilder')->method('buildData')->willReturn(['content' => 'data']);

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_feed'])
            ->willReturnCallback(function (string $hook, array $data, array $param): void {
                if ('render_feed' === $hook) {
                    static::assertSame('data', $data['content']);

                    static::assertArrayHasKey('loggedin', $param);
                    static::assertSame('feed.atom', $param['target']);
                }
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
        $request = new FakeRequest();
        $request = (new FakeRequest('GET', (new Uri('', ''))
            ->withQuery(http_build_query(['parameter' => 'value']))))
            ->withServerParams([
                'SERVER_NAME' => 'shaarli',
                'SERVER_PORT' => '80',
                ]);
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('feedBuilder')
            ->method('buildData')
            ->with('atom', ['parameter' => 'value'])
            ->willReturn(['content' => 'data'])
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_feed'])
            ->willReturnCallback(function (string $hook, array $data, array $param): void {
                if ('render_feed' === $hook) {
                    static::assertSame('data', $data['content']);

                    static::assertArrayHasKey('loggedin', $param);
                    static::assertSame('feed.atom', $param['target']);
                }
            })
        ;

        $result = $this->controller->atom($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/atom', $result->getHeader('Content-Type')[0]);
        static::assertSame('feed.atom', (string) $result->getBody());
        static::assertSame('data', $assignedVariables['content']);
    }
}

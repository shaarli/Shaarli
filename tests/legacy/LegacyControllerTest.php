<?php

declare(strict_types=1);

namespace Shaarli\Legacy;

use Shaarli\Front\Controller\Visitor\FrontControllerMockHelper;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class LegacyControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var LegacyController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new LegacyController($this->container);
    }

    /**
     * @dataProvider getProcessProvider
     */
    public function testProcess(string $legacyRoute, array $queryParameters, string $slimRoute, bool $isLoggedIn): void
    {
        $query = http_build_query($queryParameters);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli?' . $query);
        $response = $this->responseFactory->createResponse();

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn($isLoggedIn);

        $result = $this->controller->process($request, $response, $legacyRoute);

        static::assertSame('/subfolder' . $slimRoute, $result->getHeader('location')[0]);
    }

    public function testProcessNotFound(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->expectException(UnknowLegacyRouteException::class);

        $this->controller->process($request, $response, 'nope');
    }

    /**
     * @return array[] Parameters:
     *                   - string legacyRoute
     *                   - array  queryParameters
     *                   - string slimRoute
     *                   - bool   isLoggedIn
     */
    public function getProcessProvider(): array
    {
        return [
             ['post', [], '/admin/shaare', true],
             ['post', [], '/login?returnurl=/subfolder/admin/shaare', false],
             ['post', ['title' => 'test'], '/admin/shaare?title=test', true],
             ['post', ['title' => 'test'], '/login?returnurl=/subfolder/admin/shaare?title=test', false],
             ['addlink', [], '/admin/add-shaare', true],
             ['addlink', [], '/login?returnurl=/subfolder/admin/add-shaare', false],
             ['login', [], '/login', true],
             ['login', [], '/login', false],
             ['logout', [], '/admin/logout', true],
             ['logout', [], '/admin/logout', false],
             ['picwall', [], '/picture-wall', false],
             ['picwall', [], '/picture-wall', true],
             ['tagcloud', [], '/tags/cloud', false],
             ['tagcloud', [], '/tags/cloud', true],
             ['taglist', [], '/tags/list', false],
             ['taglist', [], '/tags/list', true],
             ['daily', [], '/daily', false],
             ['daily', [], '/daily', true],
             ['daily', ['day' => '123456789', 'discard' => '1'], '/daily?day=123456789', false],
             ['rss', [], '/feed/rss', false],
             ['rss', [], '/feed/rss', true],
             ['rss', ['search' => 'filter123', 'other' => 'param'], '/feed/rss?search=filter123&other=param', false],
             ['atom', [], '/feed/atom', false],
             ['atom', [], '/feed/atom', true],
             ['atom', ['search' => 'filter123', 'other' => 'param'], '/feed/atom?search=filter123&other=param', false],
             ['opensearch', [], '/open-search', false],
             ['opensearch', [], '/open-search', true],
             ['dailyrss', [], '/daily-rss', false],
             ['dailyrss', [], '/daily-rss', true],
             ['configure', [], '/login?returnurl=/subfolder/admin/configure', false],
             ['configure', [], '/admin/configure', true],
        ];
    }
}

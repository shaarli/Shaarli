<?php

declare(strict_types=1);

namespace Shaarli\Front;

use DI\Container as DIContainer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Security\LoginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\RequestHandlerFactory;
use Shaarli\Updater\Updater;
use Slim\Http\Uri;

class ShaarliAdminMiddlewareTest extends TestCase
{
    protected const TMP_MOCK_FILE = '.tmp';

    /** @var Container */
    protected $container;

    /** @var ShaarliMiddleware  */
    protected $middleware;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->container = new DIContainer();

        touch(static::TMP_MOCK_FILE);

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('getConfigFileExt')->willReturn(static::TMP_MOCK_FILE);

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->set('updater', $this->createMock(Updater::class));
        $this->container->set('basePath', '/subfolder');

        $this->middleware = new ShaarliAdminMiddleware($this->container);
        $this->requestHandlerFactory = new RequestHandlerFactory();
    }

    public function tearDown(): void
    {
        unlink(static::TMP_MOCK_FILE);
    }

    /**
     * Try to access an admin controller while logged out -> redirected to login page.
     */
    public function testMiddlewareWhileLoggedOut(): void
    {

        $this->container->get('loginManager')->expects(static::once())->method('isLoggedIn')->willReturn(false);

        $serverParams = ['REQUEST_URI' => 'http://shaarli/subfolder/path'];
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', 'http://shaarli/subfolder/path', $serverParams);

        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $result = ($this->middleware)($request, $requestHandler);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(
            '/subfolder/login?returnurl=' . urlencode('http://shaarli/subfolder/path'),
            $result->getHeader('location')[0]
        );
    }

    /**
     * Process controller while logged in.
     */
    public function testMiddlewareWhileLoggedIn(): void
    {
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $serverParams = ['REQUEST_URI' => 'http://shaarli/subfolder/path'];
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', 'http://shaarli/subfolder/path', $serverParams);

        $responseFactory = $this->responseFactory;
        $requestHandler = $this->requestHandlerFactory->createRequestHandler(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($responseFactory) {
                return $responseFactory->createResponse()->withStatus(418);
            }
        );
        $result = ($this->middleware)($request, $requestHandler);


        static::assertSame(418, $result->getStatusCode());
    }
}

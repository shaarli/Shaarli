<?php

declare(strict_types=1);

namespace Shaarli\Front;

use DI\Container as DIContainer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\LoginBannedException;
use Shaarli\Front\Exception\UnauthorizedException;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\RequestHandlerFactory;
use Shaarli\Updater\Updater;

class ShaarliMiddlewareTest extends TestCase
{
    protected const TMP_MOCK_FILE = '.tmp';

    /** @var Container */
    protected $container;

    /** @var ShaarliMiddleware  */
    protected $middleware;

    /** @var RequestHandlerFactory */
    private $requestHandlerFactory;
    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->container = new DIContainer();

        touch(static::TMP_MOCK_FILE);

        $this->container->set('conf', $this->createMock(ConfigManager::class));

        $conf = $this->container->get('conf');
        $conf->method('getConfigFileExt')->willReturn(static::TMP_MOCK_FILE);

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->set('basePath', '/subfolder');

        $this->middleware = new ShaarliMiddleware($this->container);
        $this->requestHandlerFactory = new RequestHandlerFactory();
    }

    public function tearDown(): void
    {
        unlink(static::TMP_MOCK_FILE);
    }

    /**
     * Test middleware execution with valid controller call
     */
    public function testMiddlewareExecution(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/subfolder/path');

        $responseFactory = $this->responseFactory;
        $requestHandler = $this->requestHandlerFactory->createRequestHandler(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($responseFactory) {
                return $responseFactory->createResponse()->withStatus(418); // I'm a tea pot
            }
        );

        $result = ($this->middleware)($request, $requestHandler);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(418, $result->getStatusCode());
    }

    /**
     * Test middleware execution with controller throwing a known front exception.
     * The exception should be thrown to be later handled by the error handler.
     */
    public function testMiddlewareExecutionWithFrontException(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/subfolder/path');

        $requestHandler = $this->requestHandlerFactory->createRequestHandler(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) {
                $exception = new LoginBannedException();
                throw new $exception();
            }
        );

        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder->method('render')->willReturnCallback(function (string $message): string {
            return $message;
        });
        $this->container->set('pageBuilder', $pageBuilder);

        $this->expectException(LoginBannedException::class);
        ($this->middleware)($request, $requestHandler);
    }

    /**
     * Test middleware execution with controller throwing a not authorized exception
     * The middle should send a redirection response to the login page.
     */
    public function testMiddlewareExecutionWithUnauthorizedException(): void
    {
        $serverParams = ['REQUEST_URI' => 'http://shaarli/subfolder/path'];
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', 'http://shaarli/subfolder/path', $serverParams);

        $requestHandler = $this->requestHandlerFactory->createRequestHandler(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) {
                throw new UnauthorizedException();
            }
        );

        $result = ($this->middleware)($request, $requestHandler);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(
            '/subfolder/login?returnurl=' . urlencode('http://shaarli/subfolder/path'),
            $result->getHeader('location')[0]
        );
    }

    /**
     * Test middleware execution with controller throwing a not authorized exception.
     * The exception should be thrown to be later handled by the error handler.
     */
    public function testMiddlewareExecutionWithServerException(): void
    {
        $serverParams = ['REQUEST_URI' => 'http://shaarli/subfolder/path'];
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', 'http://shaarli/subfolder/path', $serverParams);

        $dummyException = new class () extends \Exception {
        };
        $requestHandler = $this->requestHandlerFactory->createRequestHandler(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($dummyException) {
                throw $dummyException;
            }
        );

        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder->method('render')->willReturnCallback(function (string $message): string {
            return $message;
        });
        $parameters = [];
        $pageBuilder
            ->method('assign')
            ->willReturnCallback(function (string $key, string $value) use (&$parameters): void {
                $parameters[$key] = $value;
            })
        ;
        $this->container->set('pageBuilder', $pageBuilder);

        $this->expectException(get_class($dummyException));
        ($this->middleware)($request, $requestHandler);
    }

    public function testMiddlewareExecutionWithUpdates(): void
    {
        $serverParams = ['REQUEST_URI' => 'http://shaarli/subfolder/path'];
        $request = $this->serverRequestFactory
            ->createServerRequest('GET', 'http://shaarli/subfolder/path', $serverParams);

        $responseFactory = $this->responseFactory;
        $requestHandler = $this->requestHandlerFactory->createRequestHandler(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($responseFactory) {
                return $responseFactory->createResponse()->withStatus(418); // I'm a tea pot;
            }
        );

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $key): string {
            return $key;
        });
        $this->container->get('conf')->method('getConfigFileExt')->willReturn(static::TMP_MOCK_FILE);

        $this->container->set('pageCacheManager', $this->createMock(PageCacheManager::class));
        $this->container->get('pageCacheManager')->expects(static::once())->method('invalidateCaches');

        $this->container->set('updater', $this->createMock(Updater::class));
        $this->container->get('updater')
            ->expects(static::once())
            ->method('update')
            ->willReturn(['update123'])
        ;
        $this->container->get('updater')->method('getDoneUpdates')->willReturn($updates = ['update123', 'other']);
        $this->container->get('updater')
            ->expects(static::once())
            ->method('writeUpdates')
            ->with('resource.updates', $updates)
        ;

        $result = ($this->middleware)($request, $requestHandler);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(418, $result->getStatusCode());
    }
}

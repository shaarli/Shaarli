<?php

declare(strict_types=1);

namespace Shaarli\Front;

use DI\Container as DIContainer;
use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\LoginBannedException;
use Shaarli\Front\Exception\UnauthorizedException;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\FakeRequestHandler;
use Shaarli\Updater\Updater;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class ShaarliMiddlewareTest extends TestCase
{
    protected const TMP_MOCK_FILE = '.tmp';

    /** @var Container */
    protected $container;

    /** @var ShaarliMiddleware  */
    protected $middleware;

    public function setUp(): void
    {
        $this->container = new DIContainer();

        touch(static::TMP_MOCK_FILE);

        $this->container->set('conf', $this->createMock(ConfigManager::class));

        $conf = $this->container->get('conf');
        $conf->method('getConfigFileExt')->willReturn(static::TMP_MOCK_FILE);

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->set('basePath', '/subfolder');

        $this->middleware = new ShaarliMiddleware($this->container);
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
        $request = new FakeRequest(
            'GET',
            (new Uri('http', 'shaarli'))->withPath('/subfolder/path')
        );

        $fakeResponse = (new SlimResponse())->withStatus(418); // I'm a tea pot
        $result = ($this->middleware)($request, new FakeRequestHandler($fakeResponse));

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(418, $result->getStatusCode());
    }

    /**
     * Test middleware execution with controller throwing a known front exception.
     * The exception should be thrown to be later handled by the error handler.
     */
    public function testMiddlewareExecutionWithFrontException(): void
    {
        $request = new FakeRequest(
            'GET',
            (new Uri('http', 'shaarli'))->withPath('/subfolder/path')
        );

        $fakeResponse = (new SlimResponse())->withStatus(418); // I'm a tea pot
        $callback = function ($request) {
            $exception = new LoginBannedException();

            throw new $exception();
        };

        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder->method('render')->willReturnCallback(function (string $message): string {
            return $message;
        });
        $this->container->set('pageBuilder', $pageBuilder);

        $this->expectException(LoginBannedException::class);
        ($this->middleware)($request, new FakeRequestHandler($fakeResponse, $callback));
    }

    /**
     * Test middleware execution with controller throwing a not authorized exception
     * The middle should send a redirection response to the login page.
     */
    public function testMiddlewareExecutionWithUnauthorizedException(): void
    {
        $request = new FakeRequest(
            'GET',
            (new Uri('http', 'shaarli'))->withPath('/subfolder/path'),
            null,
            [],
            ['REQUEST_URI' => 'http://shaarli/subfolder/path']
        );

        $fakeResponse = new SlimResponse();
        $callback = function ($request) {
            throw new UnauthorizedException();
        };

        $result = ($this->middleware)($request, new FakeRequestHandler($fakeResponse, $callback));

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
        $request = new FakeRequest(
            'GET',
            (new Uri('http', 'shaarli'))->withPath('/subfolder/path'),
            null,
            [],
            ['REQUEST_URI' => 'http://shaarli/subfolder/path']
        );

        $fakeResponse = new SlimResponse();
        $dummyException = new class () extends \Exception {
        };
        $callback = function ($request) use ($dummyException) {
            throw $dummyException;
        };

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
        ($this->middleware)($request, new FakeRequestHandler($fakeResponse, $callback));
    }

    public function testMiddlewareExecutionWithUpdates(): void
    {
        $request = new FakeRequest(
            'GET',
            (new Uri('http', 'shaarli'))->withPath('/subfolder/path'),
            null,
            [],
            ['REQUEST_URI' => 'http://shaarli/subfolder/path']
        );

        $fakeResponse = (new SlimResponse())->withStatus(418); // I'm a tea pot;

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

        $result = ($this->middleware)($request, new FakeRequestHandler($fakeResponse));

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(418, $result->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front;

use DI\Container as DIContainer;
use Shaarli\Config\ConfigManager;
use Shaarli\Security\LoginManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\FakeRequestHandler;
use Shaarli\Updater\Updater;
use Slim\Http\Uri;
use Slim\Psr7\Response as SlimResponse;

class ShaarliAdminMiddlewareTest extends TestCase
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
        $this->container->get('conf')->method('getConfigFileExt')->willReturn(static::TMP_MOCK_FILE);

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->set('updater', $this->createMock(Updater::class));
        $this->container->set('basePath', '/subfolder');

        $this->middleware = new ShaarliAdminMiddleware($this->container);
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

        $request = new FakeRequest(
            'GET',
            (new \Slim\Psr7\Uri('http', 'shaarli'))->withPath('/subfolder/path'),
            null,
            [],
            ['REQUEST_URI' => 'http://shaarli/subfolder/path']
        );

        $fakeResponse = new SlimResponse();
        $result = ($this->middleware)($request, new FakeRequestHandler($fakeResponse));

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

        $request = new FakeRequest(
            'GET',
            (new \Slim\Psr7\Uri('http', 'shaarli'))->withPath('/subfolder/path'),
            null,
            [],
            ['REQUEST_URI' => 'http://shaarli/subfolder/path']
        );

        $fakeResponse = (new SlimResponse())->withStatus(418);
        $result = ($this->middleware)($request, new FakeRequestHandler($fakeResponse));


        static::assertSame(418, $result->getStatusCode());
    }
}

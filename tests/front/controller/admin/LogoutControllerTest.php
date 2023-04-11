<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\Security\CookieManager;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;

class LogoutControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var LogoutController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new LogoutController($this->container);
    }

    public function testValidControllerInvoke(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('pageCacheManager')->expects(static::once())->method('invalidateCaches');

        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')->expects(static::once())->method('logout');

        $this->container->set('cookieManager', $this->createMock(CookieManager::class));
        $this->container->get('cookieManager')
            ->expects(static::once())
            ->method('setCookieParameter')
            ->with(CookieManager::STAY_SIGNED_IN, 'false', 0, '/subfolder/')
        ;

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}

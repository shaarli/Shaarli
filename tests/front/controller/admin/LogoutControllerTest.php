<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

/** Override PHP builtin setcookie function in the local namespace to mock it... more or less */
if (!function_exists('Shaarli\Front\Controller\Admin\setcookie')) {
    function setcookie(string $name, string $value): void {
        $_COOKIE[$name] = $value;
    }
}

use PHPUnit\Framework\TestCase;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

class LogoutControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var LogoutController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new LogoutController($this->container);

        setcookie(LoginManager::$STAY_SIGNED_IN_COOKIE, $cookie = 'hi there');
    }

    public function testValidControllerInvoke(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->pageCacheManager->expects(static::once())->method('invalidateCaches');

        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->expects(static::once())->method('logout');

        static::assertSame('hi there', $_COOKIE[LoginManager::$STAY_SIGNED_IN_COOKIE]);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
        static::assertSame('false', $_COOKIE[LoginManager::$STAY_SIGNED_IN_COOKIE]);
    }
}

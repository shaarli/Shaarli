<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

/** Override PHP builtin setcookie function in the local namespace to mock it... more or less */
if (!function_exists('Shaarli\Front\Controller\setcookie')) {
    function setcookie(string $name, string $value): void {
        $_COOKIE[$name] = $value;
    }
}

use PHPUnit\Framework\TestCase;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

class LogoutControllerTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var LogoutController */
    protected $controller;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->controller = new LogoutController($this->container);

        setcookie(LoginManager::$STAY_SIGNED_IN_COOKIE, $cookie = 'hi there');
    }

    public function testValidControllerInvoke(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $pageCacheManager = $this->createMock(PageCacheManager::class);
        $pageCacheManager->expects(static::once())->method('invalidateCaches');
        $this->container->pageCacheManager = $pageCacheManager;

        $sessionManager = $this->createMock(SessionManager::class);
        $sessionManager->expects(static::once())->method('logout');
        $this->container->sessionManager = $sessionManager;

        static::assertSame('hi there', $_COOKIE[LoginManager::$STAY_SIGNED_IN_COOKIE]);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertContains('./', $result->getHeader('Location'));
        static::assertSame('false', $_COOKIE[LoginManager::$STAY_SIGNED_IN_COOKIE]);
    }
}

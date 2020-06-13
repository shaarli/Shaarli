<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\LoginBannedException;
use Slim\Http\Request;
use Slim\Http\Response;

class LoginControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var LoginController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new LoginController($this->container);
    }

    public function testValidControllerInvoke(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects(static::once())->method('getServerParam')->willReturn('> referer');
        $response = new Response();

        $assignedVariables = [];
        $this->container->pageBuilder
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$assignedVariables) {
                $assignedVariables[$key] = $value;

                return $this;
            })
        ;

        $this->container->loginManager->method('canLogin')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(200, $result->getStatusCode());
        static::assertSame('loginform', (string) $result->getBody());

        static::assertSame('&gt; referer', $assignedVariables['returnurl']);
        static::assertSame(true, $assignedVariables['remember_user_default']);
        static::assertSame('Login - Shaarli', $assignedVariables['pagetitle']);
    }

    public function testValidControllerInvokeWithUserName(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects(static::once())->method('getServerParam')->willReturn('> referer');
        $request->expects(static::exactly(2))->method('getParam')->willReturn('myUser>');
        $response = new Response();

        $assignedVariables = [];
        $this->container->pageBuilder
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$assignedVariables) {
                $assignedVariables[$key] = $value;

                return $this;
            })
        ;

        $this->container->loginManager->expects(static::once())->method('canLogin')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(200, $result->getStatusCode());
        static::assertSame('loginform', (string) $result->getBody());

        static::assertSame('myUser&gt;', $assignedVariables['username']);
        static::assertSame('&gt; referer', $assignedVariables['returnurl']);
        static::assertSame(true, $assignedVariables['remember_user_default']);
        static::assertSame('Login - Shaarli', $assignedVariables['pagetitle']);
    }

    public function testLoginControllerWhileLoggedIn(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->loginManager->expects(static::once())->method('isLoggedIn')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('Location'));
    }

    public function testLoginControllerOpenShaarli(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $conf = $this->createMock(ConfigManager::class);
        $conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'security.open_shaarli') {
                return true;
            }
            return $default;
        });
        $this->container->conf = $conf;

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('Location'));
    }

    public function testLoginControllerWhileBanned(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->loginManager->method('isLoggedIn')->willReturn(false);
        $this->container->loginManager->method('canLogin')->willReturn(false);

        $this->expectException(LoginBannedException::class);

        $this->controller->index($request, $response);
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\OpenShaarliPasswordException;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

class PasswordControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var PasswordController */
    protected $controller;

    /** @var mixed[] Variables assigned to the template */
    protected $assignedVariables = [];

    public function setUp(): void
    {
        $this->createContainer();
        $this->assignTemplateVars($this->assignedVariables);

        $this->controller = new PasswordController($this->container);
    }

    /**
     * Test displaying the change password page.
     */
    public function testGetPage(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('changepassword', (string) $result->getBody());
        static::assertSame('Change password - Shaarli', $this->assignedVariables['pagetitle']);
    }

    /**
     * Change the password with valid parameters
     */
    public function testPostNewPasswordDefault(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(function (string $key): string {
             if ('oldpassword' === $key) {
                 return 'old';
             }
             if ('setpassword' === $key) {
                 return 'new';
             }

             return $key;
        });
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key, $default) {
            if ('credentials.hash' === $key) {
                return sha1('old' . 'credentials.login' . 'credentials.salt');
            }

            return strpos($key, 'credentials') !== false ? $key : $default;
        });
        $this->container->conf->expects(static::once())->method('write')->with(true);

        $this->container->conf
            ->method('set')
            ->willReturnCallback(function (string $key, string $value) {
                if ('credentials.hash' === $key) {
                    static::assertSame(sha1('new' . 'credentials.login' . 'credentials.salt'), $value);
                }
            })
        ;

        $result = $this->controller->change($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('changepassword', (string) $result->getBody());
        static::assertSame('Change password - Shaarli', $this->assignedVariables['pagetitle']);
    }

    /**
     * Change the password with a wrong existing password
     */
    public function testPostNewPasswordWrongOldPassword(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(function (string $key): string {
            if ('oldpassword' === $key) {
                return 'wrong';
            }
            if ('setpassword' === $key) {
                return 'new';
            }

            return $key;
        });
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key, $default) {
            if ('credentials.hash' === $key) {
                return sha1('old' . 'credentials.login' . 'credentials.salt');
            }

            return strpos($key, 'credentials') !== false ? $key : $default;
        });

        $this->container->conf->expects(static::never())->method('set');
        $this->container->conf->expects(static::never())->method('write');

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['The old password is not correct.'])
        ;

        $result = $this->controller->change($request, $response);

        static::assertSame(400, $result->getStatusCode());
        static::assertSame('changepassword', (string) $result->getBody());
        static::assertSame('Change password - Shaarli', $this->assignedVariables['pagetitle']);
    }

    /**
     * Change the password with a wrong existing password
     */
    public function testPostNewPasswordWrongToken(): void
    {
        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(false);

        $this->container->conf->expects(static::never())->method('set');
        $this->container->conf->expects(static::never())->method('write');

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(WrongTokenException::class);

        $this->controller->change($request, $response);
    }

    /**
     * Change the password with an empty new password
     */
    public function testPostNewEmptyPassword(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['You must provide the current and new password to change it.'])
        ;

        $this->container->conf->expects(static::never())->method('set');
        $this->container->conf->expects(static::never())->method('write');

        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(function (string $key): string {
            if ('oldpassword' === $key) {
                return 'old';
            }
            if ('setpassword' === $key) {
                return '';
            }

            return $key;
        });
        $response = new Response();

        $result = $this->controller->change($request, $response);

        static::assertSame(400, $result->getStatusCode());
        static::assertSame('changepassword', (string) $result->getBody());
        static::assertSame('Change password - Shaarli', $this->assignedVariables['pagetitle']);
    }

    /**
     * Change the password on an open shaarli
     */
    public function testPostNewPasswordOnOpenShaarli(): void
    {
        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->with('security.open_shaarli')->willReturn(true);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(OpenShaarliPasswordException::class);

        $this->controller->change($request, $response);
    }
}

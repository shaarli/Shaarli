<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\LoginBannedException;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Render\TemplatePage;
use Shaarli\Security\CookieManager;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class LoginControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var LoginController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->set('cookieManager', $this->createMock(CookieManager::class));
        $this->container->get('sessionManager')->method('checkToken')->willReturn(true);

        $this->controller = new LoginController($this->container);
    }

    /**
     * Test displaying login form with valid parameters.
     */
    public function testValidControllerInvoke(): void
    {
        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(
                ['returnurl' => '> referer']
            ))
        ));
        $response = new SlimResponse();

        $assignedVariables = [];
        $this->container->get('pageBuilder')
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$assignedVariables) {
                $assignedVariables[$key] = $value;

                return $this;
            })
        ;

        $this->container->get('loginManager')->method('canLogin')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(200, $result->getStatusCode());
        static::assertSame(TemplatePage::LOGIN, (string) $result->getBody());

        static::assertSame('&gt; referer', $assignedVariables['returnurl']);
        static::assertSame(true, $assignedVariables['remember_user_default']);
        static::assertSame('Login - Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Test displaying login form with username defined in the request.
     */
    public function testValidControllerInvokeWithUserName(): void
    {

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(
                ['login' => 'myUser>']
            ))
        ))->withServerParams(['HTTP_REFERER' => '> referer']);

        $response = new SlimResponse();

        $assignedVariables = [];
        $this->container->get('pageBuilder')
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$assignedVariables) {
                $assignedVariables[$key] = $value;

                return $this;
            })
        ;

        $this->container->get('loginManager')->expects(static::once())->method('canLogin')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(200, $result->getStatusCode());
        static::assertSame('loginform', (string) $result->getBody());

        static::assertSame('myUser&gt;', $assignedVariables['username']);
        static::assertSame('&gt; referer', $assignedVariables['returnurl']);
        static::assertSame(true, $assignedVariables['remember_user_default']);
        static::assertSame('Login - Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Test displaying login page while being logged in.
     */
    public function testLoginControllerWhileLoggedIn(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('loginManager')->expects(static::once())->method('isLoggedIn')->willReturn(true);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('Location'));
    }

    /**
     * Test displaying login page with open shaarli configured: redirect to homepage.
     */
    public function testLoginControllerOpenShaarli(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $conf = $this->createMock(ConfigManager::class);
        $conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'security.open_shaarli') {
                return true;
            }
            return $default;
        });
        $this->container->set('conf', $conf);

        $result = $this->controller->index($request, $response);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('Location'));
    }

    /**
     * Test displaying login page while being banned.
     */
    public function testLoginControllerWhileBanned(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(false);
        $this->container->get('loginManager')->method('canLogin')->willReturn(false);

        $this->expectException(LoginBannedException::class);

        $this->controller->index($request, $response);
    }

    /**
     * Test processing login with valid parameters.
     */
    public function testProcessLoginWithValidParameters(): void
    {
        $parameters = [
            'login' => 'bob',
            'password' => 'pass',
        ];
        $request = (new FakeRequest())->withParsedBody($parameters)
            ->withServerParams([
                'SERVER_NAME' => 'shaarli',
                'SERVER_PORT' => '80',
                'REQUEST_URI' => '/subfolder/daily-rss',
                'REMOTE_ADDR' => '1.2.3.4',
                'SCRIPT_NAME' => '/subfolder/index.php',
            ]);

        $response = new SlimResponse();

        $this->container->get('loginManager')->method('canLogin')->willReturn(true);
        $this->container->get('loginManager')->expects(static::once())->method('handleSuccessfulLogin');
        $this->container->get('loginManager')
            ->expects(static::once())
            ->method('checkCredentials')
            ->with('1.2.3.4', 'bob', 'pass')
            ->willReturn(true)
        ;
        $this->container->get('loginManager')->method('getStaySignedInToken')->willReturn(bin2hex(random_bytes(8)));

        $this->container->get('sessionManager')->expects(static::never())->method('extendSession');
        $this->container->get('sessionManager')->expects(static::once())->method('destroy');
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('cookieParameters')
            ->with(0, '/subfolder/', 'shaarli')
        ;
        $this->container->get('sessionManager')->expects(static::once())->method('start');
        $this->container->get('sessionManager')->expects(static::once())->method('regenerateId')->with(true);

        $result = $this->controller->login($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/', $result->getHeader('location')[0]);
    }

    /**
     * Test processing login with return URL.
     */
    public function testProcessLoginWithReturnUrl(): void
    {
        $parameters = [
            'returnurl' => 'http://shaarli/subfolder/admin/shaare',
        ];
        $request = (new FakeRequest())->withParsedBody($parameters)
            ->withServerParams([
                'SERVER_NAME' => 'shaarli',
                'SERVER_PORT' => '80',
                'REQUEST_URI' => '/subfolder/daily-rss',
                'REMOTE_ADDR' => '1.2.3.4',
                'SCRIPT_NAME' => '/subfolder/index.php',
            ]);
        $response = new SlimResponse();

        $this->container->get('loginManager')->method('canLogin')->willReturn(true);
        $this->container->get('loginManager')->expects(static::once())->method('handleSuccessfulLogin');
        $this->container->get('loginManager')->expects(static::once())->method('checkCredentials')->willReturn(true);
        $this->container->get('loginManager')->method('getStaySignedInToken')->willReturn(bin2hex(random_bytes(8)));

        $result = $this->controller->login($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/admin/shaare', $result->getHeader('location')[0]);
    }

    /**
     * Test processing login with remember me session enabled.
     */
    public function testProcessLoginLongLastingSession(): void
    {
        $parameters = [
            'longlastingsession' => true,
        ];
        $request = (new FakeRequest())->withParsedBody($parameters)
            ->withServerParams([
                'SERVER_NAME' => 'shaarli',
                'SERVER_PORT' => '80',
                'REQUEST_URI' => '/subfolder/daily-rss',
                'REMOTE_ADDR' => '1.2.3.4',
                'SCRIPT_NAME' => '/subfolder/index.php',
            ]);
        $response = new SlimResponse();

        $this->container->get('loginManager')->method('canLogin')->willReturn(true);
        $this->container->get('loginManager')->expects(static::once())->method('handleSuccessfulLogin');
        $this->container->get('loginManager')->expects(static::once())->method('checkCredentials')->willReturn(true);
        $this->container->get('loginManager')->method('getStaySignedInToken')->willReturn(bin2hex(random_bytes(8)));

        $this->container->get('sessionManager')->expects(static::once())->method('destroy');
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('cookieParameters')
            ->with(42, '/subfolder/', 'shaarli')
        ;
        $this->container->get('sessionManager')->expects(static::once())->method('start');
        $this->container->get('sessionManager')->expects(static::once())->method('regenerateId')->with(true);
        $this->container->get('sessionManager')->expects(static::once())->method('extendSession')->willReturn(42);

        $this->container->set('cookieManager', $this->createMock(CookieManager::class));
        $this->container->get('cookieManager')
            ->expects(static::once())
            ->method('setCookieParameter')
            ->willReturnCallback(function (string $name): CookieManager {
                static::assertSame(CookieManager::STAY_SIGNED_IN, $name);

                return $this->container->get('cookieManager');
            })
        ;

        $result = $this->controller->login($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/', $result->getHeader('location')[0]);
    }

    /**
     * Test processing login with invalid credentials
     */
    public function testProcessLoginWrongCredentials(): void
    {
        $parameters = [
            'returnurl' => 'http://shaarli/subfolder/admin/shaare',
        ];
        $request = (new FakeRequest())->withParsedBody($parameters)
            ->withServerParams([
                'SERVER_NAME' => 'shaarli',
                'SERVER_PORT' => '80',
                'REQUEST_URI' => '/subfolder/daily-rss',
                'REMOTE_ADDR' => '1.2.3.4',
                'SCRIPT_NAME' => '/subfolder/index.php',
            ]);
        $response = new SlimResponse();

        $this->container->get('loginManager')->method('canLogin')->willReturn(true);
        $this->container->get('loginManager')->expects(static::once())->method('handleFailedLogin');
        $this->container->get('loginManager')->expects(static::once())->method('checkCredentials')->willReturn(false);

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['Wrong login/password.'])
        ;

        $result = $this->controller->login($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame(TemplatePage::LOGIN, (string) $result->getBody());
    }

    /**
     * Test processing login with wrong token
     */
    public function testProcessLoginWrongToken(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')->method('checkToken')->willReturn(false);

        $this->expectException(WrongTokenException::class);

        $this->controller->login($request, $response);
    }

    /**
     * Test processing login with wrong token
     */
    public function testProcessLoginAlreadyLoggedIn(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);
        $this->container->get('loginManager')->expects(static::never())->method('handleSuccessfulLogin');
        $this->container->get('loginManager')->expects(static::never())->method('handleFailedLogin');

        $result = $this->controller->login($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/', $result->getHeader('location')[0]);
    }

    /**
     * Test processing login with wrong token
     */
    public function testProcessLoginInOpenShaarli(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $key, $value) {
            return 'security.open_shaarli' === $key ? true : $value;
        });

        $this->container->get('loginManager')->expects(static::never())->method('handleSuccessfulLogin');
        $this->container->get('loginManager')->expects(static::never())->method('handleFailedLogin');

        $result = $this->controller->login($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/', $result->getHeader('location')[0]);
    }

    /**
     * Test processing login while being banned
     */
    public function testProcessLoginWhileBanned(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $this->container->get('loginManager')->method('canLogin')->willReturn(false);
        $this->container->get('loginManager')->expects(static::never())->method('handleSuccessfulLogin');
        $this->container->get('loginManager')->expects(static::never())->method('handleFailedLogin');

        $this->expectException(LoginBannedException::class);

        $this->controller->login($request, $response);
    }
}

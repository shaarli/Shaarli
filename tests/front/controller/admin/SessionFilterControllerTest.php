<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class SessionFilterControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var SessionFilterController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new SessionFilterController($this->container);
    }

    /**
     * Visibility - Default call for private filter while logged in without current value
     */
    public function testVisibility(): void
    {
        $arg = ['visibility' => 'private'];

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY, 'private')
        ;

        $serverParams = [
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc',
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ];
        $request = $this->serverRequestFactory->createServerRequest('POST', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->visibility($request, $response, $arg);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    /**
     * Visibility - Toggle off private visibility
     */
    public function testVisibilityToggleOff(): void
    {
        $arg = ['visibility' => 'private'];

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);
        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY)
            ->willReturn('private')
        ;
        $this->container->get('sessionManager')
            ->expects(static::never())
            ->method('setSessionParameter')
        ;
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('deleteSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY)
        ;

        $serverParams = [
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc',
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ];
        $request = $this->serverRequestFactory->createServerRequest('POST', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();
        $result = $this->controller->visibility($request, $response, $arg);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    /**
     * Visibility - Change private to public
     */
    public function testVisibilitySwitch(): void
    {
        $arg = ['visibility' => 'private'];

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);
        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY)
            ->willReturn('public')
        ;
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY, 'private')
        ;

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->visibility($request, $response, $arg);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Visibility - With invalid value - should remove any visibility setting
     */
    public function testVisibilityInvalidValue(): void
    {
        $arg = ['visibility' => 'test'];

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);
        $this->container->get('sessionManager')
            ->expects(static::never())
            ->method('setSessionParameter')
        ;
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('deleteSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY)
        ;

        $serverParams = [
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc',
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->visibility($request, $response, $arg);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    /**
     * Visibility - Try to change visibility while logged out
     */
    public function testVisibilityLoggedOut(): void
    {
        $arg = ['visibility' => 'test'];

        $this->container->set('loginManager', $this->createMock(LoginManager::class));
        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(false);
        $this->container->get('sessionManager')
            ->expects(static::never())
            ->method('setSessionParameter')
        ;
        $this->container->get('sessionManager')
            ->expects(static::never())
            ->method('deleteSessionParameter')
            ->with(SessionManager::KEY_VISIBILITY)
        ;

        $serverParams = [
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller/?searchtag=abc',
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->visibility($request, $response, $arg);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller/?searchtag=abc'], $result->getHeader('location'));
    }
}

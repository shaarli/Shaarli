<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Shaarli\Front\Exception\UnauthorizedException;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;

/**
 * Class ShaarliControllerTest
 *
 * This class is used to test default behavior of ShaarliAdminController abstract class.
 * It uses a dummy non abstract controller.
 */
class ShaarliAdminControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaarliAdminController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new class($this->container) extends ShaarliAdminController
        {
            public function checkToken(Request $request): bool
            {
                return parent::checkToken($request);
            }

            public function saveSuccessMessage(string $message): void
            {
                parent::saveSuccessMessage($message);
            }

            public function saveWarningMessage(string $message): void
            {
                parent::saveWarningMessage($message);
            }

            public function saveErrorMessage(string $message): void
            {
                parent::saveErrorMessage($message);
            }
        };
    }

    /**
     * Creating an instance of an admin controller while logged out should raise an exception.
     */
    public function testInstantiateWhileLoggedOut(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->container->loginManager = $this->createMock(LoginManager::class);
        $this->container->loginManager->method('isLoggedIn')->willReturn(false);

        $this->controller = new class($this->container) extends ShaarliAdminController {};
    }

    /**
     * Trigger controller's checkToken with a valid token.
     */
    public function testCheckTokenWithValidToken(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')->with('token')->willReturn($token = '12345');

        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->with($token)->willReturn(true);

        static::assertTrue($this->controller->checkToken($request));
    }

    /**
     * Trigger controller's checkToken with na valid token should raise an exception.
     */
    public function testCheckTokenWithNotValidToken(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')->with('token')->willReturn($token = '12345');

        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->with($token)->willReturn(false);

        $this->expectException(WrongTokenException::class);

        $this->controller->checkToken($request);
    }

    /**
     * Test saveSuccessMessage() with a first message.
     */
    public function testSaveSuccessMessage(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES, [$message = 'bravo!'])
        ;

        $this->controller->saveSuccessMessage($message);
    }

    /**
     * Test saveSuccessMessage() with existing messages.
     */
    public function testSaveSuccessMessageWithExistingMessages(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('getSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES)
            ->willReturn(['success1', 'success2'])
        ;
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES, ['success1', 'success2', $message = 'bravo!'])
        ;

        $this->controller->saveSuccessMessage($message);
    }

    /**
     * Test saveWarningMessage() with a first message.
     */
    public function testSaveWarningMessage(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_WARNING_MESSAGES, [$message = 'warning!'])
        ;

        $this->controller->saveWarningMessage($message);
    }

    /**
     * Test saveWarningMessage() with existing messages.
     */
    public function testSaveWarningMessageWithExistingMessages(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('getSessionParameter')
            ->with(SessionManager::KEY_WARNING_MESSAGES)
            ->willReturn(['warning1', 'warning2'])
        ;
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_WARNING_MESSAGES, ['warning1', 'warning2', $message = 'warning!'])
        ;

        $this->controller->saveWarningMessage($message);
    }

    /**
     * Test saveErrorMessage() with a first message.
     */
    public function testSaveErrorMessage(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, [$message = 'error!'])
        ;

        $this->controller->saveErrorMessage($message);
    }

    /**
     * Test saveErrorMessage() with existing messages.
     */
    public function testSaveErrorMessageWithExistingMessages(): void
    {
        $this->container->sessionManager
            ->expects(static::once())
            ->method('getSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES)
            ->willReturn(['error1', 'error2'])
        ;
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['error1', 'error2', $message = 'error!'])
        ;

        $this->controller->saveErrorMessage($message);
    }
}

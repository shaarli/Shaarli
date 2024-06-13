<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Front\Controller\Visitor\ShaarliVisitorController;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Security\SessionManager;

/**
 * Class ShaarliAdminController
 *
 * All admin controllers (for logged in users) MUST extend this abstract class.
 * It makes sure that the user is properly logged in, and otherwise throw an exception
 * which will redirect to the login page.
 *
 * @package Shaarli\Front\Controller\Admin
 */
abstract class ShaarliAdminController extends ShaarliVisitorController
{
    /**
     * Any persistent action to the config or data store must check the XSRF token validity.
     */
    protected function checkToken(Request $request): bool
    {
        $token = $request->getParsedBody()['token'] ?? $request->getQueryParams()['token'] ?? null;
        if (!$this->container->get('sessionManager')->checkToken($token)) {
            throw new WrongTokenException();
        }

        return true;
    }

    /**
     * Save a SUCCESS message in user session, which will be displayed on any template page.
     */
    protected function saveSuccessMessage(string $message): void
    {
        $this->saveMessage(SessionManager::KEY_SUCCESS_MESSAGES, $message);
    }

    /**
     * Save a WARNING message in user session, which will be displayed on any template page.
     */
    protected function saveWarningMessage(string $message): void
    {
        $this->saveMessage(SessionManager::KEY_WARNING_MESSAGES, $message);
    }

    /**
     * Save an ERROR message in user session, which will be displayed on any template page.
     */
    protected function saveErrorMessage(string $message): void
    {
        $this->saveMessage(SessionManager::KEY_ERROR_MESSAGES, $message);
    }

    /**
     * Use the sessionManager to save the provided message using the proper type.
     *
     * @param string $type successes/warnings/errors
     */
    protected function saveMessage(string $type, string $message): void
    {
        $messages = $this->container->get('sessionManager')->getSessionParameter($type) ?? [];
        $messages[] = $message;

        $this->container->get('sessionManager')->setSessionParameter($type, $messages);
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Security\LoginManager;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class LogoutController
 *
 * Slim controller used to logout the user.
 * It invalidates page cache and terminate the user session. Then it redirects to the homepage.
 */
class LogoutController extends ShaarliAdminController
{
    public function index(Request $request, Response $response): Response
    {
        $this->container->pageCacheManager->invalidateCaches();
        $this->container->sessionManager->logout();

        // TODO: switch to a simple Cookie manager allowing to check the session, and create mocks.
        setcookie(LoginManager::$STAY_SIGNED_IN_COOKIE, 'false', 0, $this->container->webPath);

        return $response->withRedirect('./');
    }
}

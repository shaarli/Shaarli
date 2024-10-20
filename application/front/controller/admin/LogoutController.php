<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Security\CookieManager;

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
        $this->container->get('pageCacheManager')->invalidateCaches();
        $this->container->get('sessionManager')->logout();
        $this->container->get('cookieManager')->setCookieParameter(
            CookieManager::STAY_SIGNED_IN,
            'false',
            0,
            $this->container->get('basePath') . '/'
        );

        return $this->redirect($response, '/');
    }
}

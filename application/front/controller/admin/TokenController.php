<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class TokenController
 *
 * Endpoint used to retrieve a XSRF token. Useful for AJAX requests.
 */
class TokenController extends ShaarliAdminController
{
    /**
     * GET /admin/token
     */
    public function getToken(Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', 'text/plain');

        return $response->write($this->container->sessionManager->generateToken());
    }
}

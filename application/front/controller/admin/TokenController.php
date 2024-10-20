<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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

        return $this->respondWithBody($response, $this->container->get('sessionManager')->generateToken());
    }
}

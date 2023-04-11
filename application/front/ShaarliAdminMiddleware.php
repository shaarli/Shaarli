<?php

namespace Shaarli\Front;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Middleware used for controller requiring to be authenticated.
 * It extends ShaarliMiddleware, and just make sure that the user is authenticated.
 * Otherwise, it redirects to the login page.
 */
class ShaarliAdminMiddleware extends ShaarliMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $this->initBasePath($request);

        if (true !== $this->container->get('loginManager')->isLoggedIn()) {
            $returnUrl = urlencode($request->getServerParams()['REQUEST_URI'] ?? null);

            return $this->redirect($this->container->get('basePath') . '/login?returnurl=' . $returnUrl);
        }

        return parent::__invoke($request, $handler);
    }
}

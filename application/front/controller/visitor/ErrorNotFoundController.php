<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Render\TemplatePage;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

/**
 * Controller used to render the 404 error page.
 */
class ErrorNotFoundController extends ShaarliVisitorController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $response = new SlimResponse();
        // Request from the API
        if (false !== strpos($request->getRequestTarget(), '/api/v1')) {
            return $response->withStatus(404);
        }
        $basePathFromRequest = $request->getAttribute(RouteContext::BASE_PATH);

        // This is required because the middleware is ignored if the route is not found.
        $this->container->set('basePath', rtrim($basePathFromRequest, '/'));

        $this->assignView('error_message', t('Requested page could not be found.'));

        $response = $response->withStatus(404);
        return $this->respondWithTemplate($response, TemplatePage::ERROR_404);
    }
}

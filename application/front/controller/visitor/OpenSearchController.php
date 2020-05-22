<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class OpenSearchController
 *
 * Slim controller used to render open search template.
 * This allows to add Shaarli as a search engine within the browser.
 */
class OpenSearchController extends ShaarliVisitorController
{
    public function index(Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', 'application/opensearchdescription+xml; charset=utf-8');

        $this->assignView('serverurl', index_url($this->container->environment));

        return $response->write($this->render('opensearch'));
    }
}

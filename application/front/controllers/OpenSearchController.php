<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class OpenSearchController
 *
 * Slim controller used to render open search template.
 * This allows to add Shaarli as a search engine within the browser.
 *
 * @package front\controllers
 */
class OpenSearchController extends ShaarliController
{
    public function index(Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', 'application/opensearchdescription+xml; charset=utf-8');

        $this->assignView('serverurl', index_url($this->container->environment));

        return $response->write($this->render('opensearch'));
    }
}

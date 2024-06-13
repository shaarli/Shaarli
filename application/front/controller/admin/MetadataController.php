<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controller used to retrieve/update bookmark's metadata.
 */
class MetadataController extends ShaarliAdminController
{
    /**
     * GET /admin/metadata/{url} - Attempt to retrieve the bookmark title from provided URL.
     */
    public function ajaxRetrieveTitle(Request $request, Response $response): Response
    {
        $url = $request->getQueryParams()['url'] ?? null;

        // Only try to extract metadata from URL with HTTP(s) scheme
        if (!empty($url) && strpos(get_url_scheme($url) ?: '', 'http') !== false) {
            return $this->respondWithJson($response, $this->container->get('metadataRetriever')->retrieve($url));
        }

        return $this->respondWithJson($response, []);
    }
}

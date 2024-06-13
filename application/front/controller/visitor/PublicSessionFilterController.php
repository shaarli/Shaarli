<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Security\SessionManager;

/**
 * Slim controller used to handle filters stored in the visitor session, links per page, etc.
 */
class PublicSessionFilterController extends ShaarliVisitorController
{
    /**
     * GET /links-per-page: set the number of bookmarks to display per page in homepage
     */
    public function linksPerPage(Request $request, Response $response): Response
    {
        $linksPerPage = $request->getQueryParams()['nb'] ?? null;
        if (null === $linksPerPage || false === is_numeric($linksPerPage)) {
            $linksPerPage = $this->container->get('conf')->get('general.links_per_page', 20);
        }

        $this->container->get('sessionManager')->setSessionParameter(
            SessionManager::KEY_LINKS_PER_PAGE,
            abs(intval($linksPerPage))
        );

        return $this->redirectFromReferer($request, $response, ['linksperpage'], ['nb']);
    }

    /**
     * GET /untagged-only: allows to display only bookmarks without any tag
     */
    public function untaggedOnly(Request $request, Response $response): Response
    {
        $this->container->get('sessionManager')->setSessionParameter(
            SessionManager::KEY_UNTAGGED_ONLY,
            empty($this->container->get('sessionManager')->getSessionParameter(SessionManager::KEY_UNTAGGED_ONLY))
        );

        return $this->redirectFromReferer($request, $response, ['untaggedonly', 'untagged-only']);
    }
}

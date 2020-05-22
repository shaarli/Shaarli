<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class SessionFilterController
 *
 * Slim controller used to handle filters stored in the user session, such as visibility, links per page, etc.
 */
class SessionFilterController extends ShaarliAdminController
{
    /**
     * GET /links-per-page: set the number of bookmarks to display per page in homepage
     */
    public function linksPerPage(Request $request, Response $response): Response
    {
        $linksPerPage = $request->getParam('nb') ?? null;
        if (null === $linksPerPage || false === is_numeric($linksPerPage)) {
            $linksPerPage = $this->container->conf->get('general.links_per_page', 20);
        }

        $this->container->sessionManager->setSessionParameter(
            SessionManager::KEY_LINKS_PER_PAGE,
            abs(intval($linksPerPage))
        );

        return $this->redirectFromReferer($request, $response, ['linksperpage'], ['nb']);
    }

    /**
     * GET /visibility: allows to display only public or only private bookmarks in linklist
     */
    public function visibility(Request $request, Response $response, array $args): Response
    {
        if (false === $this->container->loginManager->isLoggedIn()) {
            return $this->redirectFromReferer($request, $response, ['visibility']);
        }

        $newVisibility = $args['visibility'] ?? null;
        if (false === in_array($newVisibility, [BookmarkFilter::$PRIVATE, BookmarkFilter::$PUBLIC], true)) {
            $newVisibility = null;
        }

        $currentVisibility = $this->container->sessionManager->getSessionParameter(SessionManager::KEY_VISIBILITY);

        // Visibility not set or not already expected value, set expected value, otherwise reset it
        if ($newVisibility !== null && (null === $currentVisibility || $currentVisibility !== $newVisibility)) {
            // See only public bookmarks
            $this->container->sessionManager->setSessionParameter(
                SessionManager::KEY_VISIBILITY,
                $newVisibility
            );
        } else {
            $this->container->sessionManager->deleteSessionParameter(SessionManager::KEY_VISIBILITY);
        }

        return $this->redirectFromReferer($request, $response, ['visibility']);
    }

    /**
     * GET /untagged-only: allows to display only bookmarks without any tag
     */
    public function untaggedOnly(Request $request, Response $response): Response
    {
        $this->container->sessionManager->setSessionParameter(
            SessionManager::KEY_UNTAGGED_ONLY,
            empty($this->container->sessionManager->getSessionParameter(SessionManager::KEY_UNTAGGED_ONLY))
        );

        return $this->redirectFromReferer($request, $response, ['untaggedonly', 'untagged-only']);
    }
}

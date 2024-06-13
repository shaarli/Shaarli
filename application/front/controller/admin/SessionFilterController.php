<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Security\SessionManager;

/**
 * Class SessionFilterController
 *
 * Slim controller used to handle filters stored in the user session, such as visibility, etc.
 */
class SessionFilterController extends ShaarliAdminController
{
    /**
     * GET /admin/visibility: allows to display only public or only private bookmarks in linklist
     */
    public function visibility(Request $request, Response $response, array $args): Response
    {
        if (false === $this->container->get('loginManager')->isLoggedIn()) {
            return $this->redirectFromReferer($request, $response, ['visibility']);
        }

        $newVisibility = $args['visibility'] ?? null;
        if (false === in_array($newVisibility, [BookmarkFilter::$PRIVATE, BookmarkFilter::$PUBLIC], true)) {
            $newVisibility = null;
        }

        $currentVisibility = $this->container->get('sessionManager')
            ->getSessionParameter(SessionManager::KEY_VISIBILITY);

        // Visibility not set or not already expected value, set expected value, otherwise reset it
        if ($newVisibility !== null && (null === $currentVisibility || $currentVisibility !== $newVisibility)) {
            // See only public bookmarks
            $this->container->get('sessionManager')->setSessionParameter(
                SessionManager::KEY_VISIBILITY,
                $newVisibility
            );
        } else {
            $this->container->get('sessionManager')->deleteSessionParameter(SessionManager::KEY_VISIBILITY);
        }

        return $this->redirectFromReferer($request, $response, ['visibility']);
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Plugin\ReadItLater;

use Shaarli\Front\Controller\Admin\ShaarliAdminController;
use Slim\Http\Request;
use Slim\Http\Response;

class ReadItLaterController extends ShaarliAdminController
{
    /**
     * GET /plugin/readitlater/bookmarks
     */
    public function toggleFilterBookmarkList(Request $request, Response $response): Response
    {
        $this->container->sessionManager->setSessionParameter(
            'readitlater-only',
            !$this->container->sessionManager->getSessionParameter('readitlater-only', false)
        );

        return $this->redirectFromReferer($request, $response, ['readitlater']);
    }

    /**
     * GET /plugin/readitlater/toggle/:id
     */
    public function toggleBookmark(Request $request, Response $response, array $args): Response
    {
        if (!array_key_exists('id', $args) || !$this->container->bookmarkService->exists((int) $args['id'])) {
            $this->saveErrorMessage('Invalid ID provided.');

            return $this->redirectFromReferer($request, $response, ['readitlater']);
        }

        $bookmark = $this->container->bookmarkService->get((int) $args['id']);
        $bookmark->setAdditionalContentEntry(
            'readitlater',
            !$bookmark->getAdditionalContentEntry('readitlater', false)
        );
        $this->container->bookmarkService->save();

        return $this->redirectFromReferer($request, $response, ['readitlater']);
    }
}

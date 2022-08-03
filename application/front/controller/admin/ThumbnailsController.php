<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Render\TemplatePage;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ToolsController
 *
 * Slim controller used to handle thumbnails update.
 */
class ThumbnailsController extends ShaarliAdminController
{
    /**
     * GET /admin/thumbnails - Display thumbnails update page
     */
    public function index(Request $request, Response $response): Response
    {
        $ids = [];
        foreach ($this->container->bookmarkService->search()->getBookmarks() as $bookmark) {
            // A note or not HTTP(S)
            if ($bookmark->isNote() || !startsWith(strtolower($bookmark->getUrl()), 'http')) {
                continue;
            }

            $ids[] = $bookmark->getId();
        }

        $this->assignView('ids', $ids);
        $this->assignView(
            'pagetitle',
            t('Thumbnails update') . ' - ' . $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render(TemplatePage::THUMBNAILS));
    }

    /**
     * PATCH /admin/shaare/{id}/thumbnail-update - Route for AJAX calls
     */
    public function ajaxUpdate(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? '';

        if (false === ctype_digit($id)) {
            return $response->withStatus(400);
        }

        try {
            $bookmark = $this->container->bookmarkService->get((int) $id);
        } catch (BookmarkNotFoundException $e) {
            return $response->withStatus(404);
        }

        $bookmark->setThumbnail($this->container->thumbnailer->get($bookmark->getUrl()));
        $this->container->bookmarkService->set($bookmark);

        return $response->withJson($this->container->formatterFactory->getFormatter('raw')->format($bookmark));
    }
}

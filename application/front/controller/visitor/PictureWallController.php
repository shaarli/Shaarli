<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Front\Exception\ThumbnailsDisabledException;
use Shaarli\Render\TemplatePage;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class PicturesWallController
 *
 * Slim controller used to render the pictures wall page.
 * If thumbnails mode is set to NONE, we just render the template without any image.
 */
class PictureWallController extends ShaarliVisitorController
{
    public function index(Request $request, Response $response): Response
    {
        if ($this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) === Thumbnailer::MODE_NONE) {
            throw new ThumbnailsDisabledException();
        }

        $this->assignView(
            'pagetitle',
            t('Picture wall') . ' - ' . $this->container->conf->get('general.title', 'Shaarli')
        );

        // Optionally filter the results:
        $bookmarks = $this->container->bookmarkService->search($request->getQueryParams())->getBookmarks();
        $links = [];

        // Get only bookmarks which have a thumbnail.
        // Note: we do not retrieve thumbnails here, the request is too heavy.
        $formatter = $this->container->formatterFactory->getFormatter();
        foreach ($bookmarks as $key => $bookmark) {
            if (!empty($bookmark->getThumbnail())) {
                $links[] = $formatter->format($bookmark);
            }
        }

        $data = ['linksToDisplay' => $links];
        $this->executePageHooks('render_picwall', $data, TemplatePage::PICTURE_WALL);

        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        return $response->write($this->render(TemplatePage::PICTURE_WALL));
    }
}

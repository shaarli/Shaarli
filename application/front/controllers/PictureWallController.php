<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Shaarli\Front\Exception\ThumbnailsDisabledException;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class PicturesWallController
 *
 * Slim controller used to render the pictures wall page.
 * If thumbnails mode is set to NONE, we just render the template without any image.
 *
 * @package Front\Controller
 */
class PictureWallController extends ShaarliController
{
    public function index(Request $request, Response $response): Response
    {
        if ($this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) === Thumbnailer::MODE_NONE) {
            throw new ThumbnailsDisabledException();
        }

        $this->assignView(
            'pagetitle',
            t('Picture wall') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        // Optionally filter the results:
        $links = $this->container->bookmarkService->search($request->getQueryParams());
        $linksToDisplay = [];

        // Get only bookmarks which have a thumbnail.
        // Note: we do not retrieve thumbnails here, the request is too heavy.
        $formatter = $this->container->formatterFactory->getFormatter('raw');
        foreach ($links as $key => $link) {
            if (!empty($link->getThumbnail())) {
                $linksToDisplay[] = $formatter->format($link);
            }
        }

        $data = $this->executeHooks($linksToDisplay);
        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        return $response->write($this->render('picwall'));
    }

    /**
     * @param mixed[] $linksToDisplay List of formatted bookmarks
     *
     * @return mixed[] Template data after active plugins render_picwall hook execution.
     */
    protected function executeHooks(array $linksToDisplay): array
    {
        $data = [
            'linksToDisplay' => $linksToDisplay,
        ];
        $this->container->pluginManager->executeHooks(
            'render_picwall',
            $data,
            ['loggedin' => $this->container->loginManager->isLoggedIn()]
        );

        return $data;
    }
}

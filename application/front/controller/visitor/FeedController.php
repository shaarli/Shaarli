<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Feed\FeedBuilder;

/**
 * Class FeedController
 *
 * Slim controller handling ATOM and RSS feed.
 */
class FeedController extends ShaarliVisitorController
{
    public function atom(Request $request, Response $response): Response
    {
        return $this->processRequest(FeedBuilder::$FEED_ATOM, $request, $response);
    }

    public function rss(Request $request, Response $response): Response
    {
        return $this->processRequest(FeedBuilder::$FEED_RSS, $request, $response);
    }

    protected function processRequest(string $feedType, Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', 'application/' . $feedType . '+xml; charset=utf-8');

        $pageUrl = page_url($request->getServerParams());
        $cache = $this->container->get('pageCacheManager')->getCachePage($pageUrl);

        $cached = $cache->cachedVersion();
        if (!empty($cached)) {
            return $this->respondWithBody($response, $cached);
        }

        // Generate data.
        $this->container->get('feedBuilder')->setLocale(strtolower(get_locale(LC_COLLATE)));
        $this->container->get('feedBuilder')->setHideDates($this->container->get('conf')
            ->get('privacy.hide_timestamps', false));
        $this->container->get('feedBuilder')->setUsePermalinks(
            null !== ($request->getQueryParams()['permalinks'] ?? null)
            || !$this->container->get('conf')->get('feed.rss_permalinks')
        );

        $data = $this->container->get('feedBuilder')
            ->buildData($feedType, $request->getQueryParams(), $request->getServerParams());

        $this->executePageHooks('render_feed', $data, 'feed.' . $feedType);
        $this->assignAllView($data);

        $content = $this->render('feed.' . $feedType);

        $cache->cache($content);

        return $this->respondWithBody($response, $content);
    }
}

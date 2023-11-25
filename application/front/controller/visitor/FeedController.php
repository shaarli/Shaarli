<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Feed\FeedBuilder;
use Slim\Http\Request;
use Slim\Http\Response;

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

        $pageUrl = page_url($this->container->environment);
        $cache = $this->container->pageCacheManager->getCachePage($pageUrl);

        $cached = $cache->cachedVersion();
        if (!empty($cached)) {
            return $response->write($cached);
        }

        // Generate data.
        $this->container->feedBuilder->setLocale(strtolower(get_locale(LC_COLLATE)));
        $this->container->feedBuilder->setHideDates($this->container->conf->get('privacy.hide_timestamps', false));
        $this->container->feedBuilder->setUsePermalinks(
            null !== $request->getParam('permalinks') || !$this->container->conf->get('feed.rss_permalinks')
        );

        $data = $this->container->feedBuilder->buildData($feedType, $request->getParams());

        $this->executePageHooks('render_feed', $data, 'feed.' . $feedType);
        $this->assignAllView($data);

        $content = $this->render('feed.' . $feedType);

        $cache->cache($content);

        return $response->write($content);
    }
}

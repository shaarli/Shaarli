<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class TagCloud
 *
 * Slim controller used to render the tag cloud and tag list pages.
 *
 * @package Front\Controller
 */
class TagCloudController extends ShaarliController
{
    protected const TYPE_CLOUD = 'cloud';
    protected const TYPE_LIST = 'list';

    /**
     * Display the tag cloud through the template engine.
     * This controller a few filters:
     *   - Visibility stored in the session for logged in users
     *   - `searchtags` query parameter: will return tags associated with filter in at least one bookmark
     */
    public function cloud(Request $request, Response $response): Response
    {
        return $this->processRequest(static::TYPE_CLOUD, $request, $response);
    }

    /**
     * Display the tag list through the template engine.
     * This controller a few filters:
     *   - Visibility stored in the session for logged in users
     *   - `searchtags` query parameter: will return tags associated with filter in at least one bookmark
     *   - `sort` query parameters:
     *       + `usage` (default): most used tags first
     *       + `alpha`: alphabetical order
     */
    public function list(Request $request, Response $response): Response
    {
        return $this->processRequest(static::TYPE_LIST, $request, $response);
    }

    /**
     * Process the request for both tag cloud and tag list endpoints.
     */
    protected function processRequest(string $type, Request $request, Response $response): Response
    {
        if ($this->container->loginManager->isLoggedIn() === true) {
            $visibility = $this->container->sessionManager->getSessionParameter('visibility');
        }

        $sort = $request->getQueryParam('sort');
        $searchTags = $request->getQueryParam('searchtags');
        $filteringTags = $searchTags !== null ? explode(' ', $searchTags) : [];

        $tags = $this->container->bookmarkService->bookmarksCountPerTag($filteringTags, $visibility ?? null);

        if (static::TYPE_CLOUD === $type || 'alpha' === $sort) {
            // TODO: the sorting should be handled by bookmarkService instead of the controller
            alphabetical_sort($tags, false, true);
        }

        if (static::TYPE_CLOUD === $type) {
            $tags = $this->formatTagsForCloud($tags);
        }

        $searchTags = implode(' ', escape($filteringTags));
        $data = [
            'search_tags' => $searchTags,
            'tags' => $tags,
        ];
        $data = $this->executeHooks('tag' . $type, $data);
        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $searchTags = !empty($searchTags) ? $searchTags .' - ' : '';
        $this->assignView(
            'pagetitle',
            $searchTags . t('Tag '. $type) .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render('tag.'. $type));
    }

    /**
     * Format the tags array for the tag cloud template.
     *
     * @param array<string, int> $tags List of tags as key with count as value
     *
     * @return mixed[] List of tags as key, with count and expected font size in a subarray
     */
    protected function formatTagsForCloud(array $tags): array
    {
        // We sort tags alphabetically, then choose a font size according to count.
        // First, find max value.
        $maxCount = count($tags) > 0 ? max($tags) : 0;
        $logMaxCount = $maxCount > 1 ? log($maxCount, 30) : 1;
        $tagList = [];
        foreach ($tags as $key => $value) {
            // Tag font size scaling:
            //   default 15 and 30 logarithm bases affect scaling,
            //   2.2 and 0.8 are arbitrary font sizes in em.
            $size = log($value, 15) / $logMaxCount * 2.2 + 0.8;
            $tagList[$key] = [
                'count' => $value,
                'size' => number_format($size, 2, '.', ''),
            ];
        }

        return $tagList;
    }

    /**
     * @param mixed[] $data Template data
     *
     * @return mixed[] Template data after active plugins hook execution.
     */
    protected function executeHooks(string $template, array $data): array
    {
        $this->container->pluginManager->executeHooks(
            'render_'. $template,
            $data,
            ['loggedin' => $this->container->loginManager->isLoggedIn()]
        );

        return $data;
    }
}

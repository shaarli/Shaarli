<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class TagCloud
 *
 * Slim controller used to render the tag cloud page.
 *
 * @package Front\Controller
 */
class TagCloudController extends ShaarliController
{
    public function index(Request $request, Response $response): Response
    {
        if ($this->container->loginManager->isLoggedIn() === true) {
            $visibility = $this->container->sessionManager->getSessionParameter('visibility');
        }

        $searchTags = $request->getQueryParam('searchtags');
        $filteringTags = $searchTags !== null ? explode(' ', $searchTags) : [];

        $tags = $this->container->bookmarkService->bookmarksCountPerTag($filteringTags, $visibility ?? null);

        // We sort tags alphabetically, then choose a font size according to count.
        // First, find max value.
        $maxCount = 0;
        foreach ($tags as $count) {
            $maxCount = max($maxCount, $count);
        }

        alphabetical_sort($tags, false, true);

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

        $searchTags = implode(' ', escape($filteringTags));
        $data = [
            'search_tags' => $searchTags,
            'tags' => $tagList,
        ];
        $data = $this->executeHooks($data);
        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $searchTags = !empty($searchTags) ? $searchTags .' - ' : '';
        $this->assignView(
            'pagetitle',
            $searchTags. t('Tag cloud') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render('tag.cloud'));
    }

    /**
     * @param mixed[] $data Template data
     *
     * @return mixed[] Template data after active plugins render_picwall hook execution.
     */
    protected function executeHooks(array $data): array
    {
        $this->container->pluginManager->executeHooks(
            'render_tagcloud',
            $data,
            ['loggedin' => $this->container->loginManager->isLoggedIn()]
        );

        return $data;
    }
}

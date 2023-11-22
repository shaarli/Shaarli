<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Legacy\LegacyController;
use Shaarli\Legacy\UnknowLegacyRouteException;
use Shaarli\Render\TemplatePage;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class BookmarkListController
 *
 * Slim controller used to render the bookmark list, the home page of Shaarli.
 * It also displays permalinks, and process legacy routes based on GET parameters.
 */
class BookmarkListController extends ShaarliVisitorController
{
    /**
     * GET / - Displays the bookmark list, with optional filter parameters.
     */
    public function index(Request $request, Response $response): Response
    {
        $legacyResponse = $this->processLegacyController($request, $response);
        if (null !== $legacyResponse) {
            return $legacyResponse;
        }

        $formatter = $this->container->formatterFactory->getFormatter();
        $formatter->addContextData('base_path', $this->container->basePath);
        $formatter->addContextData('index_url', index_url($this->container->environment));

        $searchTags = normalize_spaces($request->getParam('searchtags') ?? '');
        $searchTerm = escape(normalize_spaces($request->getParam('searchterm') ?? ''));

        // Filter bookmarks according search parameters.
        $visibility = $this->container->sessionManager->getSessionParameter('visibility');
        $search = [
            'searchtags' => $searchTags,
            'searchterm' => $searchTerm,
        ];

        // Select articles according to paging.
        $page = (int) ($request->getParam('page') ?? 1);
        $page = $page < 1 ? 1 : $page;
        $linksPerPage = $this->container->sessionManager->getSessionParameter('LINKS_PER_PAGE', 20) ?: 20;

        $searchResult = $this->container->bookmarkService->search(
            $search,
            $visibility,
            false,
            !!$this->container->sessionManager->getSessionParameter('untaggedonly'),
            false,
            ['offset' => $linksPerPage * ($page - 1), 'limit' => $linksPerPage]
        ) ?? [];

        $save = false;
        $links = [];
        foreach ($searchResult->getBookmarks() as $key => $bookmark) {
            $save = $this->updateThumbnail($bookmark, false) || $save;
            $links[$key] = $formatter->format($bookmark);
        }

        if ($save) {
            $this->container->bookmarkService->save();
        }

        // Compute paging navigation
        $searchtagsUrl = $searchTags === '' ? '' : '&searchtags=' . urlencode($searchTags);
        $searchtermUrl = $searchTerm === '' ? '' : '&searchterm=' . urlencode($searchTerm);
        $page = $searchResult->getPage();

        $previousPageUrl = !$searchResult->isLastPage() ? '?page=' . ($page + 1) . $searchtermUrl . $searchtagsUrl : '';
        $nextPageUrl = !$searchResult->isFirstPage() ? '?page=' . ($page - 1) . $searchtermUrl . $searchtagsUrl : '';

        $tagsSeparator = $this->container->conf->get('general.tags_separator', ' ');
        $searchTagsUrlEncoded = array_map('urlencode', tags_str2array($searchTags, $tagsSeparator));
        $searchTags = !empty($searchTags) ? trim($searchTags, $tagsSeparator) . $tagsSeparator : '';

        $searchTags = !empty($searchTags) ? escape($searchTags) : '';
        $searchTerm = !empty($searchTerm) ? escape($searchTerm) : '';

        // Fill all template fields.
        $data = array_merge(
            $this->initializeTemplateVars(),
            [
                'previous_page_url' => $previousPageUrl,
                'next_page_url' => $nextPageUrl,
                'page_current' => $page,
                'page_max' => $searchResult->getLastPage(),
                'result_count' => $searchResult->getTotalCount(),
                'search_term' => $searchTerm,
                'search_tags' => $searchTags,
                'search_tags_url' => $searchTagsUrlEncoded,
                'visibility' => $visibility,
                'links' => $links,
            ]
        );

        if (!empty($searchTerm) || !empty($searchTags)) {
            $data['pagetitle'] = t('Search: ');
            $data['pagetitle'] .= ! empty($searchTerm) ? $searchTerm . ' ' : '';
            $bracketWrap = function ($tag) {
                return '[' . $tag . ']';
            };
            $data['pagetitle'] .= ! empty($searchTags)
                ? implode(' ', array_map($bracketWrap, tags_str2array($searchTags, $tagsSeparator))) . ' '
                : ''
            ;
            $data['pagetitle'] .= '- ';
        }

        $data['pagetitle'] = ($data['pagetitle'] ?? '') . $this->container->conf->get('general.title', 'Shaarli');

        $this->executePageHooks('render_linklist', $data, TemplatePage::LINKLIST);
        $this->assignAllView($data);

        return $response->write($this->render(TemplatePage::LINKLIST));
    }

    /**
     * GET /shaare/{hash} - Display a single shaare
     */
    public function permalink(Request $request, Response $response, array $args): Response
    {
        $privateKey = $request->getParam('key');

        try {
            $bookmark = $this->container->bookmarkService->findByHash($args['hash'], $privateKey);
        } catch (BookmarkNotFoundException $e) {
            $this->assignView('error_message', $e->getMessage());

            return $response->write($this->render(TemplatePage::ERROR_404));
        }

        $this->updateThumbnail($bookmark);

        $formatter = $this->container->formatterFactory->getFormatter();
        $formatter->addContextData('base_path', $this->container->basePath);
        $formatter->addContextData('index_url', index_url($this->container->environment));

        $data = array_merge(
            $this->initializeTemplateVars(),
            [
                'pagetitle' => $bookmark->getTitle() . ' - ' . $this->container->conf->get('general.title', 'Shaarli'),
                'links' => [$formatter->format($bookmark)],
            ]
        );

        $this->executePageHooks('render_linklist', $data, TemplatePage::LINKLIST);
        $this->assignAllView($data);

        return $response->write($this->render(TemplatePage::LINKLIST));
    }

    /**
     * Update the thumbnail of a single bookmark if necessary.
     */
    protected function updateThumbnail(Bookmark $bookmark, bool $writeDatastore = true): bool
    {
        if (false === $this->container->loginManager->isLoggedIn()) {
            return false;
        }

        // If thumbnail should be updated, we reset it to null
        if ($bookmark->shouldUpdateThumbnail()) {
            $bookmark->setThumbnail(null);

            // Requires an update, not async retrieval, thumbnails enabled
            if (
                $bookmark->shouldUpdateThumbnail()
                && true !== $this->container->conf->get('general.enable_async_metadata', true)
                && $this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE
            ) {
                $bookmark->setThumbnail($this->container->thumbnailer->get($bookmark->getUrl()));
                $this->container->bookmarkService->set($bookmark, $writeDatastore);

                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] Default template variables without values.
     */
    protected function initializeTemplateVars(): array
    {
        return [
            'previous_page_url' => '',
            'next_page_url' => '',
            'page_max' => '',
            'search_tags' => '',
            'result_count' => '',
            'async_metadata' => $this->container->conf->get('general.enable_async_metadata', true)
        ];
    }

    /**
     * Process legacy routes if necessary. They used query parameters.
     * If no legacy routes is passed, return null.
     */
    protected function processLegacyController(Request $request, Response $response): ?Response
    {
        // Legacy smallhash filter
        $queryString = $this->container->environment['QUERY_STRING'] ?? null;
        if (null !== $queryString && 1 === preg_match('/^([a-zA-Z0-9-_@]{6})($|&|#)/', $queryString, $match)) {
            return $this->redirect($response, '/shaare/' . $match[1]);
        }

        // Legacy controllers (mostly used for redirections)
        if (null !== $request->getQueryParam('do')) {
            $legacyController = new LegacyController($this->container);

            try {
                return $legacyController->process($request, $response, $request->getQueryParam('do'));
            } catch (UnknowLegacyRouteException $e) {
                // We ignore legacy 404
                return null;
            }
        }

        // Legacy GET admin routes
        $legacyGetRoutes = array_intersect(
            LegacyController::LEGACY_GET_ROUTES,
            array_keys($request->getQueryParams() ?? [])
        );
        if (1 === count($legacyGetRoutes)) {
            $legacyController = new LegacyController($this->container);

            return $legacyController->process($request, $response, $legacyGetRoutes[0]);
        }

        return null;
    }
}

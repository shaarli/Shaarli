<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\Render\TemplatePage;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class ShaarePublishController extends ShaarliAdminController
{
    /**
     * @var BookmarkFormatter[] Statically cached instances of formatters
     */
    protected $formatters = [];

    /**
     * @var array Statically cached bookmark's tags counts
     */
    protected $tags;

    /**
     * GET /admin/shaare - Displays the bookmark form for creation.
     *                     Note that if the URL is found in existing bookmarks, then it will be in edit mode.
     */
    public function displayCreateForm(Request $request, Response $response): Response
    {
        $url = cleanup_url($request->getParam('post'));
        $link = $this->buildLinkDataFromUrl($request, $url);

        return $this->displayForm($link, $link['linkIsNew'], $request, $response);
    }

    /**
     * POST /admin/shaare-batch - Displays multiple creation/edit forms from bulk add in add-link page.
     */
    public function displayCreateBatchForms(Request $request, Response $response): Response
    {
        $urls = array_map('cleanup_url', explode(PHP_EOL, $request->getParam('urls')));

        $links = [];
        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }
            $link = $this->buildLinkDataFromUrl($request, $url);
            $data = $this->buildFormData($link, $link['linkIsNew'], $request);
            $data['token'] = $this->container->sessionManager->generateToken();
            $data['source'] = 'batch';

            $this->executePageHooks('render_editlink', $data, TemplatePage::EDIT_LINK);

            $links[] = $data;
        }

        $this->assignView('links', $links);
        $this->assignView('batch_mode', true);
        $this->assignView('async_metadata', $this->container->conf->get('general.enable_async_metadata', true));

        return $response->write($this->render(TemplatePage::EDIT_LINK_BATCH));
    }

    /**
     * GET /admin/shaare/{id} - Displays the bookmark form in edition mode.
     */
    public function displayEditForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? '';
        try {
            if (false === ctype_digit($id)) {
                throw new BookmarkNotFoundException();
            }
            $bookmark = $this->container->bookmarkService->get((int) $id);  // Read database
        } catch (BookmarkNotFoundException $e) {
            $this->saveErrorMessage(sprintf(
                t('Bookmark with identifier %s could not be found.'),
                $id
            ));

            return $this->redirect($response, '/');
        }

        $formatter = $this->getFormatter('raw');
        $link = $formatter->format($bookmark);

        return $this->displayForm($link, false, $request, $response);
    }

    /**
     * POST /admin/shaare
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        // lf_id should only be present if the link exists.
        $id = $request->getParam('lf_id') !== null ? intval(escape($request->getParam('lf_id'))) : null;
        if (null !== $id && true === $this->container->bookmarkService->exists($id)) {
            // Edit
            $bookmark = $this->container->bookmarkService->get($id);
        } else {
            // New link
            $bookmark = new Bookmark();
        }

        $bookmark->setTitle($request->getParam('lf_title'));
        $bookmark->setDescription($request->getParam('lf_description'));
        $bookmark->setUrl($request->getParam('lf_url'), $this->container->conf->get('security.allowed_protocols', []));
        $bookmark->setPrivate(filter_var($request->getParam('lf_private'), FILTER_VALIDATE_BOOLEAN));
        $bookmark->setTagsString(
            $request->getParam('lf_tags'),
            $this->container->conf->get('general.tags_separator', ' ')
        );

        if (
            $this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE
            && true !== $this->container->conf->get('general.enable_async_metadata', true)
            && $bookmark->shouldUpdateThumbnail()
        ) {
            $bookmark->setThumbnail($this->container->thumbnailer->get($bookmark->getUrl()));
        }
        $this->container->bookmarkService->addOrSet($bookmark, false);

        // To preserve backward compatibility with 3rd parties, plugins still use arrays
        $formatter = $this->getFormatter('raw');
        $data = $formatter->format($bookmark);
        $this->executePageHooks('save_link', $data);

        $bookmark->fromArray($data, $this->container->conf->get('general.tags_separator', ' '));
        $this->container->bookmarkService->set($bookmark);

        // If we are called from the bookmarklet, we must close the popup:
        if ($request->getParam('source') === 'bookmarklet') {
            return $response->write('<script>self.close();</script>');
        } elseif ($request->getParam('source') === 'batch') {
            return $response;
        }

        if (!empty($request->getParam('returnurl'))) {
            $this->container->environment['HTTP_REFERER'] = $request->getParam('returnurl');
        }

        return $this->redirectFromReferer(
            $request,
            $response,
            ['/admin/add-shaare', '/admin/shaare'],
            ['addlink', 'post', 'edit_link'],
            $bookmark->getShortUrl()
        );
    }

    /**
     * Helper function used to display the shaare form whether it's a new or existing bookmark.
     *
     * @param array $link data used in template, either from parameters or from the data store
     */
    protected function displayForm(array $link, bool $isNew, Request $request, Response $response): Response
    {
        $data = $this->buildFormData($link, $isNew, $request);

        $this->executePageHooks('render_editlink', $data, TemplatePage::EDIT_LINK);

        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $editLabel = false === $isNew ? t('Edit') . ' ' : '';
        $this->assignView(
            'pagetitle',
            $editLabel . t('Shaare') . ' - ' . $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render(TemplatePage::EDIT_LINK));
    }

    protected function buildLinkDataFromUrl(Request $request, string $url): array
    {
        // Check if URL is not already in database (in this case, we will edit the existing link)
        $bookmark = $this->container->bookmarkService->findByUrl($url);
        if (null === $bookmark) {
            // Get shaare data if it was provided in URL (e.g.: by the bookmarklet).
            $title = $request->getParam('title');
            $description = $request->getParam('description');
            $tags = $request->getParam('tags');
            if ($request->getParam('private') !== null) {
                $private = filter_var($request->getParam('private'), FILTER_VALIDATE_BOOLEAN);
            } else {
                $private = $this->container->conf->get('privacy.default_private_links', false);
            }

            // If this is an HTTP(S) link, we try go get the page to extract
            // the title (otherwise we will to straight to the edit form.)
            if (
                true !== $this->container->conf->get('general.enable_async_metadata', true)
                && empty($title)
                && !empty($url)
                && in_array(strtolower(get_url_scheme($url) ?: ''), ['http', 'https'], true)
                && is_safe_url($url)
            ) {
                $metadata = $this->container->metadataRetriever->retrieve($url);
            }

            if (empty($url)) {
                $metadata['title'] = $this->container->conf->get('general.default_note_title', t('Note: '));
            }

            return [
                'title' => $title ?? $metadata['title'] ?? '',
                'url' => $url ?? '',
                'description' => $description ?? $metadata['description'] ?? '',
                'tags' => $tags ?? $metadata['tags'] ?? '',
                'private' => $private,
                'linkIsNew' => true,
            ];
        }

        $formatter = $this->getFormatter('raw');
        $link = $formatter->format($bookmark);
        $link['linkIsNew'] = false;

        return $link;
    }

    protected function buildFormData(array $link, bool $isNew, Request $request): array
    {
        $link['tags'] = $link['tags'] !== null && strlen($link['tags']) > 0
            ? $link['tags'] . $this->container->conf->get('general.tags_separator', ' ')
            : $link['tags']
        ;

        return escape([
            'link' => $link,
            'link_is_new' => $isNew,
            'http_referer' => $this->container->environment['HTTP_REFERER'] ?? '',
            'source' => $request->getParam('source') ?? '',
            'tags' => $this->getTags(),
            'default_private_links' => $this->container->conf->get('privacy.default_private_links', false),
            'async_metadata' => $this->container->conf->get('general.enable_async_metadata', true),
            'retrieve_description' => $this->container->conf->get('general.retrieve_description', false),
        ]);
    }

    /**
     * Memoize formatterFactory->getFormatter() calls.
     */
    protected function getFormatter(string $type): BookmarkFormatter
    {
        if (!array_key_exists($type, $this->formatters) || $this->formatters[$type] === null) {
            $this->formatters[$type] = $this->container->formatterFactory->getFormatter($type);
        }

        return $this->formatters[$type];
    }

    /**
     * Memoize bookmarkService->bookmarksCountPerTag() calls.
     */
    protected function getTags(): array
    {
        if ($this->tags === null) {
            $this->tags = $this->container->bookmarkService->bookmarksCountPerTag();

            if ($this->container->conf->get('formatter') === 'markdown') {
                $this->tags[BookmarkMarkdownFormatter::NO_MD_TAG] = 1;
            }
        }

        return $this->tags;
    }
}

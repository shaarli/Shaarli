<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class PostBookmarkController
 *
 * Slim controller used to handle Shaarli create or edit bookmarks.
 */
class PostBookmarkController extends ShaarliAdminController
{
    /**
     * GET /admin/add-shaare - Displays the form used to create a new bookmark from an URL
     */
    public function addShaare(Request $request, Response $response): Response
    {
        $this->assignView(
            'pagetitle',
            t('Shaare a new link') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render('addlink'));
    }

    /**
     * GET /admin/shaare - Displays the bookmark form for creation.
     *               Note that if the URL is found in existing bookmarks, then it will be in edit mode.
     */
    public function displayCreateForm(Request $request, Response $response): Response
    {
        $url = cleanup_url($request->getParam('post'));

        $linkIsNew = false;
        // Check if URL is not already in database (in this case, we will edit the existing link)
        $bookmark = $this->container->bookmarkService->findByUrl($url);
        if (null === $bookmark) {
            $linkIsNew = true;
            // Get shaare data if it was provided in URL (e.g.: by the bookmarklet).
            $title = $request->getParam('title');
            $description = $request->getParam('description');
            $tags = $request->getParam('tags');
            $private = filter_var($request->getParam('private'), FILTER_VALIDATE_BOOLEAN);

            // If this is an HTTP(S) link, we try go get the page to extract
            // the title (otherwise we will to straight to the edit form.)
            if (empty($title) && strpos(get_url_scheme($url) ?: '', 'http') !== false) {
                $retrieveDescription = $this->container->conf->get('general.retrieve_description');
                // Short timeout to keep the application responsive
                // The callback will fill $charset and $title with data from the downloaded page.
                $this->container->httpAccess->getHttpResponse(
                    $url,
                    $this->container->conf->get('general.download_timeout', 30),
                    $this->container->conf->get('general.download_max_size', 4194304),
                    $this->container->httpAccess->getCurlDownloadCallback(
                        $charset,
                        $title,
                        $description,
                        $tags,
                        $retrieveDescription
                    )
                );
                if (! empty($title) && strtolower($charset) !== 'utf-8') {
                    $title = mb_convert_encoding($title, 'utf-8', $charset);
                }
            }

            if (empty($url) && empty($title)) {
                $title = $this->container->conf->get('general.default_note_title', t('Note: '));
            }

            $link = escape([
                'title' => $title,
                'url' => $url ?? '',
                'description' => $description ?? '',
                'tags' => $tags ?? '',
                'private' => $private,
            ]);
        } else {
            $formatter = $this->container->formatterFactory->getFormatter('raw');
            $link = $formatter->format($bookmark);
        }

        return $this->displayForm($link, $linkIsNew, $request, $response);
    }

    /**
     * GET /admin/shaare/{id} - Displays the bookmark form in edition mode.
     */
    public function displayEditForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        try {
            if (false === ctype_digit($id)) {
                throw new BookmarkNotFoundException();
            }
            $bookmark = $this->container->bookmarkService->get($id);  // Read database
        } catch (BookmarkNotFoundException $e) {
            $this->saveErrorMessage(t('Bookmark not found'));

            return $this->redirect($response, '/');
        }

        $formatter = $this->container->formatterFactory->getFormatter('raw');
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
        $id = $request->getParam('lf_id') ? intval(escape($request->getParam('lf_id'))) : null;
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
        $bookmark->setTagsString($request->getParam('lf_tags'));

        if ($this->container->conf->get('thumbnails.mode', Thumbnailer::MODE_NONE) !== Thumbnailer::MODE_NONE
            && false === $bookmark->isNote()
        ) {
            $bookmark->setThumbnail($this->container->thumbnailer->get($bookmark->getUrl()));
        }
        $this->container->bookmarkService->addOrSet($bookmark, false);

        // To preserve backward compatibility with 3rd parties, plugins still use arrays
        $formatter = $this->container->formatterFactory->getFormatter('raw');
        $data = $formatter->format($bookmark);
        $data = $this->executeHooks('save_link', $data);

        $bookmark->fromArray($data);
        $this->container->bookmarkService->set($bookmark);

        // If we are called from the bookmarklet, we must close the popup:
        if ($request->getParam('source') === 'bookmarklet') {
            return $response->write('<script>self.close();</script>');
        }

        if (!empty($request->getParam('returnurl'))) {
            $this->container->environment['HTTP_REFERER'] = escape($request->getParam('returnurl'));
        }

        return $this->redirectFromReferer(
            $request,
            $response,
            ['add-shaare', 'shaare'], ['addlink', 'post', 'edit_link'],
            $bookmark->getShortUrl()
        );
    }

    /**
     * GET /admin/shaare/delete
     */
    public function deleteBookmark(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $ids = escape(trim($request->getParam('id')));
        if (strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(preg_split('/\s+/', $ids), 'strlen'));
        } else {
            $ids = [$ids];
        }

        // assert at least one id is given
        if (0 === count($ids)) {
            $this->saveErrorMessage(t('Invalid bookmark ID provided.'));

            return $this->redirectFromReferer($request, $response, [], ['delete-shaare']);
        }

        $formatter = $this->container->formatterFactory->getFormatter('raw');
        foreach ($ids as $id) {
            $id = (int) $id;
            // TODO: check if it exists
            $bookmark = $this->container->bookmarkService->get($id);
            $data = $formatter->format($bookmark);
            $this->container->pluginManager->executeHooks('delete_link', $data);
            $this->container->bookmarkService->remove($bookmark, false);
        }

        $this->container->bookmarkService->save();

        // If we are called from the bookmarklet, we must close the popup:
        if ($request->getParam('source') === 'bookmarklet') {
            return $response->write('<script>self.close();</script>');
        }

        // Don't redirect to where we were previously because the datastore has changed.
        return $this->redirect($response, '/');
    }

    protected function displayForm(array $link, bool $isNew, Request $request, Response $response): Response
    {
        $tags = $this->container->bookmarkService->bookmarksCountPerTag();
        if ($this->container->conf->get('formatter') === 'markdown') {
            $tags[BookmarkMarkdownFormatter::NO_MD_TAG] = 1;
        }

        $data = [
            'link' => $link,
            'link_is_new' => $isNew,
            'http_referer' => escape($this->container->environment['HTTP_REFERER'] ?? ''),
            'source' => $request->getParam('source') ?? '',
            'tags' => $tags,
            'default_private_links' => $this->container->conf->get('privacy.default_private_links', false),
        ];

        $data = $this->executeHooks('render_editlink', $data);

        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $editLabel = false === $isNew ? t('Edit') .' ' : '';
        $this->assignView(
            'pagetitle',
            $editLabel . t('Shaare') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render('editlink'));
    }

    /**
     * @param mixed[] $data Variables passed to the template engine
     *
     * @return mixed[] Template data after active plugins render_picwall hook execution.
     */
    protected function executeHooks(string $hook, array $data): array
    {
        $this->container->pluginManager->executeHooks(
            $hook,
            $data
        );

        return $data;
    }
}

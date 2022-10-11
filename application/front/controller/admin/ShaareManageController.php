<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\Exception\BookmarkNotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class PostBookmarkController
 *
 * Slim controller used to handle Shaarli create or edit bookmarks.
 */
class ShaareManageController extends ShaarliAdminController
{
    /**
     * GET /admin/shaare/delete - Delete one or multiple bookmarks (depending on `id` query parameter).
     */
    public function deleteBookmark(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $ids = escape(trim($request->getParam('id') ?? ''));
        if (empty($ids) || strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(preg_split('/\s+/', $ids), 'ctype_digit'));
        } else {
            $ids = [$ids];
        }

        // assert at least one id is given
        if (0 === count($ids)) {
            $this->saveErrorMessage(t('Invalid bookmark ID provided.'));

            return $this->redirectFromReferer($request, $response, [], ['delete-shaare']);
        }

        $formatter = $this->container->formatterFactory->getFormatter('raw');
        $count = 0;
        foreach ($ids as $id) {
            try {
                $bookmark = $this->container->bookmarkService->get((int) $id);
            } catch (BookmarkNotFoundException $e) {
                $this->saveErrorMessage(sprintf(
                    t('Bookmark with identifier %s could not be found.'),
                    $id
                ));

                continue;
            }

            $data = $formatter->format($bookmark);
            $this->executePageHooks('delete_link', $data);
            $this->container->bookmarkService->remove($bookmark, false);
            ++$count;
        }

        if ($count > 0) {
            $this->container->bookmarkService->save();
        }

        // If we are called from the bookmarklet, we must close the popup:
        if ($request->getParam('source') === 'bookmarklet') {
            return $response->write('<script>self.close();</script>');
        }

        if ($request->getParam('source') === 'batch') {
            return $response->withStatus(204);
        }

        // Don't redirect to permalink after deletion.
        return $this->redirectFromReferer($request, $response, ['shaare/']);
    }

    /**
     * GET /admin/shaare/visibility
     *
     * Change visibility (public/private) of one or multiple bookmarks (depending on `id` query parameter).
     */
    public function changeVisibility(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $ids = trim(escape($request->getParam('id') ?? ''));
        if (empty($ids) || strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(preg_split('/\s+/', $ids), 'ctype_digit'));
        } else {
            // only a single id provided
            $ids = [$ids];
        }

        // assert at least one id is given
        if (0 === count($ids)) {
            $this->saveErrorMessage(t('Invalid bookmark ID provided.'));

            return $this->redirectFromReferer($request, $response, [], ['change_visibility']);
        }

        // assert that the visibility is valid
        $visibility = $request->getParam('newVisibility');
        if (null === $visibility || false === in_array($visibility, ['public', 'private'], true)) {
            $this->saveErrorMessage(t('Invalid visibility provided.'));

            return $this->redirectFromReferer($request, $response, [], ['change_visibility']);
        } else {
            $isPrivate = $visibility === 'private';
        }

        $formatter = $this->container->formatterFactory->getFormatter('raw');
        $count = 0;

        foreach ($ids as $id) {
            try {
                $bookmark = $this->container->bookmarkService->get((int) $id);
            } catch (BookmarkNotFoundException $e) {
                $this->saveErrorMessage(sprintf(
                    t('Bookmark with identifier %s could not be found.'),
                    $id
                ));

                continue;
            }

            $bookmark->setPrivate($isPrivate);

            // To preserve backward compatibility with 3rd parties, plugins still use arrays
            $data = $formatter->format($bookmark);
            $this->executePageHooks('save_link', $data);
            $bookmark->fromArray($data, $this->container->conf->get('general.tags_separator', ' '));

            $this->container->bookmarkService->set($bookmark, false);
            ++$count;
        }

        if ($count > 0) {
            $this->container->bookmarkService->save();
        }

        return $this->redirectFromReferer($request, $response, ['/visibility'], ['change_visibility']);
    }

    /**
     * GET /admin/shaare/{id}/pin - Pin or unpin a bookmark.
     */
    public function pinBookmark(Request $request, Response $response, array $args): Response
    {
        $this->checkToken($request);

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

            return $this->redirectFromReferer($request, $response, ['/pin'], ['pin']);
        }

        $formatter = $this->container->formatterFactory->getFormatter('raw');

        $bookmark->setSticky(!$bookmark->isSticky());

        // To preserve backward compatibility with 3rd parties, plugins still use arrays
        $data = $formatter->format($bookmark);
        $this->executePageHooks('save_link', $data);
        $bookmark->fromArray($data, $this->container->conf->get('general.tags_separator', ' '));

        $this->container->bookmarkService->set($bookmark);

        return $this->redirectFromReferer($request, $response, ['/pin'], ['pin']);
    }

    /**
     * GET /admin/shaare/private/{hash} - Attach a private key to given bookmark, then redirect to the sharing URL.
     */
    public function sharePrivate(Request $request, Response $response, array $args): Response
    {
        $this->checkToken($request);

        $hash = $args['hash'] ?? '';
        $bookmark = $this->container->bookmarkService->findByHash($hash);

        if ($bookmark->isPrivate() !== true) {
            return $this->redirect($response, '/shaare/' . $hash);
        }

        if (empty($bookmark->getAdditionalContentEntry('private_key'))) {
            $privateKey = bin2hex(random_bytes(16));
            $bookmark->setAdditionalContentEntry('private_key', $privateKey);
            $this->container->bookmarkService->set($bookmark);
        }

        return $this->redirect(
            $response,
            '/shaare/' . $hash . '?key=' . $bookmark->getAdditionalContentEntry('private_key')
        );
    }

    /**
     * POST /admin/shaare/update-tags
     *
     * Bulk add or delete a tags on one or multiple bookmarks.
     */
    public function addOrDeleteTags(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $ids = trim(escape($request->getParam('id') ?? ''));
        if (empty($ids) || strpos($ids, ' ') !== false) {
            // multiple, space-separated ids provided
            $ids = array_values(array_filter(preg_split('/\s+/', $ids), 'ctype_digit'));
        } else {
            // only a single id provided
            $ids = [$ids];
        }

        // assert at least one id is given
        if (0 === count($ids)) {
            $this->saveErrorMessage(t('Invalid bookmark ID provided.'));

            return $this->redirectFromReferer($request, $response, ['/updateTag'], []);
        }

        // assert that the action is valid
        $action = $request->getParam('action');
        if (!in_array($action, ['add', 'delete'], true)) {
            $this->saveErrorMessage(t('Invalid action provided.'));

            return $this->redirectFromReferer($request, $response, ['/updateTag'], []);
        }

        // assert that the tag name is valid
        $tagString = trim($request->getParam('tag'));
        if (empty($tagString)) {
            $this->saveErrorMessage(t('Invalid tag name provided.'));

            return $this->redirectFromReferer($request, $response, ['/updateTag'], []);
        }

        $tags = tags_str2array($tagString, $this->container->conf->get('general.tags_separator', ' '));
        $formatter = $this->container->formatterFactory->getFormatter('raw');
        $count = 0;

        foreach ($ids as $id) {
            try {
                $bookmark = $this->container->bookmarkService->get((int) $id);
            } catch (BookmarkNotFoundException $e) {
                $this->saveErrorMessage(sprintf(
                    t('Bookmark with identifier %s could not be found.'),
                    $id
                ));

                continue;
            }

            foreach ($tags as $tag) {
                if ($action === 'add') {
                    $bookmark->addTag($tag);
                } else {
                    $bookmark->deleteTag($tag);
                }
            }

            // To preserve backward compatibility with 3rd parties, plugins still use arrays
            $data = $formatter->format($bookmark);
            $this->executePageHooks('save_link', $data);
            $bookmark->fromArray($data, $this->container->conf->get('general.tags_separator', ' '));

            $this->container->bookmarkService->set($bookmark, false);
            ++$count;
        }

        if ($count > 0) {
            $this->container->bookmarkService->save();
        }

        return $this->redirectFromReferer($request, $response, ['/updateTag'], []);
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Bookmark\BookmarkFilter;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ManageTagController
 *
 * Slim controller used to handle Shaarli manage tags page (rename and delete tags).
 */
class ManageTagController extends ShaarliAdminController
{
    /**
     * GET /manage-tags - Displays the manage tags page
     */
    public function index(Request $request, Response $response): Response
    {
        $fromTag = $request->getParam('fromtag') ?? '';

        $this->assignView('fromtag', escape($fromTag));
        $this->assignView(
            'pagetitle',
            t('Manage tags') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render('changetag'));
    }

    /**
     * POST /manage-tags - Update or delete provided tag
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $isDelete = null !== $request->getParam('deletetag') && null === $request->getParam('renametag');

        $fromTag = escape(trim($request->getParam('fromtag') ?? ''));
        $toTag = escape(trim($request->getParam('totag') ?? ''));

        if (0 === strlen($fromTag) || false === $isDelete && 0 === strlen($toTag)) {
            $this->saveWarningMessage(t('Invalid tags provided.'));

            return $response->withRedirect('./manage-tags');
        }

        // TODO: move this to bookmark service
        $count = 0;
        $bookmarks = $this->container->bookmarkService->search(['searchtags' => $fromTag], BookmarkFilter::$ALL, true);
        foreach ($bookmarks as $bookmark) {
            if (false === $isDelete) {
                $bookmark->renameTag($fromTag, $toTag);
            } else {
                $bookmark->deleteTag($fromTag);
            }

            $this->container->bookmarkService->set($bookmark, false);
            $this->container->history->updateLink($bookmark);
            $count++;
        }

        $this->container->bookmarkService->save();

        if (true === $isDelete) {
            $alert = sprintf(
                t('The tag was removed from %d bookmark.', 'The tag was removed from %d bookmarks.', $count),
                $count
            );
        } else {
            $alert = sprintf(
                t('The tag was renamed in %d bookmark.', 'The tag was renamed in %d bookmarks.', $count),
                $count
            );
        }

        $this->saveSuccessMessage($alert);

        $redirect = true === $isDelete ? './manage-tags' : './?searchtags='. urlencode($toTag);

        return $response->withRedirect($redirect);
    }
}

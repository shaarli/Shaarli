<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Render\TemplatePage;

/**
 * Class ManageTagController
 *
 * Slim controller used to handle Shaarli manage tags page (rename and delete tags).
 */
class ManageTagController extends ShaarliAdminController
{
    /**
     * GET /admin/tags - Displays the manage tags page
     */
    public function index(Request $request, Response $response): Response
    {
        $fromTag = $request->getQueryParams()['fromtag'] ?? '';

        $this->assignView('fromtag', escape($fromTag));
        $separator = escape($this->container->get('conf')->get('general.tags_separator', ' '));
        if ($separator === ' ') {
            $separator = '&nbsp;';
            $this->assignView('tags_separator_desc', t('whitespace'));
        }
        $this->assignView('tags_separator', $separator);
        $this->assignView(
            'pagetitle',
            t('Manage tags') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );

        return $this->respondWithTemplate($response, TemplatePage::CHANGE_TAG);
    }

    /**
     * POST /admin/tags - Update or delete provided tag
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $isDelete = null !== ($request->getParsedBody()['deletetag'] ?? null)
            && null === ($request->getParsedBody()['renametag'] ?? null);

        $fromTag = trim($request->getParsedBody()['fromtag'] ?? '');
        $toTag = trim($request->getParsedBody()['totag'] ?? '');

        if (0 === strlen($fromTag) || false === $isDelete && 0 === strlen($toTag)) {
            $this->saveWarningMessage(t('Invalid tags provided.'));

            return $this->redirect($response, '/admin/tags');
        }

        // TODO: move this to bookmark service
        $searchResult = $this->container->get('bookmarkService')->search(
            ['searchtags' => $fromTag],
            BookmarkFilter::$ALL,
            true
        );
        foreach ($searchResult->getBookmarks() as $bookmark) {
            if (false === $isDelete) {
                $bookmark->renameTag($fromTag, $toTag);
            } else {
                $bookmark->deleteTag($fromTag);
            }

            $this->container->get('bookmarkService')->set($bookmark, false);
            $this->container->get('history')->updateLink($bookmark);
        }

        $this->container->get('bookmarkService')->save();

        $count = $searchResult->getResultCount();
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

        $redirect = true === $isDelete ? '/admin/tags' : '/?searchtags=' . urlencode($toTag);

        return $this->redirect($response, $redirect);
    }

    /**
     * POST /admin/tags/change-separator - Change tag separator
     */
    public function changeSeparator(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $reservedCharacters = ['-', '.', '*'];
        $newSeparator = $request->getParsedBody()['separator'] ?? null;
        if ($newSeparator === null || mb_strlen($newSeparator) !== 1) {
            $this->saveErrorMessage(t('Tags separator must be a single character.'));
        } elseif (in_array($newSeparator, $reservedCharacters, true)) {
            $reservedCharacters = implode(' ', array_map(function (string $character) {
                return '<code>' . $character . '</code>';
            }, $reservedCharacters));
            $this->saveErrorMessage(
                t('These characters are reserved and can\'t be used as tags separator: ') . $reservedCharacters
            );
        } else {
            $this->container->get('conf')->set('general.tags_separator', $newSeparator, true, true);

            $this->saveSuccessMessage('Your tags separator setting has been updated!');
        }

        return $this->redirect($response, '/admin/tags');
    }
}

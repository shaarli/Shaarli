<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class TagController
 *
 * Slim controller handle tags.
 */
class TagController extends ShaarliVisitorController
{
    /**
     * Add another tag in the current search through an HTTP redirection.
     *
     * @param array $args Should contain `newTag` key as tag to add to current search
     */
    public function addTag(Request $request, Response $response, array $args): Response
    {
        $newTag = $args['newTag'] ?? null;
        $referer = $request->getServerParams()['HTTP_REFERER'] ?? null;

        // In case browser does not send HTTP_REFERER, we search a single tag
        if (null === $referer) {
            if (null !== $newTag) {
                return $this->redirect($response, '/?searchtags=' . urlencode($newTag));
            }

            return $this->redirect($response, '/');
        }

        $currentUrl = parse_url($referer);
        parse_str($currentUrl['query'] ?? '', $params);

        if (null === $newTag) {
            return $this->redirect($response, ($currentUrl['path'] ?? './') . '?' . http_build_query($params), false);
        }

        // Prevent redirection loop
        if (isset($params['addtag'])) {
            unset($params['addtag']);
        }

        $tagsSeparator = $this->container->get('conf')->get('general.tags_separator', ' ');
        // Check if this tag is already in the search query and ignore it if it is.
        // Each tag is always separated by a space
        $currentTags = tags_str2array($params['searchtags'] ?? '', $tagsSeparator);

        $addtag = true;
        foreach ($currentTags as $value) {
            if ($value === $newTag) {
                $addtag = false;
                break;
            }
        }

        // Append the tag if necessary
        if (true === $addtag) {
            $currentTags[] = trim($newTag);
        }

        $params['searchtags'] = tags_array2str($currentTags, $tagsSeparator);

        // We also remove page (keeping the same page has no sense, since the results are different)
        unset($params['page']);

        return $this->redirect($response, ($currentUrl['path'] ?? './') . '?' . http_build_query($params), false);
    }

    /**
     * Remove a tag from the current search through an HTTP redirection.
     *
     * @param array $args Should contain `tag` key as tag to remove from current search
     */
    public function removeTag(Request $request, Response $response, array $args): Response
    {
        $referer = $request->getServerParams()['HTTP_REFERER'] ?? null;

        // If the referrer is not provided, we can update the search, so we failback on the bookmark list
        if (empty($referer)) {
            return $this->redirect($response, '/');
        }

        $tagToRemove = $args['tag'] ?? null;
        $currentUrl = parse_url($referer);
        parse_str($currentUrl['query'] ?? '', $params);

        if (null === $tagToRemove) {
            return $this->redirect($response, ($currentUrl['path'] ?? './') . '?' . http_build_query($params), false);
        }

        // Prevent redirection loop
        if (isset($params['removetag'])) {
            unset($params['removetag']);
        }

        if (isset($params['searchtags'])) {
            $tagsSeparator = $this->container->get('conf')->get('general.tags_separator', ' ');
            $tags = tags_str2array($params['searchtags'] ?? '', $tagsSeparator);
            // Remove value from array $tags.
            $tags = array_diff($tags, [$tagToRemove]);
            $params['searchtags'] = tags_array2str($tags, $tagsSeparator);

            if (empty($params['searchtags'])) {
                unset($params['searchtags']);
            }

            // We also remove page (keeping the same page has no sense, since the results are different)
            unset($params['page']);
        }

        $queryParams = count($params) > 0 ? '?' . http_build_query($params) : '';

        return $this->redirect($response, ($currentUrl['path'] ?? './') . $queryParams, false);
    }
}

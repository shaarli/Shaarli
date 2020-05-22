<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Slim\Http\Request;
use Slim\Http\Response;

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
        $referer = $this->container->environment['HTTP_REFERER'] ?? null;

        // In case browser does not send HTTP_REFERER, we search a single tag
        if (null === $referer) {
            if (null !== $newTag) {
                return $response->withRedirect('./?searchtags='. urlencode($newTag));
            }

            return $response->withRedirect('./');
        }

        $currentUrl = parse_url($referer);
        parse_str($currentUrl['query'] ?? '', $params);

        if (null === $newTag) {
            return $response->withRedirect(($currentUrl['path'] ?? './') .'?'. http_build_query($params));
        }

        // Prevent redirection loop
        if (isset($params['addtag'])) {
            unset($params['addtag']);
        }

        // Check if this tag is already in the search query and ignore it if it is.
        // Each tag is always separated by a space
        $currentTags = isset($params['searchtags']) ? explode(' ', $params['searchtags']) : [];

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

        $params['searchtags'] = trim(implode(' ', $currentTags));

        // We also remove page (keeping the same page has no sense, since the results are different)
        unset($params['page']);

        return $response->withRedirect(($currentUrl['path'] ?? './') .'?'. http_build_query($params));
    }

    /**
     * Remove a tag from the current search through an HTTP redirection.
     *
     * @param array $args Should contain `tag` key as tag to remove from current search
     */
    public function removeTag(Request $request, Response $response, array $args): Response
    {
        $referer = $this->container->environment['HTTP_REFERER'] ?? null;

        // If the referrer is not provided, we can update the search, so we failback on the bookmark list
        if (empty($referer)) {
            return $response->withRedirect('./');
        }

        $tagToRemove = $args['tag'] ?? null;
        $currentUrl = parse_url($referer);
        parse_str($currentUrl['query'] ?? '', $params);

        if (null === $tagToRemove) {
            return $response->withRedirect(($currentUrl['path'] ?? './') .'?'. http_build_query($params));
        }

        // Prevent redirection loop
        if (isset($params['removetag'])) {
            unset($params['removetag']);
        }

        if (isset($params['searchtags'])) {
            $tags = explode(' ', $params['searchtags']);
            // Remove value from array $tags.
            $tags = array_diff($tags, [$tagToRemove]);
            $params['searchtags'] = implode(' ', $tags);

            if (empty($params['searchtags'])) {
                unset($params['searchtags']);
            }

            // We also remove page (keeping the same page has no sense, since the results are different)
            unset($params['page']);
        }

        $queryParams = count($params) > 0 ? '?' . http_build_query($params) : '';

        return $response->withRedirect(($currentUrl['path'] ?? './') . $queryParams);
    }
}

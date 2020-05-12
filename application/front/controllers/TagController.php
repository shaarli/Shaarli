<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class TagController
 *
 * Slim controller handle tags.
 *
 * @package Front\Controller
 */
class TagController extends ShaarliController
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

        $currentUrl = parse_url($this->container->environment['HTTP_REFERER']);
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
}

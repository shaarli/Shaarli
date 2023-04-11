<?php

namespace Shaarli\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Api\ApiUtils;
use Shaarli\Api\Exceptions\ApiBadParametersException;
use Shaarli\Api\Exceptions\ApiTagNotFoundException;
use Shaarli\Bookmark\BookmarkFilter;

/**
 * Class Tags
 *
 * REST API Controller: all services related to tags collection.
 *
 * @package Api\Controllers
 */
class Tags extends ApiController
{
    /**
     * @var int Number of bookmarks returned if no limit is provided.
     */
    public static $DEFAULT_LIMIT = 'all';

    /**
     * Retrieve a list of tags, allowing different filters.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     *
     * @return Response response.
     *
     * @throws ApiBadParametersException Invalid parameters.
     */
    public function getTags($request, $response)
    {
        $visibility = $request->getQueryParams()['visibility'] ?? '';
        $tags = $this->bookmarkService->bookmarksCountPerTag([], $visibility);

        // Return tags from the {offset}th tag, starting from 0.
        $offset = $request->getQueryParams()['offset'] ?? '';
        if (! empty($offset) && ! ctype_digit($offset)) {
            throw new ApiBadParametersException('Invalid offset');
        }
        $offset = ! empty($offset) ? intval($offset) : 0;
        if ($offset > count($tags)) {
            return $this->respondWithJson($response, [], $this->jsonStyle)
                ->withStatus(200);
        }

        // limit parameter is either a number of bookmarks or 'all' for everything.
        $limit = $request->getQueryParams()['limit'] ?? null;
        if (empty($limit)) {
            $limit = self::$DEFAULT_LIMIT;
        }
        if (ctype_digit($limit)) {
            $limit = intval($limit);
        } elseif ($limit === 'all') {
            $limit = count($tags);
        } else {
            throw new ApiBadParametersException('Invalid limit');
        }

        $out = [];
        $index = 0;
        foreach ($tags as $tag => $occurrences) {
            if (count($out) >= $limit) {
                break;
            }
            if ($index++ >= $offset) {
                $out[] = ApiUtils::formatTag($tag, $occurrences);
            }
        }

        return $this->respondWithJson($response, $out, $this->jsonStyle)
            ->withStatus(200);
    }

    /**
     * Return a single formatted tag by its name.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     * @param array    $args     Path parameters. including the tag name.
     *
     * @return Response containing the link array.
     *
     * @throws ApiTagNotFoundException generating a 404 error.
     */
    public function getTag($request, $response, $args)
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag();
        if (!isset($tags[$args['tagName']])) {
            throw new ApiTagNotFoundException();
        }
        $out = ApiUtils::formatTag($args['tagName'], $tags[$args['tagName']]);

        return $this->respondWithJson($response, $out, $this->jsonStyle)
            ->withStatus(200);
    }

    /**
     * Rename a tag from the given name.
     * If the new name provided matches an existing tag, they will be merged.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     * @param array    $args     Path parameters. including the tag name.
     *
     * @return Response response.
     *
     * @throws ApiTagNotFoundException generating a 404 error.
     * @throws ApiBadParametersException new tag name not provided
     */
    public function putTag($request, $response, $args)
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag();
        if (! isset($tags[$args['tagName']])) {
            throw new ApiTagNotFoundException();
        }

        $data = $request->getParsedBody();
        if (empty($data['name'])) {
            throw new ApiBadParametersException('New tag name is required in the request body');
        }

        $searchResult = $this->bookmarkService->search(
            ['searchtags' => $args['tagName']],
            BookmarkFilter::$ALL,
            true
        );
        foreach ($searchResult->getBookmarks() as $bookmark) {
            $bookmark->renameTag($args['tagName'], $data['name']);
            $this->bookmarkService->set($bookmark, false);
            $this->history->updateLink($bookmark);
        }
        $this->bookmarkService->save();

        $tags = $this->bookmarkService->bookmarksCountPerTag();
        $out = ApiUtils::formatTag($data['name'], $tags[$data['name']]);
        return $this->respondWithJson($response, $out, $this->jsonStyle)
            ->withStatus(200);
    }

    /**
     * Delete an existing tag by its name.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     * @param array    $args     Path parameters. including the tag name.
     *
     * @return Response response.
     *
     * @throws ApiTagNotFoundException generating a 404 error.
     */
    public function deleteTag($request, $response, $args)
    {
        $tags = $this->bookmarkService->bookmarksCountPerTag();
        if (! isset($tags[$args['tagName']])) {
            throw new ApiTagNotFoundException();
        }

        $searchResult = $this->bookmarkService->search(
            ['searchtags' => $args['tagName']],
            BookmarkFilter::$ALL,
            true
        );
        foreach ($searchResult->getBookmarks() as $bookmark) {
            $bookmark->deleteTag($args['tagName']);
            $this->bookmarkService->set($bookmark, false);
            $this->history->updateLink($bookmark);
        }
        $this->bookmarkService->save();

        return $response->withStatus(204);
    }
}

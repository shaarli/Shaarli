<?php

namespace Shaarli\Api\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Api\ApiUtils;
use Shaarli\Api\Exceptions\ApiBadParametersException;
use Shaarli\Api\Exceptions\ApiLinkNotFoundException;
use Slim\Routing\RouteContext;

/**
 * Class Links
 *
 * REST API Controller: all services related to bookmarks collection.
 *
 * @package Api\Controllers
 * @see http://shaarli.github.io/api-documentation/#links-links-collection
 */
class Links extends ApiController
{
    /**
     * @var int Number of bookmarks returned if no limit is provided.
     */
    public static $DEFAULT_LIMIT = 20;

    public function __construct(Container $ci)
    {
        parent::__construct($ci);
    }

    /**
     * Retrieve a list of bookmarks, allowing different filters.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     *
     * @return Response response.
     *
     * @throws ApiBadParametersException Invalid parameters.
     */
    public function getLinks($request, $response)
    {
        $private = $request->getQueryParams()['visibility'] ?? null;

        // Return bookmarks from the {offset}th link, starting from 0.
        $offset = $request->getQueryParams()['offset'] ?? null;
        if (! empty($offset) && ! ctype_digit($offset)) {
            throw new ApiBadParametersException('Invalid offset');
        }
        $offset = ! empty($offset) ? intval($offset) : 0;

        // limit parameter is either a number of bookmarks or 'all' for everything.
        $limit = $request->getQueryParams()['limit'] ?? null;
        if (empty($limit)) {
            $limit = self::$DEFAULT_LIMIT;
        } elseif (ctype_digit($limit)) {
            $limit = intval($limit);
        } elseif ($limit === 'all') {
            $limit = null;
        } else {
            throw new ApiBadParametersException('Invalid limit');
        }

        $searchResult = $this->bookmarkService->search(
            [
                'searchtags' => $request->getQueryParams()['searchtags'] ?? '',
                'searchterm' => $request->getQueryParams()['searchterm'] ?? '',
            ],
            $private,
            false,
            false,
            false,
            [
                'limit' => $limit,
                'offset' => $offset,
                'allowOutOfBounds' => true,
            ]
        );

        // 'environment' is set by Slim and encapsulate $_SERVER.
        $indexUrl = index_url($request->getServerParams());

        $out = [];
        foreach ($searchResult->getBookmarks() as $bookmark) {
            $out[] = ApiUtils::formatLink($bookmark, $indexUrl);
        }

        return $this->respondWithJson($response, $out, $this->jsonStyle)->withStatus(200);
    }

    /**
     * Return a single formatted link by its ID.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     * @param array    $args     Path parameters. including the ID.
     *
     * @return Response containing the link array.
     *
     * @throws ApiLinkNotFoundException generating a 404 error.
     */
    public function getLink($request, $response, $args)
    {
        $id = is_integer_mixed($args['id']) ? (int) $args['id'] : null;
        if ($id === null || ! $this->bookmarkService->exists($id)) {
            throw new ApiLinkNotFoundException();
        }
        $index = index_url($request->getServerParams());
        $out = ApiUtils::formatLink($this->bookmarkService->get($id), $index);

        return $this->respondWithJson($response, $out, $this->jsonStyle)->withStatus(200);
    }

    /**
     * Creates a new link from posted request body.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     *
     * @return Response response.
     */
    public function postLink($request, $response)
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $bookmark = ApiUtils::buildBookmarkFromRequest(
            $data,
            $this->conf->get('privacy.default_private_links'),
            $this->conf->get('general.tags_separator', ' ')
        );
        // duplicate by URL, return 409 Conflict
        if (
            ! empty($bookmark->getUrl())
            && ! empty($dup = $this->bookmarkService->findByUrl($bookmark->getUrl()))
        ) {
            return $this->respondWithJson(
                $response,
                ApiUtils::formatLink($dup, index_url($request->getServerParams())),
                $this->jsonStyle
            )->withStatus(409);
        }

        $this->bookmarkService->add($bookmark);
        $out = ApiUtils::formatLink($bookmark, index_url($request->getServerParams()));
        $routeParser = $request->getAttribute(RouteContext::ROUTE_PARSER);
        $redirect = $routeParser->relativeUrlFor('getLink', ['id' => $bookmark->getId()]);

        return $this->respondWithJson($response, $out, $this->jsonStyle)
            ->withAddedHeader('Location', $redirect)->withStatus(201);
    }

    /**
     * Updates an existing link from posted request body.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     * @param array    $args     Path parameters. including the ID.
     *
     * @return Response response.
     *
     * @throws ApiLinkNotFoundException generating a 404 error.
     */
    public function putLink($request, $response, $args)
    {
        $id = is_integer_mixed($args['id']) ? (int) $args['id'] : null;
        if ($id === null || !$this->bookmarkService->exists($id)) {
            throw new ApiLinkNotFoundException();
        }

        $index = index_url($request->getServerParams());
        $data = $request->getParsedBody();

        $requestBookmark = ApiUtils::buildBookmarkFromRequest(
            $data,
            $this->conf->get('privacy.default_private_links'),
            $this->conf->get('general.tags_separator', ' ')
        );
        // duplicate URL on a different link, return 409 Conflict
        if (
            ! empty($requestBookmark->getUrl())
            && ! empty($dup = $this->bookmarkService->findByUrl($requestBookmark->getUrl()))
            && $dup->getId() != $id
        ) {
            return $this->respondWithJson(
                $response,
                ApiUtils::formatLink($dup, $index),
                $this->jsonStyle
            )->withStatus(409);
        }

        $responseBookmark = $this->bookmarkService->get($id);
        $responseBookmark = ApiUtils::updateLink($responseBookmark, $requestBookmark);
        $this->bookmarkService->set($responseBookmark);

        $out = ApiUtils::formatLink($responseBookmark, $index);
        return $this->respondWithJson($response, $out, $this->jsonStyle)
            ->withStatus(200);
    }

    /**
     * Delete an existing link by its ID.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     * @param array    $args     Path parameters. including the ID.
     *
     * @return Response response.
     *
     * @throws ApiLinkNotFoundException generating a 404 error.
     */
    public function deleteLink($request, $response, $args)
    {
        $id = is_integer_mixed($args['id']) ? (int) $args['id'] : null;
        if ($id === null || !$this->bookmarkService->exists($id)) {
            throw new ApiLinkNotFoundException();
        }
        $bookmark = $this->bookmarkService->get($id);
        $this->bookmarkService->remove($bookmark);

        return $response->withStatus(204);
    }
}

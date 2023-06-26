<?php

namespace Shaarli\Api\Controllers;

use Shaarli\Bookmark\BookmarkFilter;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Info
 *
 * REST API Controller: /info
 *
 * @package Api\Controllers
 * @see http://shaarli.github.io/api-documentation/#links-instance-information-get
 */
class Info extends ApiController
{
    /**
     * Service providing various information about Shaarli instance.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     *
     * @return Response response.
     */
    public function getInfo($request, $response)
    {
        $info = [
            'global_counter' => $this->bookmarkService->count(),
            'private_counter' => $this->bookmarkService->count(BookmarkFilter::$PRIVATE),
            'settings' => [
                'title' => $this->conf->get('general.title', 'Shaarli'),
                'header_link' => $this->conf->get('general.header_link', '?'),
                'timezone' => $this->conf->get('general.timezone', 'UTC'),
                'enabled_plugins' => $this->conf->get('general.enabled_plugins', []),
                'default_private_links' => $this->conf->get('privacy.default_private_links', false),
                'tags_separator' => $this->conf->get('general.tags_separator', ' '),
            ],
        ];

        return $response->withJson($info, 200, $this->jsonStyle);
    }
}

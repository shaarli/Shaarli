<?php

namespace Shaarli\Api\Controllers;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\History;

/**
 * Abstract Class ApiController
 *
 * Defines REST API Controller dependencies injected from the container.
 *
 * @package Api\Controllers
 */
abstract class ApiController
{
    /**
     * @var Container
     */
    protected $ci;

    /**
     * @var ConfigManager
     */
    protected $conf;

    /**
     * @var BookmarkServiceInterface
     */
    protected $bookmarkService;

    /**
     * @var History
     */
    protected $history;

    /**
     * @var int|null JSON style option.
     */
    protected $jsonStyle;

    /**
     * ApiController constructor.
     *
     * Note: enabling debug mode displays JSON with readable formatting.
     *
     * @param Container $ci Slim container.
     */
    public function __construct(Container $ci)
    {
        $this->ci = $ci;
        $this->conf = $ci->get('conf');
        $this->bookmarkService = $ci->get('db');
        $this->history = $ci->get('history');
        if ($this->conf->get('dev.debug', false)) {
            $this->jsonStyle = JSON_PRETTY_PRINT;
        } else {
            $this->jsonStyle = null;
        }
    }

    /**
     * Get the container.
     *
     * @return Container
     */
    public function getCi()
    {
        return $this->ci;
    }

    /**
     * Simple helper, which writes data as JSON to the body
     *
     * @param Response $response
     * @param array $data
     * @param int $jsonStyle
     * @return Response
     */
    protected function respondWithJson(Response $response, array $data, int $jsonStyle): Response
    {
        $jsonStyle = $jsonStyle ?? 0;
        $response->getBody()->write(json_encode($data, $jsonStyle));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

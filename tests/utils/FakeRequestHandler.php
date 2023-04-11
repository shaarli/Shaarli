<?php

namespace Shaarli\Tests\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class FakeRequestHandler implements RequestHandlerInterface
{
    /**
     * @var mixed|Response
     */
    private $response;
    /**
     * @var mixed|null
     */
    private $callback;

    public function __construct($response = null, $callback = null)
    {
        $this->response = $response ?? new Response();
        $this->callback = $callback;
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->callback) {
            return ($this->callback)($request);
        }
        return $this->response;
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Tests\Utils;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

class RequestHandlerFactory
{
    public function createRequestHandler(
        callable $callable = null
    ): RequestHandlerInterface {
        $app = AppFactory::create();
        if (is_null($callable)) {
            $callable = function (ServerRequestInterface $request, RequestHandlerInterface $next) {
                return (new ResponseFactory())->createResponse();
            };
        }
        $app->add($callable);

        return $app;
    }
}

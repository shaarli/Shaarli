<?php

namespace Shaarli\Tests\Utils;

use Slim\CallableResolver;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteCollector;

class FakeRouteCollector extends RouteCollector
{
    public function __construct()
    {
        parent::__construct(
            new ResponseFactory(),
            new CallableResolver()
        );
    }

    public function addRoute($methods, string $pattern, $name = null)
    {
        $methods = is_array($methods) ? $methods : [$methods];
        $route = $this->map($methods, $pattern, function () {
        });
        if ($name) {
            $route->setName($name);
        }
        return $this;
    }

    public function getRouteParser(): RouteParserInterface
    {
        return parent::getRouteParser();
    }
}

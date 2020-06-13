<?php

declare(strict_types=1);

namespace Shaarli\Front;

use PHPUnit\Framework\TestCase;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Front\Exception\LoginBannedException;
use Shaarli\Render\PageBuilder;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;

class ShaarliMiddlewareTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var ShaarliMiddleware  */
    protected $middleware;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->middleware = new ShaarliMiddleware($this->container);
    }

    public function testMiddlewareExecution(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getUri')->willReturnCallback(function (): Uri {
            $uri = $this->createMock(Uri::class);
            $uri->method('getBasePath')->willReturn('/subfolder');

            return $uri;
        });

        $response = new Response();
        $controller = function (Request $request, Response $response): Response {
            return $response->withStatus(418); // I'm a tea pot
        };

        /** @var Response $result */
        $result = $this->middleware->__invoke($request, $response, $controller);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(418, $result->getStatusCode());
    }

    public function testMiddlewareExecutionWithException(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getUri')->willReturnCallback(function (): Uri {
            $uri = $this->createMock(Uri::class);
            $uri->method('getBasePath')->willReturn('/subfolder');

            return $uri;
        });
        
        $response = new Response();
        $controller = function (): void {
            $exception = new LoginBannedException();

            throw new $exception;
        };

        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder->method('render')->willReturnCallback(function (string $message): string {
            return $message;
        });
        $this->container->pageBuilder = $pageBuilder;

        $conf = $this->createMock(ConfigManager::class);
        $this->container->conf = $conf;

        /** @var Response $result */
        $result = $this->middleware->__invoke($request, $response, $controller);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(401, $result->getStatusCode());
        static::assertContains('error', (string) $result->getBody());
    }
}

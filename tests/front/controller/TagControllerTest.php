<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

class TagControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var TagController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new TagController($this->container);
    }

    public function testAddTagWithReferer(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndExistingSearch(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def+abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutRefererAndExistingSearch(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagRemoveLegacyQueryParam(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&addtag=abc'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def+abc'], $result->getHeader('location'));
    }

    public function testAddTagResetPagination(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&page=12'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def+abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndEmptySearch(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags='];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithReferer(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithoutReferer(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }
}

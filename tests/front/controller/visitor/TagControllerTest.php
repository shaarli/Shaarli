<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

class TagControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var TagController */    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new TagController($this->container);
    }

    public function testAddTagWithReferer(): void
    {
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
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutMatchingTag(): void
    {
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTagsearch(): void
    {
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutReferer(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTag(): void
    {
        $this->container->environment = ['HTTP_REFERER' => 'http://shaarli/controller/?searchtag=abc'];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->removeTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTagWithoutReferer(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $result = $this->controller->removeTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }
}

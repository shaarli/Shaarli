<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

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
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/',
        ]);
        $response = new SlimResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndExistingSearch(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def',
        ]);
        $response = new SlimResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def%40abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutRefererAndExistingSearch(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
        ]);
        $response = new SlimResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagRemoveLegacyQueryParam(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&addtag=abc'
        ]);
        $response = new SlimResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def%40abc'], $result->getHeader('location'));
    }

    public function testAddTagResetPagination(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&page=12'
        ]);
        $response = new SlimResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def%40abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndEmptySearch(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags='
        ]);
        $response = new SlimResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithReferer(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'
        ]);
        $response = new SlimResponse();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithoutReferer(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutMatchingTag(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'
        ]);
        $response = new SlimResponse();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTagsearch(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/'
        ]);
        $response = new SlimResponse();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutReferer(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTag(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtag=abc'
        ]);
        $response = new SlimResponse();

        $result = $this->controller->removeTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTagWithoutReferer(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        $result = $this->controller->removeTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}

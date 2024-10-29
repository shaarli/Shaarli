<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class TagControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var TagController */    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new TagController($this->container);
    }

    public function testAddTagWithReferer(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/',
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndExistingSearch(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def',
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def%40abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutRefererAndExistingSearch(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagRemoveLegacyQueryParam(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&addtag=abc'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def%40abc'], $result->getHeader('location'));
    }

    public function testAddTagResetPagination(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def&page=12'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def%40abc'], $result->getHeader('location'));
    }

    public function testAddTagWithRefererAndEmptySearch(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags='
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['newTag' => 'abc'];

        $result = $this->controller->addTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=abc'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithReferer(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testAddTagWithoutNewTagWithoutReferer(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->addTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutMatchingTag(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtags=def'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtags=def'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTagsearch(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutReferer(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $tags = ['tag' => 'abc'];

        $result = $this->controller->removeTag($request, $response, $tags);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTag(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => 'http://shaarli/controller/?searchtag=abc'
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->removeTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/controller/?searchtag=abc'], $result->getHeader('location'));
    }

    public function testRemoveTagWithoutTagWithoutReferer(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->removeTag($request, $response, []);

        static::assertInstanceOf(Response::class, $result);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}

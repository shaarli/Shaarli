<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class TokenControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var TokenController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new TokenController($this->container);
    }

    public function testGetToken(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('generateToken')
            ->willReturn($token = 'token1234')
        ;

        $result = $this->controller->getToken($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame($token, (string) $result->getBody());
    }
}

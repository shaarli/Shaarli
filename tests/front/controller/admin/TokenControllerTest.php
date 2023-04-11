<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;

class TokenControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var TokenController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new TokenController($this->container);
    }

    public function testGetToken(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

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

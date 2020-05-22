<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

class ToolsControllerTestControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ToolsController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ToolsController($this->container);
    }

    public function testDefaultInvokeWithHttps(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->environment = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 443,
            'HTTPS' => 'on',
        ];

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tools', (string) $result->getBody());
        static::assertSame('https://shaarli', $assignedVariables['pageabsaddr']);
        static::assertTrue($assignedVariables['sslenabled']);
    }

    public function testDefaultInvokeWithoutHttps(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->environment = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
        ];

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tools', (string) $result->getBody());
        static::assertSame('http://shaarli', $assignedVariables['pageabsaddr']);
        static::assertFalse($assignedVariables['sslenabled']);
    }
}

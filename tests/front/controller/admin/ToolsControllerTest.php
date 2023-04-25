<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class ToolsControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ToolsController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new ToolsController($this->container);
    }

    public function testDefaultInvokeWithHttps(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 443,
            'HTTPS' => 'on',
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tools', (string) $result->getBody());
        static::assertSame('https://shaarli/', $assignedVariables['pageabsaddr']);
        static::assertTrue($assignedVariables['sslenabled']);
    }

    public function testDefaultInvokeWithoutHttps(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
        ];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('tools', (string) $result->getBody());
        static::assertSame('http://shaarli/', $assignedVariables['pageabsaddr']);
        static::assertFalse($assignedVariables['sslenabled']);
    }
}

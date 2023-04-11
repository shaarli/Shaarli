<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;

class ToolsControllerTest extends TestCase
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
        $request = (new FakeRequest())->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 443,
            'HTTPS' => 'on',
        ]);
        $response = new SlimResponse();

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
        $request = (new FakeRequest())->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80
        ]);
        $response = new SlimResponse();

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

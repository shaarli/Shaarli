<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class OpenSearchControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var OpenSearchController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new OpenSearchController($this->container);
    }

    public function testOpenSearchController(): void
    {
        $serverParams = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'SCRIPT_NAME' => '/subfolder/index.php',
        ];
        $request = $this->serverRequestFactory->createServerRequest('POST', 'http://shaarli', $serverParams);
        $response = $this->responseFactory->createResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString(
            'application/opensearchdescription+xml',
            $result->getHeader('Content-Type')[0]
        );
        static::assertSame('opensearch', (string) $result->getBody());
        static::assertSame('http://shaarli/subfolder/', $assignedVariables['serverurl']);
    }
}

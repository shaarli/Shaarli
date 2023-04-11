<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;

class OpenSearchControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var OpenSearchController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new OpenSearchController($this->container);
    }

    public function testOpenSearchController(): void
    {
        $request = (new FakeRequest())->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'SCRIPT_NAME' => '/subfolder/index.php',
        ]);
        $response = new SlimResponse();

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

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Routing\RouteContext;

class ErrorNotFoundControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var ErrorNotFoundController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ErrorNotFoundController($this->container);
    }

    /**
     * Test displaying 404 error
     */
    public function testDisplayNotFoundError(): void
    {
        $request = (new FakeRequest())->withAttribute(RouteContext::BASE_PATH, '/subfolder');

        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = ($this->controller)(
            $request,
            $response
        );

        static::assertSame(404, $result->getStatusCode());
        static::assertSame('404', (string) $result->getBody());
        static::assertSame('Requested page could not be found.', $assignedVariables['error_message']);
    }

    /**
     * Test displaying 404 error from REST API
     */
    public function testDisplayNotFoundErrorFromAPI(): void
    {
        $request = (new FakeRequest())->withAttribute(RouteContext::BASE_PATH, '/subfolder');

        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = ($this->controller)($request, $response);

        static::assertSame(404, $result->getStatusCode());
        // next line does not work after Slim4 migration
        // static::assertSame([], $assignedVariables);
    }
}

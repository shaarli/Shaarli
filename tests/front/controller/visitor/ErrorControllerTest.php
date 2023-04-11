<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Front\Exception\ShaarliFrontException;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class ErrorControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var ErrorController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ErrorController($this->container);
    }

    /**
     * Test displaying error with a ShaarliFrontException: display exception message and use its code for HTTTP code
     */
    public function testDisplayFrontExceptionError(): void
    {
        $request = (new FakeRequest(
            'POST',
            new Uri('', '')
        ))->withServerParams(['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80]);
        $response = new SlimResponse();

        $message = 'error message';
        $errorCode = 418;

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = ($this->controller)(
            $request,
            $response,
            new class ($message, $errorCode) extends ShaarliFrontException {
            }
        );

        static::assertSame($errorCode, $result->getStatusCode());
        static::assertSame($message, $assignedVariables['message']);
        static::assertArrayNotHasKey('stacktrace', $assignedVariables);
    }

    /**
     * Test displaying error with any exception (no debug) while logged in:
     * display full error details
     */
    public function testDisplayAnyExceptionErrorNoDebugLoggedIn(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $result = ($this->controller)($request, $response, new \Exception('abc'));

        static::assertSame(500, $result->getStatusCode());
        static::assertSame('Error: abc', $assignedVariables['message']);
        static::assertContainsPolyfill('Please report it on Github', $assignedVariables['text']);
        static::assertArrayHasKey('stacktrace', $assignedVariables);
    }

    /**
     * Test displaying error with any exception (no debug) while logged out:
     * display standard error without detail
     */
    public function testDisplayAnyExceptionErrorNoDebug(): void
    {
        $request = new FakeRequest();
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(false);

        $result = ($this->controller)($request, $response, new \Exception('abc'));

        static::assertSame(500, $result->getStatusCode());
        static::assertSame('An unexpected error occurred.', $assignedVariables['message']);
        static::assertArrayNotHasKey('text', $assignedVariables);
        static::assertArrayNotHasKey('stacktrace', $assignedVariables);
    }
}

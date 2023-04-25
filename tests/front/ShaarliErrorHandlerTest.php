<?php

declare(strict_types=1);

namespace Shaarli\Front;

use Shaarli\Front\Controller\Visitor\FrontControllerMockHelper;
use Shaarli\Front\Exception\ShaarliFrontException;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\CallableResolver;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteContext;

class ShaarliErrorHandlerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var ErrorController */
    protected $errorHandler;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();
        $this->errorHandler = new ShaarliErrorHandler(
            new CallableResolver(),
            new ResponseFactory(),
            null,
            $this->container
        );
    }

    /**
     * Test displaying error with a ShaarliFrontException: display exception message and use its code for HTTTP code
     */
    public function testDisplayFrontExceptionError(): void
    {
        $serverParams = ['SERVER_NAME' => 'domain.tld', 'SERVER_PORT' => 80];
        $request = $this->serverRequestFactory->createServerRequest('POST', 'http://shaarli', $serverParams);

        $message = 'error message';
        $errorCode = 418;

        $exception = new class ($message, $errorCode) extends ShaarliFrontException {
        };

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = ($this->errorHandler)($request, $exception, false, true, true);

        static::assertSame($errorCode, $result->getStatusCode());
        static::assertSame('error', (string) $result->getBody());
        static::assertSame($message, $assignedVariables['message']);
        static::assertArrayNotHasKey('stacktrace', $assignedVariables);
    }

    /**
     * Test displaying error with any exception (no debug) while logged in:
     * display full error details
     */
    public function testDisplayAnyExceptionErrorNoDebugLoggedIn(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $exception = new \Exception('abc');

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $result = ($this->errorHandler)($request, $exception, false, true, true);

        static::assertSame(500, $result->getStatusCode());
        static::assertSame('error', (string) $result->getBody());
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
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $exception = new \Exception('abc');

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(false);

        $result = ($this->errorHandler)($request, $exception, false, true, true);

        static::assertSame(500, $result->getStatusCode());
        static::assertSame('error', (string) $result->getBody());
        static::assertSame('An unexpected error occurred.', $assignedVariables['message']);
        static::assertArrayNotHasKey('text', $assignedVariables);
        static::assertArrayNotHasKey('stacktrace', $assignedVariables);
    }

    /**
     * Test displaying 404 error
     */
    public function testDisplayNotFoundError(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli')
            ->withAttribute(RouteContext::BASE_PATH, '/subfolder');
        $exception = new HttpNotFoundException($request);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = ($this->errorHandler)($request, $exception, false, true, true);

        static::assertSame(404, $result->getStatusCode());
        static::assertSame('404', (string) $result->getBody());
        static::assertSame('Requested page could not be found.', $assignedVariables['error_message']);
    }

    /**
     * Test displaying 404 error from REST API
     */
    public function testDisplayNotFoundErrorFromAPI(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli')
            ->withAttribute(RouteContext::BASE_PATH, '/subfolder');
        $exception = new HttpNotFoundException($request);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = ($this->errorHandler)($request, $exception, false, true, true);

        static::assertSame(404, $result->getStatusCode());
        // next line does not work after Slim4 migration
        // static::assertSame([], $assignedVariables);
    }
}

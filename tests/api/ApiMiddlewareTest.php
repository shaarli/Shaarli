<?php

namespace Shaarli\Api;

use DI\Container as DIContainer;
use Shaarli\Config\ConfigManager;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Tests\Utils\FakeRequest;
use Shaarli\Tests\Utils\FakeRequestHandler;
use Shaarli\Tests\Utils\ReferenceLinkDB;
use Shaarli\Tests\Utils\RequestHandlerFactory;

/**
 * Class ApiMiddlewareTest
 *
 * Test the REST API Slim Middleware.
 *
 * Note that we can't test a valid use case here, because the middleware
 * needs to call a valid controller/action during its execution.
 *
 * @package Api
 */
class ApiMiddlewareTest extends \Shaarli\TestCase
{
    /**
     * @var string datastore to test write operations
     */
    protected static $testDatastore = 'sandbox/datastore.php';

    /**
     * @var ConfigManager instance
     */
    protected $conf;

    /**
     * @var ReferenceLinkDB instance.
     */
    protected $refDB = null;

    /**
     * @var DIContainer instance.
     */
    protected $container;

    /**
     * @var RequestHandlerFactory instance
     */
    private $requestHandlerFactory;

    /**
     * Before every test, instantiate a new Api with its config, plugins and bookmarks.
     */
    protected function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->requestHandlerFactory = new RequestHandlerFactory();

        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('api.secret', 'NapoleonWasALizard');

        $this->refDB = new ReferenceLinkDB();
        $this->refDB->write(self::$testDatastore);

        $history = new History('sandbox/history.php');

        $this->container = new DIContainer();
        $this->container->set('conf', $this->conf);
        $this->container->set('history', $history);
        $this->container->set('pluginManager', new PluginManager($this->conf));
    }

    /**
     * After every test, remove the test datastore.
     */
    protected function tearDown(): void
    {
        @unlink(self::$testDatastore);
    }

    /**
     * Invoke the middleware with a valid token
     */
    public function testInvokeMiddlewareWithValidToken(): void
    {
        $mw = new ApiMiddleware($this->container);
        $token = 'Bearer ' . ApiUtilsTest::generateValidJwtToken('NapoleonWasALizard');
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello')
            ->withHeader('HTTP_AUTHORIZATION', $token);

        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $response = $mw($request, $requestHandler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Invoke the middleware with a valid token
     * Using specific Apache CGI redirected authorization.
     */
    public function testInvokeMiddlewareWithValidTokenFromRedirectedHeader(): void
    {
        $token = 'Bearer ' . ApiUtilsTest::generateValidJwtToken('NapoleonWasALizard');
        $mw = new ApiMiddleware($this->container);
        $serverParams = ['REDIRECT_HTTP_AUTHORIZATION' => $token];
        $request = $this->serverRequestFactory->createServerRequest('GET', 'http://shaarli/hello', $serverParams);
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();

        $response = $mw($request, $requestHandler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Invoke the middleware with the API disabled:
     * should return a 401 error Unauthorized.
     */
    public function testInvokeMiddlewareApiDisabled()
    {
        $this->conf->set('api.enabled', false);
        $this->container->set('conf', $this->conf);

        $mw = new ApiMiddleware($this->container);
        $token = 'Bearer ' . ApiUtilsTest::generateValidJwtToken('NapoleonWasALizard');
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello')
            ->withHeader('HTTP_AUTHORIZATION', $token);
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $response = $mw($request, $requestHandler);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody());
        $this->assertEquals('Not authorized', $body);
    }

    /**
     * Invoke the middleware with the API disabled in debug mode:
     * should return a 401 error Unauthorized - with a specific message and a stacktrace.
     */
    public function testInvokeMiddlewareApiDisabledDebug()
    {
        $this->conf->set('api.enabled', false);
        $this->conf->set('dev.debug', true);
        $this->container->set('conf', $this->conf);

        $mw = new ApiMiddleware($this->container);
        $token = 'Bearer ' . ApiUtilsTest::generateValidJwtToken('NapoleonWasALizard');
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello')
            ->withHeader('HTTP_AUTHORIZATION', $token);
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $response = $mw($request, $requestHandler);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody());
        $this->assertEquals('Not authorized: API is disabled', $body->message);
        $this->assertContainsPolyfill('ApiAuthorizationException', $body->stacktrace);
    }

    /**
     * Invoke the middleware without a token (debug):
     * should return a 401 error Unauthorized - with a specific message and a stacktrace.
     */
    public function testInvokeMiddlewareNoTokenProvidedDebug()
    {
        $this->conf->set('dev.debug', true);
        $this->container->set('conf', $this->conf);

        $mw = new ApiMiddleware($this->container);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello');
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $response = $mw($request, $requestHandler);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody());
        $this->assertEquals('Not authorized: JWT token not provided', $body->message);
        $this->assertContainsPolyfill('ApiAuthorizationException', $body->stacktrace);
    }

    /**
     * Invoke the middleware without a secret set in settings (debug):
     * should return a 401 error Unauthorized - with a specific message and a stacktrace.
     */
    public function testInvokeMiddlewareNoSecretSetDebug()
    {
        $this->conf->set('dev.debug', true);
        $this->conf->set('api.secret', '');
        $this->container->set('conf', $this->conf);

        $mw = new ApiMiddleware($this->container);
        $token = 'Bearer jwt';
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello')
            ->withHeader('HTTP_AUTHORIZATION', $token);
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();

        $response = $mw($request, $requestHandler);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody());
        $this->assertEquals('Not authorized: Token secret must be set in Shaarli\'s administration', $body->message);
        $this->assertContainsPolyfill('ApiAuthorizationException', $body->stacktrace);
    }

    /**
     * Invoke the middleware with an invalid JWT token header
     */
    public function testInvalidJwtAuthHeaderDebug()
    {
        $this->conf->set('dev.debug', true);
        $this->container->set('conf', $this->conf);

        $token = 'PolarBearer ' . ApiUtilsTest::generateValidJwtToken('NapoleonWasALizard');
        $mw = new ApiMiddleware($this->container);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello')
            ->withHeader('HTTP_AUTHORIZATION', $token);
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $response = $mw($request, $requestHandler);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody());
        $this->assertEquals('Not authorized: Invalid JWT header', $body->message);
        $this->assertContainsPolyfill('ApiAuthorizationException', $body->stacktrace);
    }

    /**
     * Invoke the middleware with an invalid JWT token (debug):
     * should return a 401 error Unauthorized - with a specific message and a stacktrace.
     *
     * Note: specific JWT errors tests are handled in ApiUtilsTest.
     */
    public function testInvokeMiddlewareInvalidJwtDebug()
    {
        $this->conf->set('dev.debug', true);
        $this->container->set('conf', $this->conf);

        $token = 'Bearer jwt';
        $mw = new ApiMiddleware($this->container);
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli/hello')
            ->withHeader('HTTP_AUTHORIZATION', $token);
        $requestHandler = $this->requestHandlerFactory->createRequestHandler();
        $response = $mw($request, $requestHandler);

        $this->assertEquals(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody());
        $this->assertEquals('Not authorized: Malformed JWT token', $body->message);
        $this->assertContainsPolyfill('ApiAuthorizationException', $body->stacktrace);
    }
}

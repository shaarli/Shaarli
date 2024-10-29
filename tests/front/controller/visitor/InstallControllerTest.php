<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\AlreadyInstalledException;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class InstallControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    protected const MOCK_FILE = '.tmp';

    /** @var InstallController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('getConfigFileExt')->willReturn(static::MOCK_FILE);
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $key, $default) {
            if ($key === 'resource.raintpl_tpl') {
                return '.';
            }

            return $default ?? $key;
        });

        $this->controller = new InstallController($this->container);
    }

    protected function tearDown(): void
    {
        if (file_exists(static::MOCK_FILE)) {
            unlink(static::MOCK_FILE);
        }
    }

    /**
     * Test displaying install page with valid session.
     */
    public function testInstallIndexWithValidSession(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->willReturnCallback(function (string $key, $default) {
                return $key === 'session_tested' ? 'Working' : $default;
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('install', (string) $result->getBody());

        static::assertIsArray($assignedVariables['continents']);
        static::assertSame('Africa', $assignedVariables['continents'][0]);
        static::assertSame('UTC', $assignedVariables['continents']['selected']);

        static::assertIsArray($assignedVariables['cities']);
        static::assertSame(['continent' => 'Africa', 'city' => 'Abidjan'], $assignedVariables['cities'][0]);
        static::assertSame('UTC', $assignedVariables['continents']['selected']);

        static::assertIsArray($assignedVariables['languages']);
        static::assertSame('Automatic', $assignedVariables['languages']['auto']);
        static::assertSame('French', $assignedVariables['languages']['fr']);

        static::assertSame(PHP_VERSION, $assignedVariables['php_version']);
        static::assertArrayHasKey('php_has_reached_eol', $assignedVariables);
        static::assertArrayHasKey('php_eol', $assignedVariables);
        static::assertArrayHasKey('php_extensions', $assignedVariables);
        static::assertArrayHasKey('permissions', $assignedVariables);
        static::assertEmpty($assignedVariables['permissions']);

        static::assertSame('Install Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Instantiate the install controller with an existing config file: exception.
     */
    public function testInstallWithExistingConfigFile(): void
    {
        $this->expectException(AlreadyInstalledException::class);

        touch(static::MOCK_FILE);

        $this->controller = new InstallController($this->container);
    }

    /**
     * Call controller without session yet defined, redirect to test session install page.
     */
    public function testInstallRedirectToSessionTest(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(InstallController::SESSION_TEST_KEY, InstallController::SESSION_TEST_VALUE)
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/install/session-test', $result->getHeader('location')[0]);
    }

    /**
     * Call controller in session test mode: valid session then redirect to install page.
     */
    public function testInstallSessionTestValid(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->with(InstallController::SESSION_TEST_KEY)
            ->willReturn(InstallController::SESSION_TEST_VALUE)
        ;

        $result = $this->controller->sessionTest($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/install', $result->getHeader('location')[0]);
    }

    /**
     * Call controller in session test mode: invalid session then redirect to error page.
     */
    public function testInstallSessionTestError(): void
    {
        $assignedVars = [];
        $this->assignTemplateVars($assignedVars);

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->set('sessionManager', $this->createMock(SessionManager::class));
        $this->container->get('sessionManager')
            ->method('getSessionParameter')
            ->with(InstallController::SESSION_TEST_KEY)
            ->willReturn('KO')
        ;

        $result = $this->controller->sessionTest($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('error', (string) $result->getBody());
        static::assertStringStartsWith(
            '<pre>Sessions do not seem to work correctly on your server',
            $assignedVars['message']
        );
    }

    /**
     * Test saving valid data from install form. Also initialize datastore.
     */
    public function testSaveInstallValid(): void
    {
        $providedParameters = [
            'continent' => 'Europe',
            'city' => 'Berlin',
            'setlogin' => 'bob',
            'setpassword' => 'password',
            'title' => 'Shaarli',
            'language' => 'fr',
            'updateCheck' => true,
            'enableApi' => true,
        ];

        $expectedSettings = [
            'general.timezone' => 'Europe/Berlin',
            'credentials.login' => 'bob',
            'credentials.salt' => '_NOT_EMPTY',
            'credentials.hash' => '_NOT_EMPTY',
            'general.title' => 'Shaarli',
            'translation.language' => 'en',
            'updates.check_updates' => true,
            'api.enabled' => true,
            'api.secret' => '_NOT_EMPTY',
            'general.header_link' => '/subfolder',
        ];

        $request = $this->serverRequestFactory->createServerRequest('POST', 'http://shaarli')
            ->withParsedBody(($providedParameters));

        $response = $this->responseFactory->createResponse();

        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')
            ->method('get')
            ->willReturnCallback(function (string $key, $value) {
                if ($key === 'credentials.login') {
                    return 'bob';
                } elseif ($key === 'credentials.salt') {
                    return 'salt';
                }

                return $value;
            })
        ;
        $this->container->get('conf')
            ->expects(static::exactly(count($expectedSettings)))
            ->method('set')
            ->willReturnCallback(function (string $key, $value) use ($expectedSettings) {
                if ($expectedSettings[$key] ?? null === '_NOT_EMPTY') {
                    static::assertNotEmpty($value);
                } else {
                    static::assertSame($expectedSettings[$key], $value);
                }
            })
        ;
        $this->container->get('conf')->expects(static::once())->method('write');

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES)
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/login', $result->getHeader('location')[0]);
    }

    /**
     * Test default settings (timezone and title).
     * Also check that bookmarks are not initialized if
     */
    public function testSaveInstallDefaultValues(): void
    {
        $confSettings = [];

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->get('conf')->method('set')
            ->willReturnCallback(function (string $key, $value) use (&$confSettings) {
                $confSettings[$key] = $value;
            });

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/login', $result->getHeader('location')[0]);

        static::assertSame('UTC', $confSettings['general.timezone']);
        static::assertSame('Shared Bookmarks', $confSettings['general.title']);
    }

    /**
     * Same test  as testSaveInstallDefaultValues() but for an instance install in root directory.
     */
    public function testSaveInstallDefaultValuesWithoutSubfolder(): void
    {
        $confSettings = [];

        $this->container->set('environment', [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => 80,
            'REQUEST_URI' => '/install',
            'REMOTE_ADDR' => '1.2.3.4',
            'SCRIPT_NAME' => '/index.php',
        ]);

        $this->container->set('basePath', '');

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->get('conf')->method('set')
            ->willReturnCallback(function (string $key, $value) use (&$confSettings) {
                $confSettings[$key] = $value;
            });

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/login', $result->getHeader('location')[0]);

        static::assertSame('UTC', $confSettings['general.timezone']);
        static::assertSame('Shared Bookmarks', $confSettings['general.title']);
    }
}

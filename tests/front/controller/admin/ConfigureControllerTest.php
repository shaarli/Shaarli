<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Security\SessionManager;
use Shaarli\Thumbnailer;
use Slim\Http\Request;
use Slim\Http\Response;

class ConfigureControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ConfigureController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ConfigureController($this->container);
    }

    /**
     * Test displaying configure page - it should display all config variables
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key) {
            return $key;
        });

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('configure', (string) $result->getBody());

        static::assertSame('Configure - general.title', $assignedVariables['pagetitle']);
        static::assertSame('general.title', $assignedVariables['title']);
        static::assertSame('resource.theme', $assignedVariables['theme']);
        static::assertEmpty($assignedVariables['theme_available']);
        static::assertSame(['default', 'markdown'], $assignedVariables['formatter_available']);
        static::assertNotEmpty($assignedVariables['continents']);
        static::assertNotEmpty($assignedVariables['cities']);
        static::assertSame('general.retrieve_description', $assignedVariables['retrieve_description']);
        static::assertSame('privacy.default_private_links', $assignedVariables['private_links_default']);
        static::assertSame('security.session_protection_disabled', $assignedVariables['session_protection_disabled']);
        static::assertSame('feed.rss_permalinks', $assignedVariables['enable_rss_permalinks']);
        static::assertSame('updates.check_updates', $assignedVariables['enable_update_check']);
        static::assertSame('privacy.hide_public_links', $assignedVariables['hide_public_links']);
        static::assertSame('api.enabled', $assignedVariables['api_enabled']);
        static::assertSame('api.secret', $assignedVariables['api_secret']);
        static::assertCount(4, $assignedVariables['languages']);
        static::assertArrayHasKey('gd_enabled', $assignedVariables);
        static::assertSame('thumbnails.mode', $assignedVariables['thumbnails_mode']);
    }

    /**
     * Test posting a new config - make sure that everything is saved properly, without errors.
     */
    public function testSaveNewConfig(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $parameters = [
            'token' => 'token',
            'continent' => 'Europe',
            'city' => 'Moscow',
            'title' => 'Shaarli',
            'titleLink' => './',
            'retrieveDescription' => 'on',
            'theme' => 'vintage',
            'disablesessionprotection' => null,
            'privateLinkByDefault' => true,
            'enableRssPermalinks' => true,
            'updateCheck' => false,
            'hidePublicLinks' => 'on',
            'enableApi' => 'on',
            'apiSecret' => 'abcdef',
            'formatter' => 'markdown',
            'language' => 'fr',
            'enableThumbnails' => Thumbnailer::MODE_NONE,
        ];

        $parametersConfigMapping = [
            'general.timezone' => $parameters['continent'] . '/' . $parameters['city'],
            'general.title' => $parameters['title'],
            'general.header_link' => $parameters['titleLink'],
            'general.retrieve_description' => !!$parameters['retrieveDescription'],
            'resource.theme' => $parameters['theme'],
            'security.session_protection_disabled' => !!$parameters['disablesessionprotection'],
            'privacy.default_private_links' => !!$parameters['privateLinkByDefault'],
            'feed.rss_permalinks' => !!$parameters['enableRssPermalinks'],
            'updates.check_updates' => !!$parameters['updateCheck'],
            'privacy.hide_public_links' => !!$parameters['hidePublicLinks'],
            'api.enabled' => !!$parameters['enableApi'],
            'api.secret' => $parameters['apiSecret'],
            'formatter' => $parameters['formatter'],
            'translation.language' => $parameters['language'],
            'thumbnails.mode' => $parameters['enableThumbnails'],
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')->willReturnCallback(function (string $key) use ($parameters) {
                if (false === array_key_exists($key, $parameters)) {
                    static::fail('unknown key: ' . $key);
                }

                return $parameters[$key];
            }
        );

        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf
            ->expects(static::atLeastOnce())
            ->method('set')
            ->willReturnCallback(function (string $key, $value) use ($parametersConfigMapping): void {
                if (false === array_key_exists($key, $parametersConfigMapping)) {
                    static::fail('unknown key: ' . $key);
                }

                static::assertSame($parametersConfigMapping[$key], $value);
            }
        );

        $result = $this->controller->save($request, $response);
        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./configure'], $result->getHeader('Location'));

        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['Configuration was saved.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);
    }

    /**
     * Test posting a new config - wrong token.
     */
    public function testSaveNewConfigWrongToken(): void
    {
        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(false);

        $this->container->conf->expects(static::never())->method('set');
        $this->container->conf->expects(static::never())->method('write');

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(WrongTokenException::class);

        $this->controller->save($request, $response);
    }

    /**
     * Test posting a new config - thumbnail activation.
     */
    public function testSaveNewConfigThumbnailsActivation(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')->willReturnCallback(function (string $key) {
                if ('enableThumbnails' === $key) {
                    return Thumbnailer::MODE_ALL;
                }

                return $key;
            })
        ;
        $response = new Response();

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./configure'], $result->getHeader('Location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertStringContainsString(
            'You have enabled or changed thumbnails mode',
            $session[SessionManager::KEY_WARNING_MESSAGES][0]
        );
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['Configuration was saved.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);
    }

    /**
     * Test posting a new config - thumbnail activation.
     */
    public function testSaveNewConfigThumbnailsAlreadyActive(): void
    {
        $session = [];
        $this->assignSessionVars($session);

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParam')->willReturnCallback(function (string $key) {
                if ('enableThumbnails' === $key) {
                    return Thumbnailer::MODE_ALL;
                }

                return $key;
            })
        ;
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf
            ->expects(static::atLeastOnce())
            ->method('get')
            ->willReturnCallback(function (string $key): string {
                if ('thumbnails.mode' === $key) {
                    return Thumbnailer::MODE_ALL;
                }

                return $key;
            })
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./configure'], $result->getHeader('Location'));

        static::assertArrayNotHasKey(SessionManager::KEY_ERROR_MESSAGES, $session);
        static::assertArrayNotHasKey(SessionManager::KEY_WARNING_MESSAGES, $session);
        static::assertArrayHasKey(SessionManager::KEY_SUCCESS_MESSAGES, $session);
        static::assertSame(['Configuration was saved.'], $session[SessionManager::KEY_SUCCESS_MESSAGES]);
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Config\ConfigManager;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

/**
 * Test Server administration controller.
 */
class ServerControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ServerController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ServerController($this->container);

        // initialize dummy cache
        @mkdir('sandbox/');
        foreach (['pagecache', 'tmp', 'cache'] as $folder) {
            @mkdir('sandbox/' . $folder);
            @touch('sandbox/' . $folder . '/.htaccess');
            @touch('sandbox/' . $folder . '/1');
            @touch('sandbox/' . $folder . '/2');
        }
    }

    public function tearDown(): void
    {
        foreach (['pagecache', 'tmp', 'cache'] as $folder) {
            @unlink('sandbox/' . $folder . '/.htaccess');
            @unlink('sandbox/' . $folder . '/1');
            @unlink('sandbox/' . $folder . '/2');
            @rmdir('sandbox/' . $folder);
        }
    }

    /**
     * Test default display of server administration page.
     */
    public function testIndex(): void
    {
        $request = (new FakeRequest())->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'REMOTE_ADDR' => '1.2.3.4',
        ]);
        $response = new SlimResponse();

       // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('server', (string) $result->getBody());

        static::assertSame(PHP_VERSION, $assignedVariables['php_version']);
        static::assertArrayHasKey('php_has_reached_eol', $assignedVariables);
        static::assertArrayHasKey('php_eol', $assignedVariables);
        static::assertArrayHasKey('php_extensions', $assignedVariables);
        static::assertArrayHasKey('permissions', $assignedVariables);
        static::assertEmpty($assignedVariables['permissions']);

        static::assertRegExp(
            '#https://github\.com/shaarli/Shaarli/releases/tag/v\d+\.\d+\.\d+#',
            $assignedVariables['release_url']
        );
        static::assertRegExp('#v\d+\.\d+\.\d+#', $assignedVariables['latest_version']);
        static::assertRegExp('#(v\d+\.\d+\.\d+|dev)#', $assignedVariables['current_version']);
        static::assertArrayHasKey('index_url', $assignedVariables);
        static::assertArrayHasKey('client_ip', $assignedVariables);
        static::assertArrayHasKey('trusted_proxies', $assignedVariables);

        static::assertSame('Server administration - Shaarli', $assignedVariables['pagetitle']);
    }

    /**
     * Test clearing the main cache
     */
    public function testClearMainCache(): void
    {
        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $key, $default) {
            if ($key === 'resource.page_cache') {
                return 'sandbox/pagecache';
            } elseif ($key === 'resource.raintpl_tmp') {
                return 'sandbox/tmp';
            } elseif ($key === 'resource.thumbnails_cache') {
                return 'sandbox/cache';
            } else {
                return $default;
            }
        });

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES, ['Shaarli\'s cache folder has been cleared!'])
        ;

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery('type=main')
        ));
        $response = new SlimResponse();

        $result = $this->controller->clearCache($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/admin/server', (string) $result->getHeaderLine('Location'));

        static::assertFileNotExists('sandbox/pagecache/1');
        static::assertFileNotExists('sandbox/pagecache/2');
        static::assertFileNotExists('sandbox/tmp/1');
        static::assertFileNotExists('sandbox/tmp/2');

        static::assertFileExists('sandbox/pagecache/.htaccess');
        static::assertFileExists('sandbox/tmp/.htaccess');
        static::assertFileExists('sandbox/cache');
        static::assertFileExists('sandbox/cache/.htaccess');
        static::assertFileExists('sandbox/cache/1');
        static::assertFileExists('sandbox/cache/2');
    }

    /**
     * Test clearing thumbnails cache
     */
    public function testClearThumbnailsCache(): void
    {
        $this->container->set('conf', $this->createMock(ConfigManager::class));
        $this->container->get('conf')->method('get')->willReturnCallback(function (string $key, $default) {
            if ($key === 'resource.page_cache') {
                return 'sandbox/pagecache';
            } elseif ($key === 'resource.raintpl_tmp') {
                return 'sandbox/tmp';
            } elseif ($key === 'resource.thumbnails_cache') {
                return 'sandbox/cache';
            } else {
                return $default;
            }
        });

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->willReturnCallback(function (string $key, array $value): SessionManager {
                static::assertSame(SessionManager::KEY_WARNING_MESSAGES, $key);
                static::assertCount(1, $value);
                static::assertStringStartsWith('Thumbnails cache has been cleared.', $value[0]);

                return $this->container->get('sessionManager');
            });
        ;

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery('type=thumbnails')
        ));
        $response = new SlimResponse();

        $result = $this->controller->clearCache($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame('/subfolder/admin/server', (string) $result->getHeaderLine('Location'));

        static::assertFileNotExists('sandbox/cache/1');
        static::assertFileNotExists('sandbox/cache/2');

        static::assertFileExists('sandbox/cache/.htaccess');
        static::assertFileExists('sandbox/pagecache');
        static::assertFileExists('sandbox/pagecache/.htaccess');
        static::assertFileExists('sandbox/pagecache/1');
        static::assertFileExists('sandbox/pagecache/2');
        static::assertFileExists('sandbox/tmp');
        static::assertFileExists('sandbox/tmp/.htaccess');
        static::assertFileExists('sandbox/tmp/1');
        static::assertFileExists('sandbox/tmp/2');
    }
}

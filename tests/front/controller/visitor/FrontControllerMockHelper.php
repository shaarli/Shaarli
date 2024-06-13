<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use DI\Container as DIContainer;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;

/**
 * Trait FrontControllerMockHelper
 *
 * Helper trait used to initialize the Container and mock its services for controller tests.
 *
 * @property Container $container
 * @package Shaarli\Front\Controller
 */
trait FrontControllerMockHelper
{
    /** @var Container */
    protected $container;

    /**
     * Mock the container instance and initialize container's services used by tests
     */
    protected function createContainer(): void
    {
        $this->container = new DIContainer();

        $this->container->set('loginManager', $this->createMock(LoginManager::class));

        // Config
        $conf = $this->createMock(ConfigManager::class);
        $conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            if ($parameter === 'general.tags_separator') {
                return '@';
            }

            return $default === null ? $parameter : $default;
        });
        $this->container->set('conf', $conf);

        // PageBuilder
        $this->container->set('pageBuilder', $this->createMock(PageBuilder::class));
        $this->container->get('pageBuilder')
            ->method('render')
            ->willReturnCallback(function (string $template): string {
                return $template;
            })
        ;

        // Plugin Manager
        $this->container->set('pluginManager', $this->createMock(PluginManager::class));

        // BookmarkService
        $this->container->set('bookmarkService', $this->createMock(BookmarkServiceInterface::class));

        // Formatter
        $this->container->set('formatterFactory', $this->createMock(FormatterFactory::class));
        $this->container->get('formatterFactory')
            ->method('getFormatter')
            ->willReturnCallback(function (): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->get('conf'), true);
            })
        ;

        // CacheManager
        $this->container->set('pageCacheManager', $this->createMock(PageCacheManager::class));

        // SessionManager
        $this->container->set('sessionManager', $this->createMock(SessionManager::class));

        $this->container->set('basePath', '/subfolder');
    }

    /**
     * Pass a reference of an array which will be populated by `pageBuilder->assign` calls during execution.
     *
     * @param mixed $variables Array reference to populate.
     */
    protected function assignTemplateVars(array &$variables): void
    {
        $this->container->get('pageBuilder')
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use (&$variables) {
                $variables[$key] = $value;

                return $this;
            })
        ;
    }

    protected static function generateString(int $length): string
    {
        // bin2hex(random_bytes) generates string twice as long as given parameter
        $length = (int) ceil($length / 2);

        return bin2hex(random_bytes($length));
    }

    /**
     * Force to be used in PHPUnit context.
     */
    abstract protected function isInTestsContext(): bool;
}

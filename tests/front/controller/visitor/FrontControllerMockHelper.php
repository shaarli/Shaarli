<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\MockObject\MockObject;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ShaarliTestContainer;
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
 * Helper trait used to initialize the ShaarliContainer and mock its services for controller tests.
 *
 * @property ShaarliTestContainer $container
 * @package Shaarli\Front\Controller
 */
trait FrontControllerMockHelper
{
    /** @var ShaarliTestContainer */
    protected $container;

    /**
     * Mock the container instance and initialize container's services used by tests
     */
    protected function createContainer(): void
    {
        $this->container = $this->createMock(ShaarliTestContainer::class);

        $this->container->loginManager = $this->createMock(LoginManager::class);

        // Config
        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            return $default === null ? $parameter : $default;
        });

        // PageBuilder
        $this->container->pageBuilder = $this->createMock(PageBuilder::class);
        $this->container->pageBuilder
            ->method('render')
            ->willReturnCallback(function (string $template): string {
                return $template;
            })
        ;

        // Plugin Manager
        $this->container->pluginManager = $this->createMock(PluginManager::class);

        // BookmarkService
        $this->container->bookmarkService = $this->createMock(BookmarkServiceInterface::class);

        // Formatter
        $this->container->formatterFactory = $this->createMock(FormatterFactory::class);
        $this->container->formatterFactory
            ->method('getFormatter')
            ->willReturnCallback(function (): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->conf, true);
            })
        ;

        // CacheManager
        $this->container->pageCacheManager = $this->createMock(PageCacheManager::class);

        // SessionManager
        $this->container->sessionManager = $this->createMock(SessionManager::class);

        // $_SERVER
        $this->container->environment = [
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'REQUEST_URI' => '/daily-rss',
        ];
    }

    /**
     * Pass a reference of an array which will be populated by `pageBuilder->assign` calls during execution.
     *
     * @param mixed $variables Array reference to populate.
     */
    protected function assignTemplateVars(array &$variables): void
    {
        $this->container->pageBuilder
            ->expects(static::atLeastOnce())
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
    protected abstract function createMock($originalClassName): MockObject;
}

<?php

declare(strict_types=1);

namespace Shaarli\Container;

use Psr\Log\LoggerInterface;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Front\Controller\Visitor\ErrorController;
use Shaarli\Front\Controller\Visitor\ErrorNotFoundController;
use Shaarli\History;
use Shaarli\Http\HttpAccess;
use Shaarli\Http\MetadataRetriever;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\CookieManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Thumbnailer;
use Shaarli\Updater\Updater;
use Slim\Http\Environment;

class ContainerBuilderTest extends TestCase
{
    /** @var ConfigManager */
    protected $conf;

    /** @var SessionManager */
    protected $sessionManager;

    /** @var LoginManager */
    protected $loginManager;

    /** @var ContainerBuilder */
    protected $containerBuilder;

    /** @var CookieManager */
    protected $cookieManager;

    /** @var PluginManager */
    protected $pluginManager;

    public function setUp(): void
    {
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->sessionManager = $this->createMock(SessionManager::class);
        $this->cookieManager = $this->createMock(CookieManager::class);
        $this->pluginManager = $this->createMock(PluginManager::class);

        $this->loginManager = $this->createMock(LoginManager::class);
        $this->loginManager->method('isLoggedIn')->willReturn(true);

        $this->containerBuilder = new ContainerBuilder(
            $this->conf,
            $this->sessionManager,
            $this->cookieManager,
            $this->loginManager,
            $this->pluginManager,
			$this->createMock(LoggerInterface::class)
        );
    }

    public function testBuildContainer(): void
    {
        $container = $this->containerBuilder->build();

        static::assertInstanceOf(BookmarkServiceInterface::class, $container->bookmarkService);
        static::assertInstanceOf(CookieManager::class, $container->cookieManager);
        static::assertInstanceOf(ConfigManager::class, $container->conf);
        static::assertInstanceOf(ErrorController::class, $container->errorHandler);
        static::assertInstanceOf(Environment::class, $container->environment);
        static::assertInstanceOf(FeedBuilder::class, $container->feedBuilder);
        static::assertInstanceOf(FormatterFactory::class, $container->formatterFactory);
        static::assertInstanceOf(History::class, $container->history);
        static::assertInstanceOf(HttpAccess::class, $container->httpAccess);
        static::assertInstanceOf(LoginManager::class, $container->loginManager);
        static::assertInstanceOf(LoggerInterface::class, $container->logger);
        static::assertInstanceOf(MetadataRetriever::class, $container->metadataRetriever);
        static::assertInstanceOf(NetscapeBookmarkUtils::class, $container->netscapeBookmarkUtils);
        static::assertInstanceOf(PageBuilder::class, $container->pageBuilder);
        static::assertInstanceOf(PageCacheManager::class, $container->pageCacheManager);
        static::assertInstanceOf(ErrorController::class, $container->phpErrorHandler);
        static::assertInstanceOf(ErrorNotFoundController::class, $container->notFoundHandler);
        static::assertInstanceOf(PluginManager::class, $container->pluginManager);
        static::assertInstanceOf(SessionManager::class, $container->sessionManager);
        static::assertInstanceOf(Thumbnailer::class, $container->thumbnailer);
        static::assertInstanceOf(Updater::class, $container->updater);

        // Set by the middleware
        static::assertNull($container->basePath);
    }
}

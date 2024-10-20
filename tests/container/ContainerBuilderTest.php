<?php

declare(strict_types=1);

namespace Shaarli\Container;

use Psr\Log\LoggerInterface;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\FormatterFactory;
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

        static::assertInstanceOf(BookmarkServiceInterface::class, $container->get('bookmarkService'));
        static::assertInstanceOf(CookieManager::class, $container->get('cookieManager'));
        static::assertInstanceOf(ConfigManager::class, $container->get('conf'));
        static::assertInstanceOf(FeedBuilder::class, $container->get('feedBuilder'));
        static::assertInstanceOf(FormatterFactory::class, $container->get('formatterFactory'));
        static::assertInstanceOf(History::class, $container->get('history'));
        static::assertInstanceOf(HttpAccess::class, $container->get('httpAccess'));
        static::assertInstanceOf(LoginManager::class, $container->get('loginManager'));
        static::assertInstanceOf(LoggerInterface::class, $container->get('logger'));
        static::assertInstanceOf(MetadataRetriever::class, $container->get('metadataRetriever'));
        static::assertInstanceOf(NetscapeBookmarkUtils::class, $container->get('netscapeBookmarkUtils'));
        static::assertInstanceOf(PageBuilder::class, $container->get('pageBuilder'));
        static::assertInstanceOf(PageCacheManager::class, $container->get('pageCacheManager'));
        static::assertInstanceOf(PluginManager::class, $container->get('pluginManager'));
        static::assertInstanceOf(SessionManager::class, $container->get('sessionManager'));
        static::assertInstanceOf(Thumbnailer::class, $container->get('thumbnailer'));
        static::assertInstanceOf(Updater::class, $container->get('updater'));

        // Set by the middleware
        static::assertNull($container->get('basePath'));
    }
}

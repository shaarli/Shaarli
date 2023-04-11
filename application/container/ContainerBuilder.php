<?php

declare(strict_types=1);

namespace Shaarli\Container;

use DI\Container;
use malkusch\lock\mutex\FlockMutex;
use Psr\Log\LoggerInterface;
use Shaarli\Bookmark\BookmarkFileService;
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
use Shaarli\Thumbnailer;
use Shaarli\Updater\Updater;
use Shaarli\Updater\UpdaterUtils;

/**
 * Class ContainerBuilder
 *
 * Helper used to build a Slim container instance with Shaarli's object dependencies.
 * Note that most injected objects MUST be added as closures, to let the container instantiate
 * only the objects it requires during the execution.
 *
 * @package Container
 */
class ContainerBuilder
{
    /** @var ConfigManager */
    protected $conf;

    /** @var SessionManager */
    protected $session;

    /** @var CookieManager */
    protected $cookieManager;

    /** @var LoginManager */
    protected $login;

    /** @var PluginManager */
    protected $pluginManager;

    /** @var LoggerInterface */
    protected $logger;

    /** @var string|null */
    protected $basePath = null;

    public function __construct(
        ConfigManager $conf,
        SessionManager $session,
        CookieManager $cookieManager,
        LoginManager $login,
        PluginManager $pluginManager,
        LoggerInterface $logger
    ) {
        $this->conf = $conf;
        $this->session = $session;
        $this->login = $login;
        $this->cookieManager = $cookieManager;
        $this->pluginManager = $pluginManager;
        $this->logger = $logger;
    }

    public function build(): Container
    {
        $container = new Container();

        $container->set('conf', $this->conf);
        $container->set('sessionManager', $this->session);
        $container->set('cookieManager', $this->cookieManager);
        $container->set('loginManager', $this->login);
        $container->set('pluginManager', $this->pluginManager);
        $container->set('logger', $this->logger);
        $container->set('basePath', $this->basePath);


        $container->set('history', function (Container $container): History {
            return new History($container->get('conf')->get('resource.history'));
        });

        $container->set('bookmarkService', function (Container $container): BookmarkServiceInterface {
            return new BookmarkFileService(
                $container->get('conf'),
                $container->get('pluginManager'),
                $container->get('history'),
                new FlockMutex(fopen(SHAARLI_MUTEX_FILE, 'r'), 2),
                $container->get('loginManager')->isLoggedIn()
            );
        });

        $container->set('metadataRetriever', function (Container $container): MetadataRetriever {
            return new MetadataRetriever($container->get('conf'), $container->get('httpAccess'));
        });

        $container->set('pageBuilder', function (Container $container): PageBuilder {
            $conf = $container->get('conf');
            return new PageBuilder(
                $conf,
                $container->get('sessionManager')->getSession(),
                $container->get('logger'),
                $container->get('bookmarkService'),
                $container->get('sessionManager')->generateToken(),
                $container->get('loginManager')->isLoggedIn()
            );
        });

        $container->set('formatterFactory', function (Container $container): FormatterFactory {
            return new FormatterFactory(
                $container->get('conf'),
                $container->get('loginManager')->isLoggedIn()
            );
        });

        $container->set('pageCacheManager', function (Container $container): PageCacheManager {
            return new PageCacheManager(
                $container->get('conf')->get('resource.page_cache'),
                $container->get('loginManager')->isLoggedIn()
            );
        });

        $container->set('feedBuilder', function (Container $container): FeedBuilder {
            return new FeedBuilder(
                $container->get('bookmarkService'),
                $container->get('formatterFactory')->getFormatter(),
                $container->get('loginManager')->isLoggedIn()
            );
        });

        $container->set('thumbnailer', function (Container $container): Thumbnailer {
            return new Thumbnailer($container->get('conf'));
        });

        $container->set('httpAccess', function (): HttpAccess {
            return new HttpAccess();
        });

        $container->set('netscapeBookmarkUtils', function (Container $container): NetscapeBookmarkUtils {
            return new NetscapeBookmarkUtils(
                $container->get('bookmarkService'),
                $container->get('conf'),
                $container->get('history')
            );
        });

        $container->set('updater', function (Container $container): Updater {
            return new Updater(
                UpdaterUtils::readUpdatesFile($container->get('conf')->get('resource.updates')),
                $container->get('bookmarkService'),
                $container->get('conf'),
                $container->get('loginManager')->isLoggedIn()
            );
        });

        return $container;
    }
}

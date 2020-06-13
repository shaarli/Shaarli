<?php

declare(strict_types=1);

namespace Shaarli\Container;

use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\History;
use Shaarli\Http\HttpAccess;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;
use Shaarli\Thumbnailer;
use Slim\Container;

/**
 * Extension of Slim container to document the injected objects.
 *
 * @property string                   $basePath        Shaarli's instance base path (e.g. `/shaarli/`)
 * @property BookmarkServiceInterface $bookmarkService
 * @property ConfigManager            $conf
 * @property mixed[]                  $environment     $_SERVER automatically injected by Slim
 * @property FeedBuilder              $feedBuilder
 * @property FormatterFactory         $formatterFactory
 * @property History                  $history
 * @property HttpAccess               $httpAccess
 * @property LoginManager             $loginManager
 * @property PageBuilder              $pageBuilder
 * @property PageCacheManager         $pageCacheManager
 * @property PluginManager            $pluginManager
 * @property SessionManager           $sessionManager
 * @property Thumbnailer              $thumbnailer
 */
class ShaarliContainer extends Container
{

}

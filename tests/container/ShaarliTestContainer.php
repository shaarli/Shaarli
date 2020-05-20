<?php

declare(strict_types=1);

namespace Shaarli\Container;

use PHPUnit\Framework\MockObject\MockObject;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Feed\FeedBuilder;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\History;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Render\PageCacheManager;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;

/**
 * Test helper allowing auto-completion for MockObjects.
 *
 * @property mixed[]                             $environment     $_SERVER automatically injected by Slim
 * @property MockObject|ConfigManager            $conf
 * @property MockObject|SessionManager           $sessionManager
 * @property MockObject|LoginManager             $loginManager
 * @property MockObject|string                   $webPath
 * @property MockObject|History                  $history
 * @property MockObject|BookmarkServiceInterface $bookmarkService
 * @property MockObject|PageBuilder              $pageBuilder
 * @property MockObject|PluginManager            $pluginManager
 * @property MockObject|FormatterFactory         $formatterFactory
 * @property MockObject|PageCacheManager         $pageCacheManager
 * @property MockObject|FeedBuilder              $feedBuilder
 */
class ShaarliTestContainer extends ShaarliContainer
{

}

<?php

declare(strict_types=1);

namespace Shaarli\Container;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\History;
use Shaarli\Render\PageBuilder;
use Shaarli\Security\LoginManager;
use Shaarli\Security\SessionManager;

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

    public function setUp(): void
    {
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->sessionManager = $this->createMock(SessionManager::class);

        $this->loginManager = $this->createMock(LoginManager::class);
        $this->loginManager->method('isLoggedIn')->willReturn(true);

        $this->containerBuilder = new ContainerBuilder($this->conf, $this->sessionManager, $this->loginManager);
    }

    public function testBuildContainer(): void
    {
        $container = $this->containerBuilder->build();

        static::assertInstanceOf(ConfigManager::class, $container->conf);
        static::assertInstanceOf(SessionManager::class, $container->sessionManager);
        static::assertInstanceOf(LoginManager::class, $container->loginManager);
        static::assertInstanceOf(History::class, $container->history);
        static::assertInstanceOf(BookmarkServiceInterface::class, $container->bookmarkService);
        static::assertInstanceOf(PageBuilder::class, $container->pageBuilder);
        static::assertInstanceOf(FormatterFactory::class, $container->formatterFactory);
    }
}

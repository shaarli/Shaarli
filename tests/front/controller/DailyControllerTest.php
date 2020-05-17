<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Container\ShaarliContainer;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\Formatter\BookmarkRawFormatter;
use Shaarli\Formatter\FormatterFactory;
use Shaarli\Plugin\PluginManager;
use Shaarli\Render\PageBuilder;
use Shaarli\Security\LoginManager;
use Slim\Http\Request;
use Slim\Http\Response;

class DailyControllerTest extends TestCase
{
    /** @var ShaarliContainer */
    protected $container;

    /** @var DailyController */
    protected $controller;

    public function setUp(): void
    {
        $this->container = $this->createMock(ShaarliContainer::class);
        $this->controller = new DailyController($this->container);
    }

    public function testValidControllerInvokeDefault(): void
    {
        $this->createValidContainerMockSet();

        $currentDay = new \DateTimeImmutable('2020-05-13');

        $request = $this->createMock(Request::class);
        $request->method('getQueryParam')->willReturn($currentDay->format('Ymd'));
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        // Links dataset: 2 links with thumbnails
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('days')
            ->willReturnCallback(function () use ($currentDay): array {
               return [
                   '20200510',
                   $currentDay->format('Ymd'),
                   '20200516',
               ];
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('filterDay')
            ->willReturnCallback(function (): array {
                return [
                    (new Bookmark())
                        ->setId(1)
                        ->setUrl('http://url.tld')
                        ->setTitle(static::generateContent(50))
                        ->setDescription(static::generateContent(500))
                    ,
                    (new Bookmark())
                        ->setId(2)
                        ->setUrl('http://url2.tld')
                        ->setTitle(static::generateContent(50))
                        ->setDescription(static::generateContent(500))
                    ,
                    (new Bookmark())
                        ->setId(3)
                        ->setUrl('http://url3.tld')
                        ->setTitle(static::generateContent(50))
                        ->setDescription(static::generateContent(500))
                    ,
                ];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param) use ($currentDay): array {
                static::assertSame('render_daily', $hook);

                static::assertArrayHasKey('linksToDisplay', $data);
                static::assertCount(3, $data['linksToDisplay']);
                static::assertSame(1, $data['linksToDisplay'][0]['id']);
                static::assertSame($currentDay->getTimestamp(), $data['day']);
                static::assertSame('20200510', $data['previousday']);
                static::assertSame('20200516', $data['nextday']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            });

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Daily - '. format_date($currentDay, false, true) .' - Shaarli',
            $assignedVariables['pagetitle']
        );
        static::assertCount(3, $assignedVariables['linksToDisplay']);

        $link = $assignedVariables['linksToDisplay'][0];

        static::assertSame(1, $link['id']);
        static::assertSame('http://url.tld', $link['url']);
        static::assertNotEmpty($link['title']);
        static::assertNotEmpty($link['description']);
        static::assertNotEmpty($link['formatedDescription']);

        $link = $assignedVariables['linksToDisplay'][1];

        static::assertSame(2, $link['id']);
        static::assertSame('http://url2.tld', $link['url']);
        static::assertNotEmpty($link['title']);
        static::assertNotEmpty($link['description']);
        static::assertNotEmpty($link['formatedDescription']);

        $link = $assignedVariables['linksToDisplay'][2];

        static::assertSame(3, $link['id']);
        static::assertSame('http://url3.tld', $link['url']);
        static::assertNotEmpty($link['title']);
        static::assertNotEmpty($link['description']);
        static::assertNotEmpty($link['formatedDescription']);

        static::assertCount(3, $assignedVariables['cols']);
        static::assertCount(1, $assignedVariables['cols'][0]);
        static::assertCount(1, $assignedVariables['cols'][1]);
        static::assertCount(1, $assignedVariables['cols'][2]);

        $link = $assignedVariables['cols'][0][0];

        static::assertSame(1, $link['id']);
        static::assertSame('http://url.tld', $link['url']);
        static::assertNotEmpty($link['title']);
        static::assertNotEmpty($link['description']);
        static::assertNotEmpty($link['formatedDescription']);

        $link = $assignedVariables['cols'][1][0];

        static::assertSame(2, $link['id']);
        static::assertSame('http://url2.tld', $link['url']);
        static::assertNotEmpty($link['title']);
        static::assertNotEmpty($link['description']);
        static::assertNotEmpty($link['formatedDescription']);

        $link = $assignedVariables['cols'][2][0];

        static::assertSame(3, $link['id']);
        static::assertSame('http://url3.tld', $link['url']);
        static::assertNotEmpty($link['title']);
        static::assertNotEmpty($link['description']);
        static::assertNotEmpty($link['formatedDescription']);
    }

    /**
     * Daily page - test that everything goes fine with no future or past bookmarks
     */
    public function testValidControllerInvokeNoFutureOrPast(): void
    {
        $this->createValidContainerMockSet();

        $currentDay = new \DateTimeImmutable('2020-05-13');

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        // Links dataset: 2 links with thumbnails
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('days')
            ->willReturnCallback(function () use ($currentDay): array {
                return [
                    $currentDay->format($currentDay->format('Ymd')),
                ];
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('filterDay')
            ->willReturnCallback(function (): array {
                return [
                    (new Bookmark())
                        ->setId(1)
                        ->setUrl('http://url.tld')
                        ->setTitle(static::generateContent(50))
                        ->setDescription(static::generateContent(500))
                    ,
                ];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param) use ($currentDay): array {
                static::assertSame('render_daily', $hook);

                static::assertArrayHasKey('linksToDisplay', $data);
                static::assertCount(1, $data['linksToDisplay']);
                static::assertSame(1, $data['linksToDisplay'][0]['id']);
                static::assertSame($currentDay->getTimestamp(), $data['day']);
                static::assertEmpty($data['previousday']);
                static::assertEmpty($data['nextday']);

                static::assertArrayHasKey('loggedin', $param);

                return $data;
            });

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Daily - '. format_date($currentDay, false, true) .' - Shaarli',
            $assignedVariables['pagetitle']
        );
        static::assertCount(1, $assignedVariables['linksToDisplay']);

        $link = $assignedVariables['linksToDisplay'][0];
        static::assertSame(1, $link['id']);
    }

    /**
     * Daily page - test that height adjustment in columns is working
     */
    public function testValidControllerInvokeHeightAdjustment(): void
    {
        $this->createValidContainerMockSet();

        $currentDay = new \DateTimeImmutable('2020-05-13');

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        // Links dataset: 2 links with thumbnails
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('days')
            ->willReturnCallback(function () use ($currentDay): array {
                return [
                    $currentDay->format($currentDay->format('Ymd')),
                ];
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('filterDay')
            ->willReturnCallback(function (): array {
                return [
                    (new Bookmark())->setId(1)->setUrl('http://url.tld')->setTitle('title'),
                    (new Bookmark())
                        ->setId(2)
                        ->setUrl('http://url.tld')
                        ->setTitle(static::generateContent(50))
                        ->setDescription(static::generateContent(5000))
                    ,
                    (new Bookmark())->setId(3)->setUrl('http://url.tld')->setTitle('title'),
                    (new Bookmark())->setId(4)->setUrl('http://url.tld')->setTitle('title'),
                    (new Bookmark())->setId(5)->setUrl('http://url.tld')->setTitle('title'),
                    (new Bookmark())->setId(6)->setUrl('http://url.tld')->setTitle('title'),
                    (new Bookmark())->setId(7)->setUrl('http://url.tld')->setTitle('title'),
                ];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                return $data;
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertCount(7, $assignedVariables['linksToDisplay']);

        $columnIds = function (array $column): array {
            return array_map(function (array $item): int { return $item['id']; }, $column);
        };

        static::assertSame([1, 4, 6], $columnIds($assignedVariables['cols'][0]));
        static::assertSame([2], $columnIds($assignedVariables['cols'][1]));
        static::assertSame([3, 5, 7], $columnIds($assignedVariables['cols'][2]));
    }

    /**
     * Daily page - no bookmark
     */
    public function testValidControllerInvokeNoBookmark(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        // Links dataset: 2 links with thumbnails
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('days')
            ->willReturnCallback(function (): array {
                return [];
            })
        ;
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('filterDay')
            ->willReturnCallback(function (): array {
                return [];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->pluginManager
            ->expects(static::at(0))
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                return $data;
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertCount(0, $assignedVariables['linksToDisplay']);
        static::assertSame('Today', $assignedVariables['dayDesc']);
    }

    protected function createValidContainerMockSet(): void
    {
        $loginManager = $this->createMock(LoginManager::class);
        $this->container->loginManager = $loginManager;

        // Config
        $conf = $this->createMock(ConfigManager::class);
        $this->container->conf = $conf;
        $this->container->conf->method('get')->willReturnCallback(function (string $parameter, $default) {
            return $default;
        });

        // PageBuilder
        $pageBuilder = $this->createMock(PageBuilder::class);
        $pageBuilder
            ->method('render')
            ->willReturnCallback(function (string $template): string {
                return $template;
            })
        ;
        $this->container->pageBuilder = $pageBuilder;

        // Plugin Manager
        $pluginManager = $this->createMock(PluginManager::class);
        $this->container->pluginManager = $pluginManager;

        // BookmarkService
        $bookmarkService = $this->createMock(BookmarkServiceInterface::class);
        $this->container->bookmarkService = $bookmarkService;

        // Formatter
        $formatterFactory = $this->createMock(FormatterFactory::class);
        $formatterFactory
            ->method('getFormatter')
            ->willReturnCallback(function (): BookmarkFormatter {
                return new BookmarkRawFormatter($this->container->conf, true);
            })
        ;
        $this->container->formatterFactory = $formatterFactory;
    }

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

    protected static function generateContent(int $length): string
    {
        // bin2hex(random_bytes) generates string twice as long as given parameter
        $length = (int) ceil($length / 2);
        return bin2hex(random_bytes($length));
    }
}

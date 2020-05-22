<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Feed\CachedPage;
use Slim\Http\Request;
use Slim\Http\Response;

class DailyControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var DailyController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new DailyController($this->container);
        DailyController::$DAILY_RSS_NB_DAYS = 2;
    }

    public function testValidIndexControllerInvokeDefault(): void
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
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Daily - '. format_date($currentDay, false, true) .' - Shaarli',
            $assignedVariables['pagetitle']
        );
        static::assertEquals($currentDay, $assignedVariables['dayDate']);
        static::assertEquals($currentDay->getTimestamp(), $assignedVariables['day']);
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
    public function testValidIndexControllerInvokeNoFutureOrPast(): void
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
    public function testValidIndexControllerInvokeHeightAdjustment(): void
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
    public function testValidIndexControllerInvokeNoBookmark(): void
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
        static::assertEquals((new \DateTime())->setTime(0, 0)->getTimestamp(), $assignedVariables['day']);
        static::assertEquals((new \DateTime())->setTime(0, 0), $assignedVariables['dayDate']);
    }

    /**
     * Daily RSS - default behaviour
     */
    public function testValidRssControllerInvokeDefault(): void
    {
        $this->createValidContainerMockSet();

        $dates = [
            new \DateTimeImmutable('2020-05-17'),
            new \DateTimeImmutable('2020-05-15'),
            new \DateTimeImmutable('2020-05-13'),
        ];

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->bookmarkService->expects(static::once())->method('search')->willReturn([
            (new Bookmark())->setId(1)->setCreated($dates[0])->setUrl('http://domain.tld/1'),
            (new Bookmark())->setId(2)->setCreated($dates[1])->setUrl('http://domain.tld/2'),
            (new Bookmark())->setId(3)->setCreated($dates[1])->setUrl('http://domain.tld/3'),
            (new Bookmark())->setId(4)->setCreated($dates[2])->setUrl('http://domain.tld/4'),
        ]);

        $this->container->pageCacheManager
            ->expects(static::once())
            ->method('getCachePage')
            ->willReturnCallback(function (): CachedPage {
                $cachedPage = $this->createMock(CachedPage::class);
                $cachedPage->expects(static::once())->method('cache')->with('dailyrss');

                return $cachedPage;
            }
        );

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('dailyrss', (string) $result->getBody());
        static::assertSame('Shaarli', $assignedVariables['title']);
        static::assertSame('http://shaarli', $assignedVariables['index_url']);
        static::assertSame('http://shaarli/daily-rss', $assignedVariables['page_url']);
        static::assertFalse($assignedVariables['hide_timestamps']);
        static::assertCount(2, $assignedVariables['days']);

        $day = $assignedVariables['days'][$dates[0]->format('Ymd')];

        static::assertEquals($dates[0], $day['date']);
        static::assertSame($dates[0]->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame(format_date($dates[0], false), $day['date_human']);
        static::assertSame('http://shaarli/daily?day='. $dates[0]->format('Ymd'), $day['absolute_url']);
        static::assertCount(1, $day['links']);
        static::assertSame(1, $day['links'][0]['id']);
        static::assertSame('http://domain.tld/1', $day['links'][0]['url']);
        static::assertEquals($dates[0], $day['links'][0]['created']);

        $day = $assignedVariables['days'][$dates[1]->format('Ymd')];

        static::assertEquals($dates[1], $day['date']);
        static::assertSame($dates[1]->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame(format_date($dates[1], false), $day['date_human']);
        static::assertSame('http://shaarli/daily?day='. $dates[1]->format('Ymd'), $day['absolute_url']);
        static::assertCount(2, $day['links']);

        static::assertSame(2, $day['links'][0]['id']);
        static::assertSame('http://domain.tld/2', $day['links'][0]['url']);
        static::assertEquals($dates[1], $day['links'][0]['created']);
        static::assertSame(3, $day['links'][1]['id']);
        static::assertSame('http://domain.tld/3', $day['links'][1]['url']);
        static::assertEquals($dates[1], $day['links'][1]['created']);
    }

    /**
     * Daily RSS - trigger cache rendering
     */
    public function testValidRssControllerInvokeTriggerCache(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->pageCacheManager->method('getCachePage')->willReturnCallback(function (): CachedPage {
            $cachedPage = $this->createMock(CachedPage::class);
            $cachedPage->method('cachedVersion')->willReturn('this is cache!');

            return $cachedPage;
        });

        $this->container->bookmarkService->expects(static::never())->method('search');

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('this is cache!', (string) $result->getBody());
    }

    /**
     * Daily RSS - No bookmark
     */
    public function testValidRssControllerInvokeNoBookmark(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->bookmarkService->expects(static::once())->method('search')->willReturn([]);

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('dailyrss', (string) $result->getBody());
        static::assertSame('Shaarli', $assignedVariables['title']);
        static::assertSame('http://shaarli', $assignedVariables['index_url']);
        static::assertSame('http://shaarli/daily-rss', $assignedVariables['page_url']);
        static::assertFalse($assignedVariables['hide_timestamps']);
        static::assertCount(0, $assignedVariables['days']);
    }

    protected static function generateContent(int $length): string
    {
        // bin2hex(random_bytes) generates string twice as long as given parameter
        $length = (int) ceil($length / 2);
        return bin2hex(random_bytes($length));
    }
}

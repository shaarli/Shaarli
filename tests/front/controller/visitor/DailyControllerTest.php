<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\SearchResult;
use Shaarli\Feed\CachedPage;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

class DailyControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var DailyController */
    protected $controller;

    public function setUp(): void
    {
        setlocale(LC_TIME, 'en_US.UTF-8');
        $this->createContainer();

        $this->controller = new DailyController($this->container);
        DailyController::$DAILY_RSS_NB_DAYS = 2;
    }

    public function testValidIndexControllerInvokeDefault(): void
    {
        $currentDay = new \DateTimeImmutable('2020-05-13');
        $previousDate = new \DateTime('2 days ago 00:00:00');
        $nextDate = new \DateTime('today 00:00:00');

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(
                ['day' => $currentDay->format('Ymd')]
            ))
        ));
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByDate')
            ->willReturnCallback(
                function ($from, $to, &$previous, &$next) use ($currentDay, $previousDate, $nextDate): array {
                    $previous = $previousDate;
                    $next = $nextDate;

                    return [
                        (new Bookmark())
                            ->setId(1)
                            ->setUrl('http://url.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                        (new Bookmark())
                            ->setId(2)
                            ->setUrl('http://url2.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                        (new Bookmark())
                            ->setId(3)
                            ->setUrl('http://url3.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                    ];
                }
            )
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_daily'])
            ->willReturnCallback(
                function (string $hook, array $data, array $param) use ($currentDay, $previousDate, $nextDate): array {
                    if ('render_daily' === $hook) {
                        static::assertArrayHasKey('linksToDisplay', $data);
                        static::assertCount(3, $data['linksToDisplay']);
                        static::assertSame(1, $data['linksToDisplay'][0]['id']);
                        static::assertSame($currentDay->getTimestamp(), $data['day']);
                        static::assertSame($previousDate->format('Ymd'), $data['previousday']);
                        static::assertSame($nextDate->format('Ymd'), $data['nextday']);

                        static::assertArrayHasKey('loggedin', $param);
                    }

                    return $data;
                }
            )
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Daily - ' . format_date($currentDay, false, true) . ' - Shaarli',
            $assignedVariables['pagetitle']
        );
        static::assertEquals($currentDay, $assignedVariables['dayDate']);
        static::assertEquals($currentDay->getTimestamp(), $assignedVariables['day']);
        static::assertSame($previousDate->format('Ymd'), $assignedVariables['previousday']);
        static::assertSame($nextDate->format('Ymd'), $assignedVariables['nextday']);
        static::assertSame('day', $assignedVariables['type']);
        static::assertSame('May 13, 2020', $assignedVariables['dayDesc']);
        static::assertSame('Daily', $assignedVariables['localizedType']);
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
        $currentDay = new \DateTimeImmutable('2020-05-13');

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(
                ['day' => $currentDay->format('Ymd')]
            ))
        ));
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByDate')
            ->willReturnCallback(function () use ($currentDay): array {
                return [
                    (new Bookmark())
                        ->setId(1)
                        ->setUrl('http://url.tld')
                        ->setTitle(static::generateString(50))
                        ->setDescription(static::generateString(500))
                    ,
                ];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->withConsecutive(['render_daily'])
            ->willReturnCallback(function (string $hook, array $data, array $param) use ($currentDay): array {
                if ('render_daily' === $hook) {
                    static::assertArrayHasKey('linksToDisplay', $data);
                    static::assertCount(1, $data['linksToDisplay']);
                    static::assertSame(1, $data['linksToDisplay'][0]['id']);
                    static::assertSame($currentDay->getTimestamp(), $data['day']);
                    static::assertEmpty($data['previousday']);
                    static::assertEmpty($data['nextday']);

                    static::assertArrayHasKey('loggedin', $param);
                }

                return $data;
            });

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Daily - ' . format_date($currentDay, false, true) . ' - Shaarli',
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
        $currentDay = new \DateTimeImmutable('2020-05-13');

        $request = new FakeRequest();
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByDate')
            ->willReturnCallback(function () use ($currentDay): array {
                return [
                    (new Bookmark())->setId(1)->setUrl('http://url.tld')->setTitle('title'),
                    (new Bookmark())
                        ->setId(2)
                        ->setUrl('http://url.tld')
                        ->setTitle(static::generateString(50))
                        ->setDescription(static::generateString(5000))
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
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
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
            return array_map(function (array $item): int {
                return $item['id'];
            }, $column);
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
        $request = new FakeRequest();
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        // Links dataset: 2 links with thumbnails
        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByDate')
            ->willReturnCallback(function (): array {
                return [];
            })
        ;

        // Make sure that PluginManager hook is triggered
        $this->container->get('pluginManager')
            ->expects(static::atLeastOnce())
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array $data, array $param): array {
                return $data;
            })
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertCount(0, $assignedVariables['linksToDisplay']);
        static::assertSame('Today - ' . (new \DateTime())->format('F j, Y'), $assignedVariables['dayDesc']);
        static::assertEquals((new \DateTime())->setTime(0, 0)->getTimestamp(), $assignedVariables['day']);
        static::assertEquals((new \DateTime())->setTime(0, 0), $assignedVariables['dayDate']);
    }

    /**
     * Daily RSS - default behaviour
     */
    public function testValidRssControllerInvokeDefault(): void
    {
        $dates = [
            new \DateTimeImmutable('2020-05-17'),
            new \DateTimeImmutable('2020-05-15'),
            new \DateTimeImmutable('2020-05-13'),
            new \DateTimeImmutable('+1 month'),
        ];

        $request = (new FakeRequest())->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')->expects(static::once())->method('search')->willReturn(
            SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setCreated($dates[0])->setUrl('http://domain.tld/1'),
                (new Bookmark())->setId(2)->setCreated($dates[1])->setUrl('http://domain.tld/2'),
                (new Bookmark())->setId(3)->setCreated($dates[1])->setUrl('http://domain.tld/3'),
                (new Bookmark())->setId(4)->setCreated($dates[2])->setUrl('http://domain.tld/4'),
                (new Bookmark())->setId(5)->setCreated($dates[3])->setUrl('http://domain.tld/5'),
            ])
        );

        $this->container->get('pageCacheManager')
            ->expects(static::once())
            ->method('getCachePage')
            ->willReturnCallback(function (): CachedPage {
                $cachedPage = $this->createMock(CachedPage::class);
                $cachedPage->expects(static::once())->method('cache')->with('dailyrss');

                return $cachedPage;
            });

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('dailyrss', (string) $result->getBody());
        static::assertSame('Shaarli', $assignedVariables['title']);
        static::assertSame('http://shaarli/subfolder/', $assignedVariables['index_url']);
        static::assertSame('http://shaarli/subfolder/daily-rss', $assignedVariables['page_url']);
        static::assertFalse($assignedVariables['hide_timestamps']);
        static::assertCount(3, $assignedVariables['days']);

        $day = $assignedVariables['days'][$dates[0]->format('Ymd')];
        $date = $dates[0]->setTime(23, 59, 59);

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame(format_date($date, false), $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?day=' . $dates[0]->format('Ymd'), $day['absolute_url']);
        static::assertCount(1, $day['links']);
        static::assertSame(1, $day['links'][0]['id']);
        static::assertSame('http://domain.tld/1', $day['links'][0]['url']);
        static::assertEquals($dates[0], $day['links'][0]['created']);

        $day = $assignedVariables['days'][$dates[1]->format('Ymd')];
        $date = $dates[1]->setTime(23, 59, 59);

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame(format_date($date, false), $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?day=' . $dates[1]->format('Ymd'), $day['absolute_url']);
        static::assertCount(2, $day['links']);

        static::assertSame(2, $day['links'][0]['id']);
        static::assertSame('http://domain.tld/2', $day['links'][0]['url']);
        static::assertEquals($dates[1], $day['links'][0]['created']);
        static::assertSame(3, $day['links'][1]['id']);
        static::assertSame('http://domain.tld/3', $day['links'][1]['url']);
        static::assertEquals($dates[1], $day['links'][1]['created']);

        $day = $assignedVariables['days'][$dates[2]->format('Ymd')];
        $date = $dates[2]->setTime(23, 59, 59);

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame(format_date($date, false), $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?day=' . $dates[2]->format('Ymd'), $day['absolute_url']);
        static::assertCount(1, $day['links']);
        static::assertSame(4, $day['links'][0]['id']);
        static::assertSame('http://domain.tld/4', $day['links'][0]['url']);
        static::assertEquals($dates[2], $day['links'][0]['created']);
    }

    /**
     * Daily RSS - trigger cache rendering
     */
    public function testValidRssControllerInvokeTriggerCache(): void
    {
        $request = (new FakeRequest())->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
        ]);
        $response = new SlimResponse();

        $this->container->get('pageCacheManager')->method('getCachePage')->willReturnCallback(function (): CachedPage {
            $cachedPage = $this->createMock(CachedPage::class);
            $cachedPage->method('cachedVersion')->willReturn('this is cache!');

            return $cachedPage;
        });

        $this->container->get('bookmarkService')->expects(static::never())->method('search');

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
        $request = (new FakeRequest())->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')
            ->expects(static::once())->method('search')
            ->willReturn(SearchResult::getSearchResult([]));

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('dailyrss', (string) $result->getBody());
        static::assertSame('Shaarli', $assignedVariables['title']);
        static::assertSame('http://shaarli/subfolder/', $assignedVariables['index_url']);
        static::assertSame('http://shaarli/subfolder/daily-rss', $assignedVariables['page_url']);
        static::assertFalse($assignedVariables['hide_timestamps']);
        static::assertCount(0, $assignedVariables['days']);
    }

    /**
     * Test simple display index with week parameter
     */
    public function testSimpleIndexWeekly(): void
    {
        $currentDay = new \DateTimeImmutable('2020-05-13');
        $expectedDay = new \DateTimeImmutable('2020-05-11');

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['week' => $currentDay->format('YW')]))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
        ]);
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByDate')
            ->willReturnCallback(
                function (): array {
                    return [
                        (new Bookmark())
                            ->setId(1)
                            ->setUrl('http://url.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                        (new Bookmark())
                            ->setId(2)
                            ->setUrl('http://url2.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                    ];
                }
            )
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Weekly - Week 20 (May 11, 2020) - Shaarli',
            $assignedVariables['pagetitle']
        );

        static::assertCount(2, $assignedVariables['linksToDisplay']);
        static::assertEquals($expectedDay->setTime(0, 0), $assignedVariables['dayDate']);
        static::assertSame($expectedDay->setTime(0, 0)->getTimestamp(), $assignedVariables['day']);
        static::assertSame('', $assignedVariables['previousday']);
        static::assertSame('', $assignedVariables['nextday']);
        static::assertSame('Week 20 (May 11, 2020)', $assignedVariables['dayDesc']);
        static::assertSame('week', $assignedVariables['type']);
        static::assertSame('Weekly', $assignedVariables['localizedType']);
    }

    /**
     * Test simple display index with month parameter
     */
    public function testSimpleIndexMonthly(): void
    {
        $currentDay = new \DateTimeImmutable('2020-05-13');
        $expectedDay = new \DateTimeImmutable('2020-05-01');

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['month' => $currentDay->format('Ym')]))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
        ]);
        $response = new SlimResponse();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $this->container->get('bookmarkService')
            ->expects(static::once())
            ->method('findByDate')
            ->willReturnCallback(
                function (): array {
                    return [
                        (new Bookmark())
                            ->setId(1)
                            ->setUrl('http://url.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                        (new Bookmark())
                            ->setId(2)
                            ->setUrl('http://url2.tld')
                            ->setTitle(static::generateString(50))
                            ->setDescription(static::generateString(500))
                        ,
                    ];
                }
            )
        ;

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('daily', (string) $result->getBody());
        static::assertSame(
            'Monthly - May, 2020 - Shaarli',
            $assignedVariables['pagetitle']
        );

        static::assertCount(2, $assignedVariables['linksToDisplay']);
        static::assertEquals($expectedDay->setTime(0, 0), $assignedVariables['dayDate']);
        static::assertSame($expectedDay->setTime(0, 0)->getTimestamp(), $assignedVariables['day']);
        static::assertSame('', $assignedVariables['previousday']);
        static::assertSame('', $assignedVariables['nextday']);
        static::assertSame('May, 2020', $assignedVariables['dayDesc']);
        static::assertSame('month', $assignedVariables['type']);
        static::assertSame('Monthly', $assignedVariables['localizedType']);
    }

    /**
     * Test simple display RSS with week parameter
     */
    public function testSimpleRssWeekly(): void
    {
        $dates = [
            new \DateTimeImmutable('2020-05-19'),
            new \DateTimeImmutable('2020-05-13'),
        ];
        $expectedDates = [
            new \DateTimeImmutable('2020-05-24 23:59:59'),
            new \DateTimeImmutable('2020-05-17 23:59:59'),
        ];

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['week' => '']))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
            'QUERY_STRING' => 'week',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')->expects(static::once())->method('search')->willReturn(
            SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setCreated($dates[0])->setUrl('http://domain.tld/1'),
                (new Bookmark())->setId(2)->setCreated($dates[1])->setUrl('http://domain.tld/2'),
                (new Bookmark())->setId(3)->setCreated($dates[1])->setUrl('http://domain.tld/3'),
            ])
        );

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('dailyrss', (string) $result->getBody());
        static::assertSame('Shaarli', $assignedVariables['title']);
        static::assertSame('http://shaarli/subfolder/', $assignedVariables['index_url']);
        static::assertSame('http://shaarli/subfolder/daily-rss?week', $assignedVariables['page_url']);
        static::assertFalse($assignedVariables['hide_timestamps']);
        static::assertCount(2, $assignedVariables['days']);

        $day = $assignedVariables['days'][$dates[0]->format('YW')];
        $date = $expectedDates[0];

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame('Week 21 (May 18, 2020)', $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?week=' . $dates[0]->format('YW'), $day['absolute_url']);
        static::assertCount(1, $day['links']);

        $day = $assignedVariables['days'][$dates[1]->format('YW')];
        $date = $expectedDates[1];

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame('Week 20 (May 11, 2020)', $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?week=' . $dates[1]->format('YW'), $day['absolute_url']);
        static::assertCount(2, $day['links']);
    }

    /**
     * Test simple display RSS with month parameter
     */
    public function testSimpleRssMonthly(): void
    {
        $dates = [
            new \DateTimeImmutable('2022-02-19'),
            new \DateTimeImmutable('2020-04-13'),
        ];
        $expectedDates = [
            new \DateTimeImmutable('2022-02-28 23:59:59'),
            new \DateTimeImmutable('2020-04-30 23:59:59'),
        ];

        $request = (new FakeRequest(
            'GET',
            (new Uri('', ''))->withQuery(http_build_query(['month' => '']))
        ))->withServerParams([
            'SERVER_PORT' => 80,
            'SERVER_NAME' => 'shaarli',
            'SCRIPT_NAME' => '/subfolder/index.php',
            'REQUEST_URI' => '/subfolder/daily-rss',
            'QUERY_STRING' => 'month',
        ]);
        $response = new SlimResponse();

        $this->container->get('bookmarkService')->expects(static::once())->method('search')->willReturn(
            SearchResult::getSearchResult([
                (new Bookmark())->setId(1)->setCreated($dates[0])->setUrl('http://domain.tld/1'),
                (new Bookmark())->setId(2)->setCreated($dates[1])->setUrl('http://domain.tld/2'),
                (new Bookmark())->setId(3)->setCreated($dates[1])->setUrl('http://domain.tld/3'),
            ])
        );

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->rss($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString('application/rss', $result->getHeader('Content-Type')[0]);
        static::assertSame('dailyrss', (string) $result->getBody());
        static::assertSame('Shaarli', $assignedVariables['title']);
        static::assertSame('http://shaarli/subfolder/', $assignedVariables['index_url']);
        static::assertSame('http://shaarli/subfolder/daily-rss?month', $assignedVariables['page_url']);
        static::assertFalse($assignedVariables['hide_timestamps']);
        static::assertCount(2, $assignedVariables['days']);

        $day = $assignedVariables['days'][$dates[0]->format('Ym')];
        $date = $expectedDates[0];

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame('February, 2022', $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?month=' . $dates[0]->format('Ym'), $day['absolute_url']);
        static::assertCount(1, $day['links']);

        $day = $assignedVariables['days'][$dates[1]->format('Ym')];
        $date = $expectedDates[1];

        static::assertEquals($date, $day['date']);
        static::assertSame($date->format(\DateTime::RSS), $day['date_rss']);
        static::assertSame('April, 2020', $day['date_human']);
        static::assertSame('http://shaarli/subfolder/daily?month=' . $dates[1]->format('Ym'), $day['absolute_url']);
        static::assertCount(2, $day['links']);
    }
}

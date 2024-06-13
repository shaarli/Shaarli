<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use DateTime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Helper\DailyPageHelper;
use Shaarli\Render\TemplatePage;

/**
 * Class DailyController
 *
 * Slim controller used to render the daily page.
 */
class DailyController extends ShaarliVisitorController
{
    public static $DAILY_RSS_NB_DAYS = 8;

    /**
     * Controller displaying all bookmarks published in a single day.
     * It take a `day` date query parameter (format YYYYMMDD).
     */
    public function index(Request $request, Response $response): Response
    {
        $type = DailyPageHelper::extractRequestedType($request);

        $format = DailyPageHelper::getFormatByType($type);
        $latestBookmark = $this->container->get('bookmarkService')->getLatest();
        $dateTime = DailyPageHelper::extractRequestedDateTime(
            $type,
            $request->getQueryParams()[$type] ?? null,
            $latestBookmark
        );
        $start = DailyPageHelper::getStartDateTimeByType($type, $dateTime);
        $end = DailyPageHelper::getEndDateTimeByType($type, $dateTime);
        $dailyDesc = DailyPageHelper::getDescriptionByType($type, $dateTime);

        $linksToDisplay = $this->container->get('bookmarkService')->findByDate(
            $start,
            $end,
            $previousDay,
            $nextDay
        );

        $formatter = $this->container->get('formatterFactory')->getFormatter();
        $formatter->addContextData('base_path', $this->container->get('basePath'));
        // We pre-format some fields for proper output.
        foreach ($linksToDisplay as $key => $bookmark) {
            $linksToDisplay[$key] = $formatter->format($bookmark);
            // This page is a bit specific, we need raw description to calculate the length
            $linksToDisplay[$key]['formatedDescription'] = $linksToDisplay[$key]['description'];
            $linksToDisplay[$key]['description'] = $bookmark->getDescription();
        }

        $data = [
            'linksToDisplay' => $linksToDisplay,
            'dayDate' => $start,
            'day' => $start->getTimestamp(),
            'previousday' => $previousDay ? $previousDay->format($format) : '',
            'nextday' => $nextDay ? $nextDay->format($format) : '',
            'dayDesc' => $dailyDesc,
            'type' => $type,
            'localizedType' => $this->translateType($type),
        ];

        // Hooks are called before column construction so that plugins don't have to deal with columns.
        $this->executePageHooks('render_daily', $data, TemplatePage::DAILY);

        $data['cols'] = $this->calculateColumns($data['linksToDisplay']);

        $this->assignAllView($data);

        $mainTitle = $this->container->get('conf')->get('general.title', 'Shaarli');
        $this->assignView(
            'pagetitle',
            $data['localizedType'] . ' - ' . $data['dayDesc'] . ' - ' . $mainTitle
        );

        return $this->respondWithTemplate($response, TemplatePage::DAILY);
    }

    /**
     * Daily RSS feed: 1 RSS entry per day giving all the bookmarks on that day.
     * Gives the last 7 days (which have bookmarks).
     * This RSS feed cannot be filtered and does not trigger plugins yet.
     */
    public function rss(Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        $type = DailyPageHelper::extractRequestedType($request);
        $cacheDuration = DailyPageHelper::getCacheDatePeriodByType($type);

        $pageUrl = page_url($request->getServerParams());
        $cache = $this->container->get('pageCacheManager')->getCachePage($pageUrl, $cacheDuration);

        $cached = $cache->cachedVersion();
        if (!empty($cached)) {
            return $this->respondWithBody($response, $cached);
        }

        $days = [];
        $format = DailyPageHelper::getFormatByType($type);
        $length = DailyPageHelper::getRssLengthByType($type);
        foreach ($this->container->get('bookmarkService')->search()->getBookmarks() as $bookmark) {
            $day = $bookmark->getCreated()->format($format);

            // Stop iterating after DAILY_RSS_NB_DAYS entries
            if (count($days) === $length && !isset($days[$day])) {
                break;
            }

            $days[$day][] = $bookmark;
        }

        // Build the RSS feed.
        $indexUrl = escape(index_url($request->getServerParams()));

        $formatter = $this->container->get('formatterFactory')->getFormatter();
        $formatter->addContextData('index_url', $indexUrl);

        $dataPerDay = [];

        /** @var Bookmark[] $bookmarks */
        foreach ($days as $day => $bookmarks) {
            $dayDateTime = DailyPageHelper::extractRequestedDateTime($type, (string) $day);
            $endDateTime = DailyPageHelper::getEndDateTimeByType($type, $dayDateTime);

            // We only want the RSS entry to be published when the period is over.
            if (new DateTime() < $endDateTime) {
                continue;
            }

            $dataPerDay[$day] = [
                'date' => $endDateTime,
                'date_rss' => $endDateTime->format(DateTime::RSS),
                'date_human' => DailyPageHelper::getDescriptionByType($type, $dayDateTime, false),
                'absolute_url' => $indexUrl . 'daily?' . $type . '=' . $day,
                'links' => [],
            ];

            foreach ($bookmarks as $key => $bookmark) {
                $dataPerDay[$day]['links'][$key] = $formatter->format($bookmark);

                // Make permalink URL absolute
                if ($bookmark->isNote()) {
                    $dataPerDay[$day]['links'][$key]['url'] = rtrim($indexUrl, '/') . $bookmark->getUrl();
                }
            }
        }

        $this->assignAllView([
            'title' => $this->container->get('conf')->get('general.title', 'Shaarli'),
            'index_url' => $indexUrl,
            'page_url' => $pageUrl,
            'hide_timestamps' => $this->container->get('conf')->get('privacy.hide_timestamps', false),
            'days' => $dataPerDay,
            'type' => $type,
            'localizedType' => $this->translateType($type),
        ]);

        $rssContent = $this->render(TemplatePage::DAILY_RSS);

        $cache->cache($rssContent);

        return $this->respondWithBody($response, $rssContent);
    }

    /**
     * We need to spread the articles on 3 columns.
     * did not want to use a JavaScript lib like http://masonry.desandro.com/
     * so I manually spread entries with a simple method: I roughly evaluate the
     * height of a div according to title and description length.
     */
    protected function calculateColumns(array $links): array
    {
        // Entries to display, for each column.
        $columns = [[], [], []];
        // Rough estimate of columns fill.
        $fill = [0, 0, 0];
        foreach ($links as $link) {
            // Roughly estimate length of entry (by counting characters)
            // Title: 30 chars = 1 line. 1 line is 30 pixels height.
            // Description: 836 characters gives roughly 342 pixel height.
            // This is not perfect, but it's usually OK.
            $length = strlen($link['title'] ?? '') + (342 * strlen($link['description'] ?? '')) / 836;
            if (! empty($link['thumbnail'])) {
                $length += 100; // 1 thumbnails roughly takes 100 pixels height.
            }
            // Then put in column which is the less filled:
            $smallest = min($fill); // find smallest value in array.
            $index = array_search($smallest, $fill); // find index of this smallest value.
            array_push($columns[$index], $link); // Put entry in this column.
            $fill[$index] += $length;
        }

        return $columns;
    }

    protected function translateType($type): string
    {
        return [
            t('day') => t('Daily'),
            t('week') => t('Weekly'),
            t('month') => t('Monthly'),
        ][t($type)] ?? t('Daily');
    }
}

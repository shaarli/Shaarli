<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class DailyController
 *
 * Slim controller used to render the daily page.
 *
 * @package Front\Controller
 */
class DailyController extends ShaarliController
{
    /**
     * Controller displaying all bookmarks published in a single day.
     * It take a `day` date query parameter (format YYYYMMDD).
     */
    public function index(Request $request, Response $response): Response
    {
        $day = $request->getQueryParam('day') ?? date('Ymd');

        $availableDates = $this->container->bookmarkService->days();
        $nbAvailableDates = count($availableDates);
        $index = array_search($day, $availableDates);

        if ($index === false) {
            // no bookmarks for day, but at least one day with bookmarks
            $day = $availableDates[$nbAvailableDates - 1] ?? $day;
            $previousDay = $availableDates[$nbAvailableDates - 2] ?? '';
        } else {
            $previousDay = $availableDates[$index - 1] ?? '';
            $nextDay = $availableDates[$index + 1] ?? '';
        }

        if ($day === date('Ymd')) {
            $this->assignView('dayDesc', t('Today'));
        } elseif ($day === date('Ymd', strtotime('-1 days'))) {
            $this->assignView('dayDesc', t('Yesterday'));
        }

        try {
            $linksToDisplay = $this->container->bookmarkService->filterDay($day);
        } catch (\Exception $exc) {
            $linksToDisplay = [];
        }

        $formatter = $this->container->formatterFactory->getFormatter();
        // We pre-format some fields for proper output.
        foreach ($linksToDisplay as $key => $bookmark) {
            $linksToDisplay[$key] = $formatter->format($bookmark);
            // This page is a bit specific, we need raw description to calculate the length
            $linksToDisplay[$key]['formatedDescription'] = $linksToDisplay[$key]['description'];
            $linksToDisplay[$key]['description'] = $bookmark->getDescription();
        }

        $dayDate = DateTime::createFromFormat(Bookmark::LINK_DATE_FORMAT, $day.'_000000');
        $data = [
            'linksToDisplay' => $linksToDisplay,
            'day' => $dayDate->getTimestamp(),
            'dayDate' => $dayDate,
            'previousday' => $previousDay ?? '',
            'nextday' => $nextDay ?? '',
        ];

        // Hooks are called before column construction so that plugins don't have to deal with columns.
        $this->executeHooks($data);

        $data['cols'] = $this->calculateColumns($data['linksToDisplay']);

        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $mainTitle = $this->container->conf->get('general.title', 'Shaarli');
        $this->assignView(
            'pagetitle',
            t('Daily') .' - '. format_date($dayDate, false) . ' - ' . $mainTitle
        );

        return $response->write($this->render('daily'));
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

    /**
     * @param mixed[] $data Variables passed to the template engine
     *
     * @return mixed[] Template data after active plugins render_picwall hook execution.
     */
    protected function executeHooks(array $data): array
    {
        $this->container->pluginManager->executeHooks(
            'render_daily',
            $data,
            ['loggedin' => $this->container->loginManager->isLoggedIn()]
        );

        return $data;
    }
}

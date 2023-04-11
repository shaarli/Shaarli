<?php

declare(strict_types=1);

namespace Shaarli\Helper;

use DatePeriod;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Bookmark\Bookmark;

class DailyPageHelper
{
    public const MONTH = 'month';
    public const WEEK = 'week';
    public const DAY = 'day';

    /**
     * Extracts the type of the daily to display from the HTTP request parameters
     *
     * @param Request $request HTTP request
     *
     * @return string|null month/week/day
     */
    public static function extractRequestedType(Request $request): string
    {
        if (!is_null($request->getQueryParams()[static::MONTH] ?? null)) {
            return static::MONTH;
        } elseif (!is_null($request->getQueryParams()[static::WEEK] ?? null)) {
            return static::WEEK;
        }

        return static::DAY;
    }

    /**
     * Extracts a DateTimeImmutable from provided HTTP request.
     * If no parameter is provided, we rely on the creation date of the latest provided created bookmark.
     * If the datastore is empty or no bookmark is provided, we use the current date.
     *
     * @param string        $type           month/week/day
     * @param string|null   $requestedDate  Input string extracted from the request
     * @param Bookmark|null $latestBookmark Latest bookmark found in the datastore (by date)
     *
     * @return DateTimeImmutable from input or latest bookmark.
     *
     * @throws Exception Type not supported.
     */
    public static function extractRequestedDateTime(
        string $type,
        ?string $requestedDate,
        Bookmark $latestBookmark = null
    ): DateTimeImmutable {
        $format = static::getFormatByType($type);
        if (empty($requestedDate)) {
            return $latestBookmark instanceof Bookmark
                ? new DateTimeImmutable($latestBookmark->getCreated()->format(\DateTime::ATOM))
                : new DateTimeImmutable()
            ;
        }

        // Don't use today's day of month (github issue #1844)
        if ($type === static::MONTH) {
            $format = '!' . $format;
        }

        // W is not supported by createFromFormat...
        if ($type === static::WEEK) {
            return (new DateTimeImmutable())
                ->setISODate((int) substr($requestedDate, 0, 4), (int) substr($requestedDate, 4, 2))
            ;
        }

        return DateTimeImmutable::createFromFormat($format, $requestedDate);
    }

    /**
     * Get the DateTime format used by provided type
     * Examples:
     *   - day: 20201016 (<year><month><day>)
     *   - week: 202041 (<year><week number>)
     *   - month: 202010 (<year><month>)
     *
     * @param string $type month/week/day
     *
     * @return string DateTime compatible format
     *
     * @see https://www.php.net/manual/en/datetime.format.php
     *
     * @throws Exception Type not supported.
     */
    public static function getFormatByType(string $type): string
    {
        switch ($type) {
            case static::MONTH:
                return 'Ym';
            case static::WEEK:
                return 'YW';
            case static::DAY:
                return 'Ymd';
            default:
                throw new Exception('Unsupported daily format type');
        }
    }

    /**
     * Get the first DateTime of the time period depending on given datetime and type.
     * Note: DateTimeImmutable is required because we rely heavily on DateTime->modify() syntax
     *       and we don't want to alter original datetime.
     *
     * @param string             $type      month/week/day
     * @param DateTimeImmutable $requested DateTime extracted from request input
     *                                      (should come from extractRequestedDateTime)
     *
     * @return \DateTimeInterface First DateTime of the time period
     *
     * @throws Exception Type not supported.
     */
    public static function getStartDateTimeByType(string $type, DateTimeImmutable $requested): \DateTimeInterface
    {
        switch ($type) {
            case static::MONTH:
                return $requested->modify('first day of this month midnight');
            case static::WEEK:
                return $requested->modify('Monday this week midnight');
            case static::DAY:
                return $requested->modify('Today midnight');
            default:
                throw new Exception('Unsupported daily format type');
        }
    }

    /**
     * Get the last DateTime of the time period depending on given datetime and type.
     * Note: DateTimeImmutable is required because we rely heavily on DateTime->modify() syntax
     *       and we don't want to alter original datetime.
     *
     * @param string             $type      month/week/day
     * @param DateTimeImmutable $requested DateTime extracted from request input
     *                                      (should come from extractRequestedDateTime)
     *
     * @return \DateTimeInterface Last DateTime of the time period
     *
     * @throws Exception Type not supported.
     */
    public static function getEndDateTimeByType(string $type, DateTimeImmutable $requested): \DateTimeInterface
    {
        switch ($type) {
            case static::MONTH:
                return $requested->modify('last day of this month 23:59:59');
            case static::WEEK:
                return $requested->modify('Sunday this week 23:59:59');
            case static::DAY:
                return $requested->modify('Today 23:59:59');
            default:
                throw new Exception('Unsupported daily format type');
        }
    }

    /**
     * Get localized description of the time period depending on given datetime and type.
     * Example: for a month period, it returns `October, 2020`.
     *
     * @param string             $type            month/week/day
     * @param \DateTimeImmutable $requested       DateTime extracted from request input
     *                                            (should come from extractRequestedDateTime)
     * @param bool               $includeRelative Include relative date description (today, yesterday, etc.)
     *
     * @return string Localized time period description
     *
     * @throws Exception Type not supported.
     */
    public static function getDescriptionByType(
        string $type,
        \DateTimeImmutable $requested,
        bool $includeRelative = true
    ): string {
        switch ($type) {
            case static::MONTH:
                return $requested->format('F') . ', ' . $requested->format('Y');
            case static::WEEK:
                $requested = $requested->modify('Monday this week');
                return t('Week') . ' ' . $requested->format('W') . ' (' . format_date($requested, false) . ')';
            case static::DAY:
                $out = '';
                if ($includeRelative && $requested->format('Ymd') === date('Ymd')) {
                    $out = t('Today') . ' - ';
                } elseif ($includeRelative && $requested->format('Ymd') === date('Ymd', strtotime('-1 days'))) {
                    $out = t('Yesterday') . ' - ';
                }
                return $out . format_date($requested, false);
            default:
                throw new Exception('Unsupported daily format type');
        }
    }

    /**
     * Get the number of items to display in the RSS feed depending on the given type.
     *
     * @param string $type month/week/day
     *
     * @return int number of elements
     *
     * @throws Exception Type not supported.
     */
    public static function getRssLengthByType(string $type): int
    {
        switch ($type) {
            case static::MONTH:
                return 12; // 1 year
            case static::WEEK:
                return 26; // ~6 months
            case static::DAY:
                return 30; // ~1 month
            default:
                throw new Exception('Unsupported daily format type');
        }
    }

    /**
     * Get the number of items to display in the RSS feed depending on the given type.
     *
     * @param string             $type      month/week/day
     * @param ?DateTimeImmutable $requested Currently only used for UT
     *
     * @return DatePeriod number of elements
     *
     * @throws Exception Type not supported.
     */
    public static function getCacheDatePeriodByType(string $type, DateTimeImmutable $requested = null): DatePeriod
    {
        $requested = $requested ?? new DateTimeImmutable();

        return new DatePeriod(
            static::getStartDateTimeByType($type, $requested),
            new \DateInterval('P1D'),
            static::getEndDateTimeByType($type, $requested)
        );
    }
}

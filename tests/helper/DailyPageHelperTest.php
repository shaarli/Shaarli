<?php

declare(strict_types=1);

namespace Shaarli\Helper;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Shaarli\Bookmark\Bookmark;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;

class DailyPageHelperTest extends TestCase
{
    public function setUp(): void
    {
        setlocale(LC_TIME, 'en_US.UTF-8');
    }


    /**
     * @dataProvider getRequestedTypes
     */
    public function testExtractRequestedType(array $queryParams, string $expectedType): void
    {
        $request = (new FakeRequest())->withQueryParams($queryParams);

        $type = DailyPageHelper::extractRequestedType($request);

        static::assertSame($type, $expectedType);
    }

    /**
     * @dataProvider getRequestedDateTimes
     */
    public function testExtractRequestedDateTime(
        string $type,
        string $input,
        ?Bookmark $bookmark,
        DateTimeInterface $expectedDateTime,
        string $compareFormat = 'Ymd'
    ): void {
        $dateTime = DailyPageHelper::extractRequestedDateTime($type, $input, $bookmark);

        static::assertSame($dateTime->format($compareFormat), $expectedDateTime->format($compareFormat));
    }

    public function testExtractRequestedDateTimeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::extractRequestedDateTime('nope', null, null);
    }

    /**
     * @dataProvider getFormatsByType
     */
    public function testGetFormatByType(string $type, string $expectedFormat): void
    {
        $format = DailyPageHelper::getFormatByType($type);

        static::assertSame($expectedFormat, $format);
    }

    public function testGetFormatByTypeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::getFormatByType('nope');
    }

    /**
     * @dataProvider getStartDatesByType
     */
    public function testGetStartDatesByType(
        string $type,
        DateTimeImmutable $dateTime,
        DateTimeInterface $expectedDateTime
    ): void {
        $startDateTime = DailyPageHelper::getStartDateTimeByType($type, $dateTime);

        static::assertEquals($expectedDateTime, $startDateTime);
    }

    public function testGetStartDatesByTypeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::getStartDateTimeByType('nope', new DateTimeImmutable());
    }

    /**
     * @dataProvider getEndDatesByType
     */
    public function testGetEndDatesByType(
        string $type,
        DateTimeImmutable $dateTime,
        DateTimeInterface $expectedDateTime
    ): void {
        $endDateTime = DailyPageHelper::getEndDateTimeByType($type, $dateTime);

        static::assertEquals($expectedDateTime, $endDateTime);
    }

    public function testGetEndDatesByTypeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::getEndDateTimeByType('nope', new DateTimeImmutable());
    }

    /**
     * @dataProvider getDescriptionsByType
     */
    public function testGeDescriptionsByType(
        string $type,
        DateTimeImmutable $dateTime,
        string $expectedDescription
    ): void {
        $description = DailyPageHelper::getDescriptionByType($type, $dateTime);

        static::assertEquals($expectedDescription, $description);
    }

    /**
     * @dataProvider getDescriptionsByTypeNotIncludeRelative
     */
    public function testGeDescriptionsByTypeNotIncludeRelative(
        string $type,
        \DateTimeImmutable $dateTime,
        string $expectedDescription
    ): void {
        $description = DailyPageHelper::getDescriptionByType($type, $dateTime, false);

        static::assertEquals($expectedDescription, $description);
    }

    public function getDescriptionByTypeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::getDescriptionByType('nope', new DateTimeImmutable());
    }

    /**
     * @dataProvider getRssLengthsByType
     */
    public function testGeRssLengthsByType(string $type): void
    {
        $length = DailyPageHelper::getRssLengthByType($type);

        static::assertIsInt($length);
    }

    public function testGeRssLengthsByTypeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::getRssLengthByType('nope');
    }

    /**
     * @dataProvider getCacheDatePeriodByType
     */
    public function testGetCacheDatePeriodByType(
        string $type,
        DateTimeImmutable $requested,
        DateTimeInterface $start,
        DateTimeInterface $end
    ): void {
        $period = DailyPageHelper::getCacheDatePeriodByType($type, $requested);

        static::assertEquals($start, $period->getStartDate());
        static::assertEquals($end, $period->getEndDate());
    }

    public function testGetCacheDatePeriodByTypeExceptionUnknownType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported daily format type');

        DailyPageHelper::getCacheDatePeriodByType('nope');
    }

    /**
     * Data provider for testExtractRequestedType() test method.
     */
    public function getRequestedTypes(): array
    {
        return [
            [['month' => null], DailyPageHelper::DAY],
            [['month' => ''], DailyPageHelper::MONTH],
            [['month' => 'content'], DailyPageHelper::MONTH],
            [['week' => null], DailyPageHelper::DAY],
            [['week' => ''], DailyPageHelper::WEEK],
            [['week' => 'content'], DailyPageHelper::WEEK],
            [['day' => null], DailyPageHelper::DAY],
            [['day' => ''], DailyPageHelper::DAY],
            [['day' => 'content'], DailyPageHelper::DAY],
        ];
    }

    /**
     * Data provider for testExtractRequestedDateTime() test method.
     */
    public function getRequestedDateTimes(): array
    {
        return [
            [DailyPageHelper::DAY, '20201013', null, new DateTime('2020-10-13')],
            [
                DailyPageHelper::DAY,
                '',
                (new Bookmark())->setCreated($date = new DateTime('2020-10-13 12:05:31')),
                $date,
            ],
            [DailyPageHelper::DAY, '', null, new DateTime()],
            [DailyPageHelper::WEEK, '202030', null, new DateTime('2020-07-20')],
            [
                DailyPageHelper::WEEK,
                '',
                (new Bookmark())->setCreated($date = new DateTime('2020-10-13 12:05:31')),
                new DateTime('2020-10-13'),
            ],
            [DailyPageHelper::WEEK, '', null, new DateTime(), 'Ym'],
            [DailyPageHelper::MONTH, '202008', null, new DateTime('2020-08-01'), 'Ym'],
            [
                DailyPageHelper::MONTH,
                '',
                (new Bookmark())->setCreated($date = new DateTime('2020-10-13 12:05:31')),
                new DateTime('2020-10-13'),
                'Ym'
            ],
            [DailyPageHelper::MONTH, '', null, new DateTime(), 'Ym'],
        ];
    }

    /**
     * Data provider for testGetFormatByType() test method.
     */
    public function getFormatsByType(): array
    {
        return [
            [DailyPageHelper::DAY, 'Ymd'],
            [DailyPageHelper::WEEK, 'YW'],
            [DailyPageHelper::MONTH, 'Ym'],
        ];
    }

    /**
     * Data provider for testGetStartDatesByType() test method.
     */
    public function getStartDatesByType(): array
    {
        return [
            [DailyPageHelper::DAY, new DateTimeImmutable('2020-10-09 04:05:06'), new DateTime('2020-10-09 00:00:00')],
            [DailyPageHelper::WEEK, new DateTimeImmutable('2020-10-09 04:05:06'), new DateTime('2020-10-05 00:00:00')],
            [DailyPageHelper::MONTH, new DateTimeImmutable('2022-03-30 04:05:06'), new DateTime('2022-03-01 00:00:00')],
        ];
    }

    /**
     * Data provider for testGetEndDatesByType() test method.
     */
    public function getEndDatesByType(): array
    {
        return [
            [DailyPageHelper::DAY, new DateTimeImmutable('2020-10-09 04:05:06'), new DateTime('2020-10-09 23:59:59')],
            [DailyPageHelper::WEEK, new DateTimeImmutable('2020-10-09 04:05:06'), new DateTime('2020-10-11 23:59:59')],
            [DailyPageHelper::MONTH, new DateTimeImmutable('2022-02-28 04:05:06'), new DateTime('2022-02-28 23:59:59')],
            [DailyPageHelper::MONTH, new DateTimeImmutable('2022-03-30 04:05:06'), new DateTime('2022-03-31 23:59:59')],
        ];
    }

    /**
     * Data provider for testGetDescriptionsByType() test method.
     */
    public function getDescriptionsByType(): array
    {
        return [
            [DailyPageHelper::DAY, $date = new DateTimeImmutable(), 'Today - ' . $date->format('F j, Y')],
            [DailyPageHelper::DAY, $date = new DateTimeImmutable('-1 day'), 'Yesterday - ' . $date->format('F j, Y')],
            [DailyPageHelper::DAY, new DateTimeImmutable('2020-10-09 04:05:06'), 'October 9, 2020'],
            [DailyPageHelper::WEEK, new DateTimeImmutable('2020-10-09 04:05:06'), 'Week 41 (October 5, 2020)'],
            [DailyPageHelper::MONTH, new DateTimeImmutable('2020-10-09 04:05:06'), 'October, 2020'],
        ];
    }

    /**
     * Data provider for testGeDescriptionsByTypeNotIncludeRelative() test method.
     */
    public function getDescriptionsByTypeNotIncludeRelative(): array
    {
        return [
            [DailyPageHelper::DAY, $date = new \DateTimeImmutable(), $date->format('F j, Y')],
            [DailyPageHelper::DAY, $date = new \DateTimeImmutable('-1 day'), $date->format('F j, Y')],
            [DailyPageHelper::DAY, new \DateTimeImmutable('2020-10-09 04:05:06'), 'October 9, 2020'],
            [DailyPageHelper::WEEK, new \DateTimeImmutable('2020-10-09 04:05:06'), 'Week 41 (October 5, 2020)'],
            [DailyPageHelper::MONTH, new \DateTimeImmutable('2020-10-09 04:05:06'), 'October, 2020'],
        ];
    }

    /**
     * Data provider for testGetRssLengthsByType() test method.
     */
    public function getRssLengthsByType(): array
    {
        return [
            [DailyPageHelper::DAY],
            [DailyPageHelper::WEEK],
            [DailyPageHelper::MONTH],
        ];
    }

    /**
     * Data provider for testGetCacheDatePeriodByType() test method.
     */
    public function getCacheDatePeriodByType(): array
    {
        return [
            [
                DailyPageHelper::DAY,
                new DateTimeImmutable('2020-10-09 04:05:06'),
                new DateTime('2020-10-09 00:00:00'),
                new DateTime('2020-10-09 23:59:59'),
            ],
            [
                DailyPageHelper::WEEK,
                new DateTimeImmutable('2020-10-09 04:05:06'),
                new DateTime('2020-10-05 00:00:00'),
                new DateTime('2020-10-11 23:59:59'),
            ],
            [
                DailyPageHelper::MONTH,
                new DateTimeImmutable('2020-10-09 04:05:06'),
                new DateTime('2020-10-01 00:00:00'),
                new DateTime('2020-10-31 23:59:59'),
            ],
        ];
    }
}

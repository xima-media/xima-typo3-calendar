<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Utility\CalendarFeedRequestUtility;

final class CalendarFeedRequestUtilityTest extends TestCase
{
    #[Test]
    public function parsesRequestedDateRangeToTimestamps(): void
    {
        $queryParams = [
            'start' => '2026-01-01T00:00:00+00:00',
            'end' => '2026-01-02T00:00:00+00:00',
        ];

        self::assertSame(1767225600, CalendarFeedRequestUtility::getStartTimestamp($queryParams));
        self::assertSame(1767312000, CalendarFeedRequestUtility::getEndTimestamp($queryParams));
    }

    #[Test]
    public function fallsBackWhenDateParametersAreInvalid(): void
    {
        $queryParams = [
            'start' => 'not a date',
            'end' => ['not', 'scalar'],
        ];

        self::assertSame(0, CalendarFeedRequestUtility::getStartTimestamp($queryParams));
        self::assertSame(253402300799, CalendarFeedRequestUtility::getEndTimestamp($queryParams));
    }

    #[Test]
    public function castsCalendarFilterValuesToIntegers(): void
    {
        self::assertSame([1, 2], CalendarFeedRequestUtility::getCalendarUids(['calendars' => ['1', '2']]));
    }
}

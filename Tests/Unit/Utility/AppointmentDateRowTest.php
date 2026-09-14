<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Utility\AppointmentDateUtility;

final class AppointmentDateRowTest extends TestCase
{
    private string $originalTimeZone;

    protected function setUp(): void
    {
        $this->originalTimeZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimeZone);
    }

    #[Test]
    public function returnsNullWithoutAStartDate(): void
    {
        self::assertNull(AppointmentDateUtility::getStartFromRow(['start_date' => 0]));
        self::assertNull(AppointmentDateUtility::getEndFromRow(['start_date' => 0, 'end_date' => 100]));
    }

    #[Test]
    public function fallsBackToTheDefaultDurationWithoutAnEndDate(): void
    {
        $start = $this->timestamp('2026-09-14 10:00');

        $end = AppointmentDateUtility::getEndFromRow(['start_date' => $start, 'end_date' => 0]);

        self::assertSame('2026-09-14 10:30', $end?->format('Y-m-d H:i'));
    }

    #[Test]
    public function anAllDayAppointmentEndsAtTheNextLocalMidnight(): void
    {
        $start = $this->timestamp('2026-09-14 00:00');

        $end = AppointmentDateUtility::getEndFromRow([
            'start_date' => $start,
            'end_date' => 0,
            'all_day' => 1,
        ]);

        self::assertSame('2026-09-15 00:00', $end?->format('Y-m-d H:i'));
    }

    #[Test]
    public function anAllDayAppointmentSpanningDaysEndsAfterItsLastDay(): void
    {
        $end = AppointmentDateUtility::getEndFromRow([
            'start_date' => $this->timestamp('2026-09-14 00:00'),
            'end_date' => $this->timestamp('2026-09-16 00:00'),
            'all_day' => 1,
        ]);

        self::assertSame('2026-09-17 00:00', $end?->format('Y-m-d H:i'));
    }

    #[Test]
    public function anAllDayAppointmentLastsTwentyFiveHoursOnADstFallbackDay(): void
    {
        $start = $this->timestamp('2026-10-25 00:00');

        $end = AppointmentDateUtility::getEndFromRow([
            'start_date' => $start,
            'end_date' => 0,
            'all_day' => 1,
        ]);

        self::assertSame('2026-10-26 00:00', $end?->format('Y-m-d H:i'));
        self::assertSame(90000, $end->getTimestamp() - $start);
    }

    #[Test]
    public function theRowVariantsAgreeWithTheModelVariants(): void
    {
        $row = [
            'start_date' => $this->timestamp('2026-09-14 18:00'),
            'end_date' => $this->timestamp('2026-09-14 20:30'),
        ];

        self::assertSame('2026-09-14 20:30', AppointmentDateUtility::getEndFromRow($row)?->format('Y-m-d H:i'));
    }

    #[Test]
    public function theRepresentativeRowIsTheNextOneNotYetStarted(): void
    {
        $now = new \DateTimeImmutable('2026-09-14 12:00');
        $rows = [
            ['uid' => 1, 'start_date' => $this->timestamp('2026-09-10 10:00')],
            ['uid' => 2, 'start_date' => $this->timestamp('2026-09-20 10:00')],
            ['uid' => 3, 'start_date' => $this->timestamp('2026-09-16 10:00')],
        ];

        self::assertSame(3, AppointmentDateUtility::pickRepresentativeRow($rows, $now)['uid']);
    }

    #[Test]
    public function theRepresentativeRowIsTheEarliestWhenEverythingIsPast(): void
    {
        $now = new \DateTimeImmutable('2026-09-14 12:00');
        $rows = [
            ['uid' => 1, 'start_date' => $this->timestamp('2026-09-10 10:00')],
            ['uid' => 2, 'start_date' => $this->timestamp('2026-09-02 10:00')],
        ];

        self::assertSame(2, AppointmentDateUtility::pickRepresentativeRow($rows, $now)['uid']);
    }

    #[Test]
    public function rowsWithoutAStartDateAreIgnored(): void
    {
        $now = new \DateTimeImmutable('2026-09-14 12:00');

        self::assertNull(AppointmentDateUtility::pickRepresentativeRow([['uid' => 1, 'start_date' => 0]], $now));
        self::assertNull(AppointmentDateUtility::pickRepresentativeRow([], $now));
    }

    private function timestamp(string $localTime): int
    {
        return (new \DateTimeImmutable($localTime))->getTimestamp();
    }
}

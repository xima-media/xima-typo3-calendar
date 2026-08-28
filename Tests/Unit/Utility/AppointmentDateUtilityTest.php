<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Utility\AppointmentDateUtility;

final class AppointmentDateUtilityTest extends TestCase
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
    public function returnsNullWhenNoStartIsSet(): void
    {
        $appointment = new EventAppointment();

        self::assertNull(AppointmentDateUtility::getStart($appointment));
        self::assertNull(AppointmentDateUtility::getEnd($appointment));
    }

    #[Test]
    public function keepsAnExplicitEnd(): void
    {
        $appointment = $this->appointment('2025-07-01 09:00:00', '2025-07-01 17:00:00');

        self::assertSame('2025-07-01 17:00:00', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function fallsBackToTheDefaultDurationWhenNoEndIsSet(): void
    {
        $appointment = $this->appointment('2025-07-01 09:00:00');

        self::assertSame('2025-07-01 09:30:00', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d H:i:s'));
        self::assertSame(30, AppointmentDateUtility::DEFAULT_DURATION_MINUTES);
    }

    /**
     * RFC 5545 defines DTEND as non-inclusive, so a single-day appointment ends the day after.
     */
    #[Test]
    public function endsASingleAllDayAppointmentOnTheFollowingDay(): void
    {
        $appointment = $this->appointment('2025-07-01 00:00:00', allDay: true);

        self::assertSame('2025-07-02 00:00:00', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function endsAMultiDayAllDayAppointmentAfterItsLastDay(): void
    {
        $appointment = $this->appointment('2025-07-01 00:00:00', '2025-07-03 00:00:00', allDay: true);

        self::assertSame('2025-07-04 00:00:00', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d H:i:s'));
    }

    /**
     * All-day appointments are dates, so a time entered alongside them is irrelevant.
     */
    #[Test]
    public function ignoresTheTimeOfDayOfAllDayAppointments(): void
    {
        $appointment = $this->appointment('2025-07-01 14:30:00', '2025-07-01 22:15:00', allDay: true);

        self::assertSame('2025-07-02 00:00:00', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d H:i:s'));
    }

    /**
     * A UTC reading of a German midnight lands on the previous day.
     */
    #[Test]
    public function readsAllDayBoundariesInTheInstallationTimezone(): void
    {
        $appointment = new EventAppointment();
        $appointment->setStartDate(new \DateTime('2025-06-30 22:00:00', new \DateTimeZone('UTC')));
        $appointment->setAllDay(true);

        self::assertSame('2025-07-02 00:00:00', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function crossesTheDaylightSavingBoundaryWithoutLosingADay(): void
    {
        $appointment = $this->appointment('2025-03-29 00:00:00', '2025-03-30 00:00:00', allDay: true);

        self::assertSame('2025-03-31', AppointmentDateUtility::getEnd($appointment)?->format('Y-m-d'));
    }

    private function appointment(string $start, ?string $end = null, bool $allDay = false): EventAppointment
    {
        $timeZone = new \DateTimeZone('Europe/Berlin');

        $appointment = new EventAppointment();
        $appointment->setStartDate(new \DateTime($start, $timeZone));
        if ($end !== null) {
            $appointment->setEndDate(new \DateTime($end, $timeZone));
        }
        $appointment->setAllDay($allDay);

        return $appointment;
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Utility;

use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;

/**
 * Resolves the interval of an appointment for every consumer that needs a complete one.
 *
 * `end_date` is optional in the TCA, so a fallback duration is unavoidable. Keeping it here
 * — together with the all-day rule — means the calendar feed, the ICS export and the
 * provider deep links cannot answer the same question differently.
 */
final class AppointmentDateUtility
{
    /**
     * Assumed length of an appointment that carries no end date.
     */
    public const DEFAULT_DURATION_MINUTES = 30;

    public static function getStart(EventAppointment $appointment): ?\DateTimeImmutable
    {
        $start = $appointment->getStartDate();

        return $start === null ? null : \DateTimeImmutable::createFromInterface($start);
    }

    /**
     * The end of the interval, exclusive for all-day appointments.
     *
     * RFC 5545 defines DTEND as non-inclusive, so a single-day appointment ends on the
     * following day. Google and Outlook read their all-day range the same way, which is why
     * this is not an ICS-only concern.
     */
    public static function getEnd(EventAppointment $appointment): ?\DateTimeImmutable
    {
        $start = self::getStart($appointment);
        if ($start === null) {
            return null;
        }

        $end = $appointment->getEndDate();
        $end = $end === null ? null : \DateTimeImmutable::createFromInterface($end);

        if ($appointment->isAllDay()) {
            return self::toLocalMidnight($end ?? $start)->modify('+1 day');
        }

        return $end ?? $start->modify('+' . self::DEFAULT_DURATION_MINUTES . ' minutes');
    }

    /**
     * Start of the calendar day the given moment falls into, in the installation timezone.
     *
     * All-day values carry a date and no time, so they have to be read in the timezone the
     * editor entered them in; a UTC reading shifts them by a day for part of the year.
     */
    public static function toLocalMidnight(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->setTime(0, 0);
    }
}

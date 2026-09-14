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
     * The start of an appointment read straight from its database row.
     *
     * The row variants exist for consumers that never hydrate a model, such as the ke_search
     * indexer, and answer the same question as the model variants above.
     *
     * @param array<string, mixed> $row
     */
    public static function getStartFromRow(array $row): ?\DateTimeImmutable
    {
        $start = (int)($row['start_date'] ?? 0);
        if ($start <= 0) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($start);
    }

    /**
     * @param array<string, mixed> $row
     * @see self::getEnd()
     */
    public static function getEndFromRow(array $row): ?\DateTimeImmutable
    {
        $start = self::getStartFromRow($row);
        if ($start === null) {
            return null;
        }

        $endTimestamp = (int)($row['end_date'] ?? 0);
        $end = $endTimestamp > 0 ? (new \DateTimeImmutable())->setTimestamp($endTimestamp) : null;

        if ((bool)($row['all_day'] ?? false)) {
            return self::toLocalMidnight($end ?? $start)->modify('+1 day');
        }

        return $end ?? $start->modify('+' . self::DEFAULT_DURATION_MINUTES . ' minutes');
    }

    /**
     * The row of the appointment representing a series: the next one that has not started yet,
     * or the earliest when all of them lie in the past. Mirrors Event::getNextAppointment(),
     * which answers the same question for hydrated models.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    public static function pickRepresentativeRow(array $rows, \DateTimeImmutable $now): ?array
    {
        $next = null;
        $nextStart = null;
        $earliest = null;
        $earliestStart = null;

        foreach ($rows as $row) {
            $start = self::getStartFromRow($row);
            if ($start === null) {
                continue;
            }

            if ($earliestStart === null || $start < $earliestStart) {
                $earliest = $row;
                $earliestStart = $start;
            }

            if ($start >= $now && ($nextStart === null || $start < $nextStart)) {
                $next = $row;
                $nextStart = $start;
            }
        }

        return $next ?? $earliest;
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

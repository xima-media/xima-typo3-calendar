<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Serializer;

use Xima\XimaTypo3Calendar\Domain\Model\Dto\IcsEvent;

/**
 * Renders normalized events as an RFC 5545 iCalendar stream.
 *
 * Pure formatter: it neither reads the database nor decides what an appointment means. The
 * three rules that hand-written ICS usually gets wrong all live here — octet-based line
 * folding, TEXT escaping, and the distinction between TEXT and URI values.
 */
final class IcsSerializer
{
    public const PRODID = '-//XIMA Media GmbH//xima_typo3_calendar//EN';

    /**
     * RFC 5545 section 3.1: content lines are folded at 75 octets, excluding the line break.
     */
    private const LINE_LENGTH = 75;

    private const CRLF = "\r\n";

    /**
     * @param IcsEvent[] $events
     */
    public static function serialize(array $events, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($events as $event) {
            array_push($lines, ...self::renderEvent($event, $now));
        }

        $lines[] = 'END:VCALENDAR';

        return implode(self::CRLF, array_map(self::fold(...), $lines)) . self::CRLF;
    }

    /**
     * @return string[]
     */
    private static function renderEvent(IcsEvent $event, \DateTimeImmutable $now): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . self::escapeText($event->uid),
            'DTSTAMP:' . self::formatUtc($now),
        ];

        if ($event->allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:' . self::formatDate($event->start);
            $lines[] = 'DTEND;VALUE=DATE:' . self::formatDate($event->end);
        } else {
            $lines[] = 'DTSTART:' . self::formatUtc($event->start);
            $lines[] = 'DTEND:' . self::formatUtc($event->end);
        }

        $lines[] = 'SUMMARY:' . self::escapeText($event->summary);

        if ($event->description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escapeText($event->description);
        }

        if ($event->location !== '') {
            $lines[] = 'LOCATION:' . self::escapeText($event->location);
        }

        // URL carries a URI value, not TEXT — escaping it would put backslashes in the link.
        if ($event->url !== null && $event->url !== '') {
            $lines[] = 'URL:' . $event->url;
        }

        if ($event->categories !== []) {
            $lines[] = 'CATEGORIES:' . implode(',', array_map(self::escapeText(...), $event->categories));
        }

        if ($event->organizerEmail !== null && $event->organizerEmail !== '') {
            $lines[] = self::renderOrganizer($event->organizerEmail, $event->organizerName);
        }

        $lines[] = 'STATUS:' . ($event->cancelled ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'SEQUENCE:' . $event->sequence;

        if ($event->lastModified !== null) {
            $lines[] = 'LAST-MODIFIED:' . self::formatUtc($event->lastModified);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private static function renderOrganizer(string $email, ?string $name): string
    {
        // Quoted parameter values cannot contain a double quote and have no escape for it.
        $commonName = trim(str_replace(['"', "\r", "\n"], '', (string)$name));

        return $commonName === ''
            ? 'ORGANIZER:mailto:' . $email
            : 'ORGANIZER;CN="' . $commonName . '":mailto:' . $email;
    }

    /**
     * Escapes a TEXT value per RFC 5545 section 3.3.11.
     *
     * The backslash is replaced first so the backslashes introduced afterwards are not
     * escaped again.
     */
    private static function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value
        );
    }

    private static function formatUtc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * A DATE value has no timezone, so it is read in the installation's.
     */
    private static function formatDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Ymd');
    }

    /**
     * Folds one content line, keeping multi-byte characters intact.
     *
     * The limit counts octets, and a continuation line spends one of them on its leading
     * space.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_LENGTH) {
            return $line;
        }

        $folded = '';
        $current = '';
        $limit = self::LINE_LENGTH;

        foreach (mb_str_split($line, 1, 'UTF-8') as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $folded .= $current . self::CRLF . ' ';
                $current = '';
                $limit = self::LINE_LENGTH - 1;
            }

            $current .= $character;
        }

        return $folded . $current;
    }
}

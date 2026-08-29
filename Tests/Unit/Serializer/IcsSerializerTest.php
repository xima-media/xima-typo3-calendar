<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Serializer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\IcsEvent;
use Xima\XimaTypo3Calendar\Serializer\IcsSerializer;

/**
 * Covers the RFC 5545 formatting rules.
 *
 * Everything here is byte-level on purpose: a calendar client rejects or silently mangles a
 * stream whose folding, escaping or value types are off, and none of that shows up in a
 * casual read of the output.
 */
final class IcsSerializerTest extends TestCase
{
    private const NOW = '2025-06-01T12:00:00+00:00';

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
    public function wrapsEventsInACalendarObject(): void
    {
        $output = $this->serialize($this->icsEvent());

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $output);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $output);
        self::assertStringContainsString("VERSION:2.0\r\n", $output);
        self::assertStringContainsString('PRODID:' . IcsSerializer::PRODID . "\r\n", $output);
        self::assertStringContainsString("METHOD:PUBLISH\r\n", $output);
    }

    #[Test]
    public function usesCrlfForEveryLineBreak(): void
    {
        $output = $this->serialize($this->icsEvent());

        self::assertSame(0, preg_match('/(?<!\r)\n/', $output), 'Found a LF that is not preceded by CR');
    }

    #[Test]
    public function writesTimedEventsInUtc(): void
    {
        $output = $this->serialize($this->icsEvent(
            start: new \DateTimeImmutable('2025-07-01 09:00:00', new \DateTimeZone('Europe/Berlin')),
            end: new \DateTimeImmutable('2025-07-01 10:30:00', new \DateTimeZone('Europe/Berlin')),
        ));

        self::assertStringContainsString("DTSTART:20250701T070000Z\r\n", $output);
        self::assertStringContainsString("DTEND:20250701T083000Z\r\n", $output);
    }

    /**
     * A DATE value carries no timezone, so it has to be read in the installation's — a UTC
     * reading moves a German midnight to the previous day.
     */
    #[Test]
    public function writesAllDayEventsAsDateValuesInTheInstallationTimezone(): void
    {
        $output = $this->serialize($this->icsEvent(
            start: new \DateTimeImmutable('2025-07-01 00:00:00', new \DateTimeZone('Europe/Berlin')),
            end: new \DateTimeImmutable('2025-07-02 00:00:00', new \DateTimeZone('Europe/Berlin')),
            allDay: true,
        ));

        self::assertStringContainsString("DTSTART;VALUE=DATE:20250701\r\n", $output);
        self::assertStringContainsString("DTEND;VALUE=DATE:20250702\r\n", $output);
    }

    #[Test]
    public function escapesTheSpecialCharactersOfTextValues(): void
    {
        $output = $this->serialize($this->icsEvent(
            summary: 'A, B; C \\ D',
            description: "First line\r\nSecond line",
        ));

        self::assertStringContainsString('SUMMARY:A\, B\; C \\\\ D' . "\r\n", $output);
        self::assertStringContainsString('DESCRIPTION:First line\nSecond line' . "\r\n", $output);
    }

    /**
     * URL is a URI value, so the TEXT escaping must not touch it.
     */
    #[Test]
    public function leavesUrlValuesUnescaped(): void
    {
        $url = 'https://example.org/events?a=1,2&b=3';

        $output = $this->serialize($this->icsEvent(url: $url));

        self::assertStringContainsString('URL:' . $url . "\r\n", $output);
    }

    #[Test]
    public function foldsLinesLongerThanSeventyFiveOctets(): void
    {
        $output = $this->serialize($this->icsEvent(description: str_repeat('a', 200)));

        foreach (explode("\r\n", $output) as $line) {
            self::assertLessThanOrEqual(75, strlen($line), 'Unfolded line: ' . $line);
        }

        self::assertStringContainsString("\r\n ", $output, 'Expected at least one continuation line');
    }

    /**
     * The 75 octets are bytes, but a folded line must not cut a character in half.
     */
    #[Test]
    public function foldsWithoutSplittingMultiByteCharacters(): void
    {
        $output = $this->serialize($this->icsEvent(description: str_repeat('ä', 120)));

        foreach (explode("\r\n", $output) as $line) {
            self::assertLessThanOrEqual(75, strlen($line), 'Unfolded line: ' . $line);
        }

        self::assertSame($output, mb_convert_encoding($output, 'UTF-8', 'UTF-8'), 'Output is no longer valid UTF-8');
    }

    /**
     * Unfolding is defined as removing CRLF plus the single following space.
     */
    #[Test]
    public function foldedContentSurvivesUnfolding(): void
    {
        $description = str_repeat('Lorem ipsum dolor sit amet. ', 12);

        $output = $this->serialize($this->icsEvent(description: $description));
        $unfolded = str_replace("\r\n ", '', $output);

        self::assertStringContainsString('DESCRIPTION:' . trim($description), $unfolded);
    }

    #[Test]
    public function marksCanceledAppointmentsAsCancelled(): void
    {
        self::assertStringContainsString("STATUS:CANCELLED\r\n", $this->serialize($this->icsEvent(cancelled: true)));
        self::assertStringContainsString("STATUS:CONFIRMED\r\n", $this->serialize($this->icsEvent()));
    }

    #[Test]
    public function writesCategoriesAsAnUnescapedList(): void
    {
        $output = $this->serialize($this->icsEvent(categories: ['Talks', 'Wine, Cheese']));

        self::assertStringContainsString('CATEGORIES:Talks,Wine\, Cheese' . "\r\n", $output);
    }

    #[Test]
    public function writesTheOrganizerAsAMailtoUri(): void
    {
        $output = $this->serialize($this->icsEvent(
            organizerEmail: 'jane@example.org',
            organizerName: 'Jane Doe',
        ));

        self::assertStringContainsString('ORGANIZER;CN="Jane Doe":mailto:jane@example.org' . "\r\n", $output);
    }

    #[Test]
    public function omitsTheCommonNameWhenNoOrganizerNameIsKnown(): void
    {
        $output = $this->serialize($this->icsEvent(organizerEmail: 'jane@example.org'));

        self::assertStringContainsString('ORGANIZER:mailto:jane@example.org' . "\r\n", $output);
    }

    #[Test]
    public function omitsEmptyProperties(): void
    {
        $output = $this->serialize($this->icsEvent());

        self::assertStringNotContainsString('DESCRIPTION:', $output);
        self::assertStringNotContainsString('LOCATION:', $output);
        self::assertStringNotContainsString('URL:', $output);
        self::assertStringNotContainsString('CATEGORIES:', $output);
        self::assertStringNotContainsString('ORGANIZER', $output);
    }

    #[Test]
    public function writesTheSequenceAndLastModifiedFromTheRecordTimestamp(): void
    {
        $output = $this->serialize($this->icsEvent(
            sequence: 1748779200,
            lastModified: new \DateTimeImmutable('@1748779200'),
        ));

        self::assertStringContainsString("SEQUENCE:1748779200\r\n", $output);
        self::assertStringContainsString("LAST-MODIFIED:20250601T120000Z\r\n", $output);
    }

    #[Test]
    public function writesOneVeventPerEvent(): void
    {
        $output = IcsSerializer::serialize(
            [$this->icsEvent(uid: 'entry-1@example.org'), $this->icsEvent(uid: 'entry-2@example.org')],
            new \DateTimeImmutable(self::NOW)
        );

        self::assertSame(2, substr_count($output, 'BEGIN:VEVENT'));
        self::assertStringContainsString("UID:entry-1@example.org\r\n", $output);
        self::assertStringContainsString("UID:entry-2@example.org\r\n", $output);
    }

    #[Test]
    public function stillProducesAValidCalendarWithoutEvents(): void
    {
        $output = IcsSerializer::serialize([], new \DateTimeImmutable(self::NOW));

        self::assertStringNotContainsString('BEGIN:VEVENT', $output);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $output);
    }

    private function serialize(IcsEvent $event): string
    {
        return IcsSerializer::serialize([$event], new \DateTimeImmutable(self::NOW));
    }

    /**
     * @param string[] $categories
     */
    private function icsEvent(
        string $uid = 'entry-1@example.org',
        ?\DateTimeImmutable $start = null,
        ?\DateTimeImmutable $end = null,
        bool $allDay = false,
        string $summary = 'Kickoff',
        string $description = '',
        string $location = '',
        ?string $url = null,
        bool $cancelled = false,
        array $categories = [],
        ?string $organizerEmail = null,
        ?string $organizerName = null,
        int $sequence = 0,
        ?\DateTimeImmutable $lastModified = null,
    ): IcsEvent {
        return new IcsEvent(
            uid: $uid,
            start: $start ?? new \DateTimeImmutable('2025-07-01 09:00:00', new \DateTimeZone('Europe/Berlin')),
            end: $end ?? new \DateTimeImmutable('2025-07-01 10:00:00', new \DateTimeZone('Europe/Berlin')),
            allDay: $allDay,
            summary: $summary,
            description: $description,
            location: $location,
            url: $url,
            cancelled: $cancelled,
            categories: $categories,
            organizerEmail: $organizerEmail,
            organizerName: $organizerName,
            sequence: $sequence,
            lastModified: $lastModified,
        );
    }
}

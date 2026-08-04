<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Serializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Serializer\VkurkoCalendarSerializer;

/**
 * Covers the pure row→vkurko/calendar mapping.
 *
 * Every fixture row deliberately keeps `pid` at 0 so buildEventSingleUrl()
 * short-circuits to null before it would reach SiteFinder — the URL building
 * itself needs a site configuration and is therefore covered functionally.
 */
final class VkurkoCalendarSerializerTest extends TestCase
{
    /** 2025-01-01T00:00:00Z */
    private const START = 1735689600;

    /** 2025-01-01T01:00:00Z */
    private const END = 1735693200;

    #[Test]
    public function mapsStartAndEndToLocalIsoStrings(): void
    {
        $result = $this->serializeInTimeZone('UTC', [
            $this->row(['start_date' => self::START, 'end_date' => self::END]),
        ]);

        self::assertSame('2025-01-01T00:00:00', $result[0]['start']);
        self::assertSame('2025-01-01T01:00:00', $result[0]['end']);
    }

    /**
     * The emitted strings carry no offset, so they must already be in the installation's
     * timezone — a consumer reads them as local time.
     */
    #[Test]
    public function convertsTimestampsIntoTheInstallationTimezone(): void
    {
        $result = $this->serializeInTimeZone('Europe/Berlin', [
            $this->row(['start_date' => self::START, 'end_date' => self::END]),
        ]);

        self::assertSame('2025-01-01T01:00:00', $result[0]['start']);
        self::assertSame('2025-01-01T02:00:00', $result[0]['end']);
    }

    #[Test]
    public function derivesTheThirtyMinuteFallbackFromTheConvertedStart(): void
    {
        $result = $this->serializeInTimeZone('Europe/Berlin', [
            $this->row(['start_date' => self::START, 'end_date' => 0]),
        ]);

        self::assertSame('2025-01-01T01:30:00', $result[0]['end']);
    }

    /**
     * vkurko/calendar refuses to render an entry without an end, so a missing one
     * is filled with a 30 minute default — even for all-day entries.
     */
    #[Test]
    public function fallsBackToThirtyMinutesWhenTheEndDateIsMissing(): void
    {
        $result = $this->serializeInTimeZone('UTC', [
            $this->row(['start_date' => self::START, 'end_date' => 0]),
        ]);

        self::assertSame('2025-01-01T00:00:00', $result[0]['start']);
        self::assertSame('2025-01-01T00:30:00', $result[0]['end']);
    }

    #[Test]
    public function appliesTheThirtyMinuteFallbackToAllDayEntriesToo(): void
    {
        $result = $this->serializeInTimeZone('UTC', [
            $this->row(['start_date' => self::START, 'end_date' => null, 'all_day' => 1]),
        ]);

        self::assertTrue($result[0]['allDay']);
        self::assertSame('2025-01-01T00:30:00', $result[0]['end']);
    }

    #[Test]
    public function leavesBothDatesNullWhenTheStartDateIsMissing(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([
            $this->row(['start_date' => 0, 'end_date' => 0]),
        ]);

        self::assertNull($result[0]['start']);
        self::assertNull($result[0]['end']);
    }

    #[Test]
    public function prefixesTheIdWithEntry(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([$this->row(['uid' => 17])]);

        self::assertSame('entry-17', $result[0]['id']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[Test]
    #[DataProvider('titleProvider')]
    public function composesTheTitleFromEntryAndEventTitle(array $overrides, ?string $expected): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([$this->row($overrides)]);

        self::assertSame($expected, $result[0]['title']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string|null}>
     */
    public static function titleProvider(): array
    {
        return [
            'both titles append the event in brackets' => [
                ['title' => 'Kickoff', 'event_title' => 'Annual Conference'],
                'Kickoff (Annual Conference)',
            ],
            'entry title only stays untouched' => [
                ['title' => 'Kickoff', 'event_title' => null],
                'Kickoff',
            ],
            'event title only is used verbatim' => [
                ['title' => null, 'event_title' => 'Annual Conference'],
                'Annual Conference',
            ],
            'neither title yields null' => [
                ['title' => null, 'event_title' => null],
                null,
            ],
            'empty entry title falls back to the event title' => [
                ['title' => '', 'event_title' => 'Annual Conference'],
                'Annual Conference',
            ],
        ];
    }

    #[Test]
    public function castsFlagsToBooleans(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([
            $this->row(['all_day' => '1', 'canceled' => '1']),
        ]);

        self::assertTrue($result[0]['allDay']);
        self::assertTrue($result[0]['extendedProps']['appointmentCanceled']);
    }

    #[Test]
    public function defaultsCanceledToFalseWhenTheColumnIsAbsent(): void
    {
        $row = $this->row();
        unset($row['canceled']);

        $result = VkurkoCalendarSerializer::serializeBackendEntries([$row]);

        self::assertFalse($result[0]['extendedProps']['appointmentCanceled']);
    }

    #[Test]
    public function castsCalendarCategoryAndStatusToIntegers(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([
            $this->row(['calendar' => '3', 'event_category_id' => '9', 'event_status' => '2']),
        ]);

        self::assertSame(3, $result[0]['extendedProps']['calendarUid']);
        self::assertSame(9, $result[0]['extendedProps']['eventCategoryId']);
        self::assertSame(2, $result[0]['extendedProps']['eventStatus']);
    }

    /**
     * A LEFT JOIN that found no category/event must surface as null rather than 0,
     * so the frontend can tell "no category" from "category 0".
     */
    #[Test]
    public function keepsMissingCategoryAndStatusAsNull(): void
    {
        $row = $this->row();
        unset($row['event_category_id'], $row['event_status']);

        $result = VkurkoCalendarSerializer::serializeBackendEntries([$row]);

        self::assertNull($result[0]['extendedProps']['eventCategoryId']);
        self::assertNull($result[0]['extendedProps']['eventStatus']);
    }

    #[Test]
    public function defaultsJoinedTitlesToEmptyStrings(): void
    {
        $row = $this->row();
        unset($row['calendar_title'], $row['record_type']);

        $result = VkurkoCalendarSerializer::serializeBackendEntries([$row]);

        self::assertSame('', $result[0]['extendedProps']['calendarTitle']);
        self::assertSame('', $result[0]['extendedProps']['recordType']);
    }

    #[Test]
    public function passesTheRemainingEventAndAppointmentFieldsThrough(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([
            $this->row([
                'event_uid'            => 5,
                'event_description'    => 'Event description',
                'event_category_title' => 'Workshops',
                'event_language'       => 'de',
                'event_owner'          => 12,
                'description'          => 'Appointment description',
                'location_name'        => 'Room 101',
                'speakers'             => 'Jane Doe',
            ]),
        ]);

        $props = $result[0]['extendedProps'];
        self::assertSame(5, $props['eventUid']);
        self::assertSame('Event description', $props['eventDescription']);
        self::assertSame('Workshops', $props['eventCategoryTitle']);
        self::assertSame('de', $props['eventLanguage']);
        self::assertSame(12, $props['eventOwner']);
        self::assertSame('Appointment description', $props['appointmentDescription']);
        self::assertSame('Room 101', $props['appointmentLocation']);
        self::assertSame('Jane Doe', $props['appointmentSpeakers']);
    }

    /**
     * Without a resolvable page the single-view URL cannot be built and must be
     * null instead of a broken link.
     */
    #[Test]
    public function returnsNoUrlForARecordWithoutAPage(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([$this->row(['pid' => 0])]);

        self::assertNull($result[0]['extendedProps']['url']);
    }

    #[Test]
    public function returnsAnEmptyListForAnEmptyResultSet(): void
    {
        self::assertSame([], VkurkoCalendarSerializer::serializeBackendEntries([]));
    }

    #[Test]
    public function preservesTheRowOrder(): void
    {
        $result = VkurkoCalendarSerializer::serializeBackendEntries([
            $this->row(['uid' => 1]),
            $this->row(['uid' => 2]),
            $this->row(['uid' => 3]),
        ]);

        self::assertSame(['entry-1', 'entry-2', 'entry-3'], array_column($result, 'id'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function serializeInTimeZone(string $timeZone, array $rows): array
    {
        $previousTimeZone = date_default_timezone_get();
        date_default_timezone_set($timeZone);

        try {
            return VkurkoCalendarSerializer::serializeBackendEntries($rows);
        } finally {
            date_default_timezone_set($previousTimeZone);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'uid'                  => 1,
            // pid 0 keeps buildEventSingleUrl() away from SiteFinder.
            'pid'                  => 0,
            'title'                => 'Kickoff',
            'start_date'           => self::START,
            'end_date'             => self::END,
            'all_day'              => 0,
            'calendar'             => 2,
            'calendar_title'       => 'Main calendar',
            'record_type'          => 'default',
            'description'          => null,
            'canceled'             => 0,
            'speakers'             => null,
            'location_name'        => null,
            'event_uid'            => 4,
            'event_title'          => null,
            'event_description'    => null,
            'event_category_title' => null,
            'event_category_id'    => null,
            'event_status'         => 2,
            'event_language'       => '',
            'event_owner'          => null,
        ], $overrides);
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3Calendar\Service\CalendarEventCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPendingCreationService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class CalendarEventCreationServiceTest extends AbstractCalendarFunctionalTestCase
{
    private CalendarEventCreationService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->setUpBackendUser(1);
        $this->subject = new CalendarEventCreationService(
            $this->get(ConnectionPool::class),
            $this->get(CalendarPendingCreationService::class),
        );
    }

    #[Test]
    public function createsAnEventAndItsAppointment(): void
    {
        $result = $this->subject->create(2, 1767225600, 1767229200, false, 1);

        self::assertTrue($result['success']);
        self::assertArrayHasKey('eventUid', $result);
        self::assertArrayHasKey('entryUid', $result);

        $eventUid = (int)$result['eventUid'];
        $event = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_EVENT)
            ->select('uid', 'record_type')
            ->from(self::TABLE_EVENT)
            ->where('uid = ' . $eventUid)
            ->executeQuery()
            ->fetchAssociative();
        $entry = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_ENTRY)
            ->select('event', 'calendar', 'record_type', 'start_date', 'end_date', 'all_day')
            ->from(self::TABLE_ENTRY)
            ->where('event = ' . $eventUid)
            ->executeQuery()
            ->fetchAssociative();

        self::assertSame('event', $event['record_type'] ?? null);
        self::assertSame($eventUid, (int)($entry['event'] ?? 0));
        self::assertSame(1, (int)($entry['calendar'] ?? 0));
        self::assertSame('event-appointment', $entry['record_type'] ?? null);
        self::assertSame(1767225600, (int)($entry['start_date'] ?? 0));
        self::assertSame(1767229200, (int)($entry['end_date'] ?? 0));
        self::assertSame(0, (int)($entry['all_day'] ?? 1));
    }

    #[Test]
    public function createsAnAppointmentWhenTheCreationTypeIsEvent(): void
    {
        $result = $this->subject->create(2, 1767225600, 1767229200, true);

        self::assertTrue($result['success']);
        self::assertArrayHasKey('entryUid', $result);
        self::assertSame(1, (int)($this->get(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_ENTRY)
            ->select('all_day')
            ->from(self::TABLE_ENTRY)
            ->where('uid = ' . (int)$result['entryUid'])
            ->executeQuery()
            ->fetchOne()));
    }

    #[Test]
    public function createsAnAppointmentBelowTheFirstExistingEvent(): void
    {
        $result = $this->subject->createAppointment(2, 1767225600, 1767229200, false, 1);

        self::assertTrue($result['success']);
        self::assertArrayNotHasKey('eventUid', $result);
        self::assertArrayHasKey('entryUid', $result);

        $entry = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_ENTRY)
            ->select('event', 'calendar')
            ->from(self::TABLE_ENTRY)
            ->where('uid = ' . (int)$result['entryUid'])
            ->executeQuery()
            ->fetchAssociative();

        self::assertSame(1, (int)($entry['event'] ?? 0));
        self::assertSame(1, (int)($entry['calendar'] ?? 0));
    }

    #[Test]
    public function cleanupRemovesAnUntitledAppointmentWithoutRemovingItsEvent(): void
    {
        $result = $this->subject->createAppointment(2, 1767225600, 1767229200, false);
        $entryUid = (int)$result['entryUid'];

        self::assertTrue($this->get(CalendarPendingCreationService::class)->cleanupEntry($entryUid, 2));
        self::assertSame(0, $this->countRecords(self::TABLE_ENTRY, $entryUid));
        self::assertSame(1, $this->countRecords(self::TABLE_EVENT, 1));
    }

    #[Test]
    public function cleanupRemovesAnUntitledEventAndItsAppointment(): void
    {
        $result = $this->subject->create(2, 1767225600, 1767229200, false);
        $eventUid = (int)$result['eventUid'];

        self::assertTrue($this->get(CalendarPendingCreationService::class)->cleanupEvent($eventUid, 2));
        self::assertSame(0, $this->countRecords(self::TABLE_EVENT, $eventUid));
        self::assertSame(0, $this->countEntriesForEvent($eventUid));
    }

    #[Test]
    public function cleanupKeepsAnEventThatAlreadyHasATitle(): void
    {
        $result = $this->subject->create(2, 1767225600, 1767229200, false);
        $eventUid = (int)$result['eventUid'];
        $this->get(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['title' => 'Created event'], ['uid' => $eventUid]);

        self::assertTrue($this->get(CalendarPendingCreationService::class)->cleanupEvent($eventUid, 2));
        self::assertSame(1, $this->countRecords(self::TABLE_EVENT, $eventUid));
        self::assertSame(1, $this->countEntriesForEvent($eventUid));
    }

    #[Test]
    public function cleanupKeepsAnAppointmentThatAlreadyHasATitle(): void
    {
        $result = $this->subject->create(2, 1767225600, 1767229200, false);
        $eventUid = (int)$result['eventUid'];

        $this->get(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_ENTRY)
            ->update(self::TABLE_ENTRY, ['title' => 'Created appointment'], ['event' => $eventUid]);

        self::assertTrue($this->get(CalendarPendingCreationService::class)->cleanupEvent($eventUid, 2));
        self::assertSame(1, $this->countRecords(self::TABLE_EVENT, $eventUid));
        self::assertSame(1, $this->countEntriesForEvent($eventUid));
    }

    #[Test]
    public function cleanupDoesNotRemoveAnEventFromAnotherStoragePage(): void
    {
        $result = $this->subject->create(2, 1767225600, 1767229200, false);
        $eventUid = (int)$result['eventUid'];

        self::assertFalse($this->get(CalendarPendingCreationService::class)->cleanupEvent($eventUid, 999));
        self::assertSame(1, $this->countRecords(self::TABLE_EVENT, $eventUid));
        self::assertSame(1, $this->countEntriesForEvent($eventUid));
    }

    private function countRecords(string $table, int $uid): int
    {
        return (int)$this->get(ConnectionPool::class)
            ->getQueryBuilderForTable($table)
            ->count('uid')
            ->from($table)
            ->where('uid = ' . $uid)
            ->executeQuery()
            ->fetchOne();
    }

    private function countEntriesForEvent(int $eventUid): int
    {
        return (int)$this->get(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_ENTRY)
            ->count('uid')
            ->from(self::TABLE_ENTRY)
            ->where('event = ' . $eventUid)
            ->executeQuery()
            ->fetchOne();
    }
}

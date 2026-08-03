<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Widgets\Provider;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;
use Xima\XimaTypo3Calendar\Widgets\Provider\ReadyToPublishEventsDataProvider;
use Xima\XimaTypo3Calendar\Widgets\Provider\UpcomingAppointmentsDataProvider;

/**
 * The widget queries are time-relative, so the fixture is written at runtime
 * against the current timestamp rather than imported from a CSV with baked-in
 * dates that would drift out of the query windows.
 */
final class WidgetDataProviderTest extends AbstractCalendarFunctionalTestCase
{
    private const DAY = 86400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->insertCalendar();
    }

    #[Test]
    public function upcomingAppointmentsReturnsAppointmentsOfLiveEventsInsideThePreviewWindow(): void
    {
        $this->insertEvent(1, 'Live event', 2);
        $this->insertEntry(1, 1, 'Tomorrow', time() + self::DAY);

        $items = $this->upcomingAppointments()->getItems();

        self::assertCount(1, $items);
        self::assertSame('Tomorrow', $items[0]['entry_title']);
        self::assertSame('Live event', $items[0]['event_title']);
    }

    #[Test]
    public function upcomingAppointmentsIgnoresAppointmentsOfEventsThatAreNotLive(): void
    {
        $this->insertEvent(1, 'Draft event', 0);
        $this->insertEntry(1, 1, 'Tomorrow', time() + self::DAY);

        self::assertSame([], $this->upcomingAppointments()->getItems());
    }

    #[Test]
    public function upcomingAppointmentsIgnoresAppointmentsInThePast(): void
    {
        $this->insertEvent(1, 'Live event', 2);
        $this->insertEntry(1, 1, 'Yesterday', time() - self::DAY);

        self::assertSame([], $this->upcomingAppointments()->getItems());
    }

    #[Test]
    public function upcomingAppointmentsIgnoresAppointmentsBeyondThePreviewWindow(): void
    {
        $this->insertEvent(1, 'Live event', 2);
        $this->insertEntry(1, 1, 'Way ahead', time() + 30 * self::DAY);

        self::assertSame([], $this->upcomingAppointments(daysInPreview: 10)->getItems());
    }

    #[Test]
    public function upcomingAppointmentsOrdersByStartDateAscending(): void
    {
        $this->insertEvent(1, 'Live event', 2);
        $this->insertEntry(1, 1, 'Later', time() + 3 * self::DAY);
        $this->insertEntry(2, 1, 'Sooner', time() + self::DAY);

        $items = $this->upcomingAppointments()->getItems();

        self::assertSame(['Sooner', 'Later'], array_column($items, 'entry_title'));
    }

    #[Test]
    public function upcomingAppointmentsHonoursTheConfiguredLimit(): void
    {
        $this->insertEvent(1, 'Live event', 2);
        $this->insertEntry(1, 1, 'First', time() + self::DAY);
        $this->insertEntry(2, 1, 'Second', time() + 2 * self::DAY);

        self::assertCount(1, $this->upcomingAppointments(limit: 1)->getItems());
    }

    /**
     * KNOWN DEFECT — ReadyToPublishEventsDataProvider selects `e.title` and
     * `en.start_date` while grouping by `e.uid` alone. That is invalid under
     * `ONLY_FULL_GROUP_BY`, which is MySQL's default since 5.7 and which the
     * testing framework enables for every functional test. On a server with the
     * relaxed sql_mode the widget works; on a strict one it throws and the
     * dashboard breaks.
     *
     * The fix is in the provider's query — either group by every selected column
     * or aggregate `en.start_date` with `MIN()`. Once it lands, replace this test
     * with the behavioural coverage it currently blocks: an event awaiting review
     * is returned, live/draft events are not, events whose appointments are all
     * in the past are not, and an event with several upcoming appointments is
     * reported exactly once.
     */
    #[Test]
    public function readyToPublishEventsCurrentlyFailsUnderStrictGroupBy(): void
    {
        $this->insertEvent(1, 'Review event', 1);
        $this->insertEntry(1, 1, 'Upcoming', time() + self::DAY);

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessageMatches('/isn\'t in GROUP BY/');

        $this->readyToPublishEvents()->getItems();
    }

    /**
     * Every provider dispatches BeforeWidgetItemsFetchedEvent and re-reads the
     * QueryBuilder afterwards, so a listener can narrow the widget's result set.
     */
    #[Test]
    public function providersLetAListenerNarrowTheQuery(): void
    {
        $this->insertEvent(1, 'Live event', 2);
        $this->insertEntry(1, 1, 'Tomorrow', time() + self::DAY);

        $provider = new UpcomingAppointmentsDataProvider(
            $this->get(ConnectionPool::class),
            $this->dispatcherNarrowingTo('a.title', 'Nothing matches this'),
            10,
            10
        );

        self::assertSame([], $provider->getItems());
    }

    #[Test]
    public function providersPassTheirOwnClassNameToTheEvent(): void
    {
        $seen = null;
        $dispatcher = new class($seen) implements EventDispatcherInterface {
            public function __construct(public ?string &$seen)
            {
            }

            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeWidgetItemsFetchedEvent) {
                    $this->seen = $event->getDispatchingClassName();
                }
                return $event;
            }
        };

        (new UpcomingAppointmentsDataProvider($this->get(ConnectionPool::class), $dispatcher, 10, 10))->getItems();

        self::assertSame(UpcomingAppointmentsDataProvider::class, $seen);
    }

    private function upcomingAppointments(int $daysInPreview = 10, int $limit = 10): UpcomingAppointmentsDataProvider
    {
        return new UpcomingAppointmentsDataProvider(
            $this->get(ConnectionPool::class),
            $this->get(EventDispatcherInterface::class),
            $daysInPreview,
            $limit
        );
    }

    private function readyToPublishEvents(int $limit = 8): ReadyToPublishEventsDataProvider
    {
        return new ReadyToPublishEventsDataProvider(
            $this->get(ConnectionPool::class),
            $this->get(EventDispatcherInterface::class),
            $limit
        );
    }

    private function dispatcherNarrowingTo(string $field, string $value): EventDispatcherInterface
    {
        return new class($field, $value) implements EventDispatcherInterface {
            public function __construct(private readonly string $field, private readonly string $value)
            {
            }

            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeWidgetItemsFetchedEvent) {
                    $queryBuilder = $event->getQueryBuilder();
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->eq($this->field, $queryBuilder->createNamedParameter($this->value))
                    );
                    $event->setQueryBuilder($queryBuilder);
                }
                return $event;
            }
        };
    }

    private function insertCalendar(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_CALENDAR)->insert(
            self::TABLE_CALENDAR,
            ['uid' => 1, 'pid' => 2, 'title' => 'Main calendar', 'hidden' => 0, 'deleted' => 0]
        );
    }

    private function insertEvent(int $uid, string $title, int $status): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)->insert(
            self::TABLE_EVENT,
            [
                'uid' => $uid,
                'pid' => 2,
                'record_type' => 'event',
                'title' => $title,
                'status' => $status,
                'hidden' => 0,
                'deleted' => 0,
            ]
        );
    }

    private function insertEntry(int $uid, int $eventUid, string $title, int $startDate): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)->insert(
            self::TABLE_ENTRY,
            [
                'uid' => $uid,
                'pid' => 2,
                'record_type' => 'event-appointment',
                'event' => $eventUid,
                'calendar' => 1,
                'title' => $title,
                'start_date' => $startDate,
                'end_date' => $startDate + 3600,
                'hidden' => 0,
                'deleted' => 0,
            ]
        );
    }
}

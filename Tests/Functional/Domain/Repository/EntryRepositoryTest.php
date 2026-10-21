<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class EntryRepositoryTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar-categories.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/categories.csv');
    }

    #[Test]
    public function backendCalendarEntriesIncludeAppointmentsOverlappingTheRequestedWindow(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767227400,
            1767231000,
        );

        self::assertContains(1, array_map('intval', array_column($rows, 'uid')));
    }

    #[Test]
    public function backendCalendarEntriesCanBeFilteredByEventStatus(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767225600,
            1767229200,
            [],
            ['statuses' => [2]],
        );

        self::assertSame([1], array_map('intval', array_column($rows, 'uid')));
    }

    #[Test]
    public function backendCalendarEntriesCanBeFilteredByCanceledStatus(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767225600,
            1767229200,
            [],
            ['statuses' => ['canceled']],
        );

        self::assertSame([2], array_map('intval', array_column($rows, 'uid')));
    }

    #[Test]
    public function canceledStatusNarrowsSelectedEventStatuses(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767225600,
            1767229200,
            [],
            ['statuses' => [2, 'canceled']],
        );

        self::assertSame([], $rows);
    }

    #[Test]
    public function backendCalendarEntriesCanBeFilteredByParentEventType(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767225600,
            1767229200,
            [],
            [
                'filters' => [
                    'recordTypes' => ['event'],
                ],
            ],
        );

        self::assertCount(2, $rows);

        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767225600,
            1767229200,
            [],
            [
                'filters' => [
                    'recordTypes' => ['event-appointment'],
                ],
            ],
        );

        self::assertSame([], $rows);
    }

    #[Test]
    public function backendCalendarEntriesCanBeFilteredByCategory(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767225600,
            1767229200,
            [],
            ['categories' => [2]],
        );

        self::assertSame([2], array_map('intval', array_column($rows, 'uid')));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function categoryTitlePerLanguageDataProvider(): iterable
    {
        yield 'default language' => [0, 'Vorträge'];
        yield 'translated language' => [1, 'Lectures'];
        yield 'hidden translation falls back to the default' => [2, 'Vorträge'];
        yield 'missing translation falls back to the default' => [3, 'Vorträge'];
    }

    #[Test]
    #[DataProvider('categoryTitlePerLanguageDataProvider')]
    public function backendCalendarEntriesCarryTheCategoryTitleOfTheRequestedLanguage(int $languageId, string $expectedTitle): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767227400,
            1767231000,
            [],
            [],
            $languageId,
        );

        $row = array_column($rows, null, 'uid')[1] ?? null;

        self::assertIsArray($row);
        self::assertSame($expectedTitle, $row['event_category_title']);
        self::assertSame(10, (int)$row['event_category_id']);
    }
}

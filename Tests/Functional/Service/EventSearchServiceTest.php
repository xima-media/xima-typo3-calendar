<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3Calendar\Service\EventSearchService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Users and groups come from Fixtures/backend-permissions.csv, the events from
 * Fixtures/event-search.csv. "Now" is 1800000000.
 */
final class EventSearchServiceTest extends AbstractCalendarFunctionalTestCase
{
    private const ADMIN = 1;
    private const EDITOR = 2;
    private const EDITOR_WITHOUT_TABLES = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/backend-permissions.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/event-search.csv');
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@1800000000')));
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.org/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function searchResultsDataProvider(): iterable
    {
        // Upcoming by next appointment, then past by last appointment, then without appointments by title.
        yield 'admin sees every event, hidden ones included' => [self::ADMIN, [3, 2, 1, 7, 4, 5, 9]];
        yield 'editor sees neither foreign drafts nor events on unreadable pages' => [self::EDITOR, [3, 2, 1, 7, 5]];
        yield 'editor without table access gets nothing' => [self::EDITOR_WITHOUT_TABLES, []];
    }

    /**
     * @param list<int> $expectedUids
     */
    #[Test]
    #[DataProvider('searchResultsDataProvider')]
    public function findsTheEventsTheUserMayAssignInOrderOfRelevance(int $backendUserUid, array $expectedUids): void
    {
        $this->setUpBackendUser($backendUserUid);

        $results = $this->get(EventSearchService::class)->search('summer');

        self::assertSame($expectedUids, array_column($results, 'uid'));
    }

    #[Test]
    public function reportsTheDateRangeOfTheAppointments(): void
    {
        $this->setUpBackendUser(self::ADMIN);

        $results = $this->get(EventSearchService::class)->search('Summer');
        $byUid = array_column($results, null, 'uid');

        self::assertSame(
            ['uid' => 3, 'title' => 'Summer workshop', 'firstStart' => 1600000000, 'lastEnd' => 1900003600, 'details' => []],
            $byUid[3],
        );
        self::assertSame(1700003600, $byUid[1]['lastEnd'], 'A deleted appointment must not extend the range.');
        self::assertNull($byUid[7]['firstStart']);
        self::assertNull($byUid[7]['lastEnd']);
    }

    #[Test]
    public function treatsLikeWildcardsAsText(): void
    {
        $this->setUpBackendUser(self::ADMIN);

        self::assertSame([7], array_column($this->get(EventSearchService::class)->search('0%'), 'uid'));
    }

    #[Test]
    public function needsAMinimumSearchLength(): void
    {
        $this->setUpBackendUser(self::ADMIN);

        self::assertSame([], $this->get(EventSearchService::class)->search(' S '));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function selectableDataProvider(): iterable
    {
        yield 'live event' => [self::EDITOR, 1, true];
        yield 'hidden event' => [self::EDITOR, 5, true];
        yield 'foreign draft' => [self::EDITOR, 4, false];
        yield 'event on an unreadable page' => [self::EDITOR, 9, false];
        yield 'event on an unreadable page, admin' => [self::ADMIN, 9, true];
        yield 'deleted event' => [self::ADMIN, 6, false];
        yield 'unknown event' => [self::ADMIN, 999, false];
        yield 'no table access' => [self::EDITOR_WITHOUT_TABLES, 1, false];
    }

    #[Test]
    #[DataProvider('selectableDataProvider')]
    public function acceptsOnlyEventsTheSearchWouldOffer(int $backendUserUid, int $eventUid, bool $expected): void
    {
        $this->setUpBackendUser($backendUserUid);

        self::assertSame($expected, $this->get(EventSearchService::class)->isSelectable($eventUid));
    }
}

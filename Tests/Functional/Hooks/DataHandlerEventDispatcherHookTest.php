<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;
use Xima\XimaTypo3Calendar\Tests\Functional\Fixtures\RecordingEventListener;

/**
 * Drives the real DataHandler and asserts which lifecycle events reach a
 * listener. Every case here maps to a rule the hook implements explicitly —
 * the change-type taxonomy, the create-only rule, and the per-run dedup scope.
 */
final class DataHandlerEventDispatcherHookTest extends AbstractCalendarFunctionalTestCase
{
    private RecordingEventListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->setUpBackendUser(1);

        $this->listener = $this->get(RecordingEventListener::class);
        $this->listener->reset();
    }

    #[Test]
    public function creatingAnEventDispatchesCreatedExactlyOnce(): void
    {
        $newId = StringUtility::getUniqueId('NEW');

        $this->processDatamap([
            self::TABLE_EVENT => [
                $newId => ['pid' => 2, 'record_type' => 'event', 'title' => 'Fresh event', 'status' => 0],
            ],
        ]);

        self::assertSame([ChangeType::CREATED->value], $this->listener->changeTypesFor(self::TABLE_EVENT));
    }

    /**
     * A brand-new record only ever emits CREATED. The *_CHANGED variants describe
     * a transition from a prior value, which does not exist yet.
     */
    #[Test]
    public function creatingAnEntryWithALocationAndDatesDoesNotDispatchTheChangedVariants(): void
    {
        $newId = StringUtility::getUniqueId('NEW');

        $this->processDatamap([
            self::TABLE_ENTRY => [
                $newId => [
                    'pid'        => 2,
                    'record_type' => 'event-appointment',
                    'event'      => 1,
                    'calendar'   => 1,
                    'title'      => 'Fresh appointment',
                    'location'   => 1,
                    'start_date' => 1767225600,
                    'end_date'   => 1767229200,
                ],
            ],
        ]);

        self::assertSame([ChangeType::CREATED->value], $this->listener->changeTypesFor(self::TABLE_ENTRY));
    }

    #[Test]
    public function createdCarriesTheIncomingFieldsAsADiff(): void
    {
        $newId = StringUtility::getUniqueId('NEW');

        $this->processDatamap([
            self::TABLE_EVENT => [
                $newId => ['pid' => 2, 'record_type' => 'event', 'title' => 'Fresh event'],
            ],
        ]);

        $events = $this->listener->for(self::TABLE_EVENT, ChangeType::CREATED);
        self::assertCount(1, $events);
        self::assertArrayHasKey('title', $events[0]->changedFields);
        self::assertSame('Fresh event', $events[0]->changedFields['title']['new']);
    }

    #[Test]
    public function updatingATitleDispatchesUpdatedWithTheOldAndNewValue(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['title' => 'Renamed live event'],
            ],
        ]);

        $events = $this->listener->for(self::TABLE_EVENT, ChangeType::UPDATED);
        self::assertCount(1, $events);
        self::assertSame(1, $events[0]->uid);
        self::assertSame('Live event', $events[0]->changedFields['title']['old']);
        self::assertSame('Renamed live event', $events[0]->changedFields['title']['new']);
    }

    #[Test]
    public function writingAnUnchangedValueDispatchesNothing(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['title' => 'Live event'],
            ],
        ]);

        self::assertSame([], $this->listener->all());
    }

    #[Test]
    public function changingTheLocationOfAnEntryDispatchesLocationChangedAndUpdated(): void
    {
        $this->processDatamap([
            self::TABLE_ENTRY => [
                1 => ['location' => 2],
            ],
        ]);

        $changeTypes = $this->listener->changeTypesFor(self::TABLE_ENTRY);
        self::assertContains(ChangeType::LOCATION_CHANGED->value, $changeTypes);
        self::assertContains(ChangeType::UPDATED->value, $changeTypes);
    }

    #[Test]
    public function changingTheDateRangeOfAnEntryDispatchesDateRangeChanged(): void
    {
        $this->processDatamap([
            self::TABLE_ENTRY => [
                1 => ['start_date' => 1767312000, 'end_date' => 1767315600],
            ],
        ]);

        $events = $this->listener->for(self::TABLE_ENTRY, ChangeType::DATE_RANGE_CHANGED);
        self::assertCount(1, $events);
        self::assertSame(
            ['start_date', 'end_date'],
            array_keys($events[0]->changedFields)
        );
    }

    /**
     * The date-range change type is entry-scoped: the event table has no
     * start/end columns of its own, so it must never surface there.
     */
    #[Test]
    public function theDateRangeChangeTypeIsNotDispatchedForEvents(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['title' => 'Renamed', 'location' => 2],
            ],
        ]);

        self::assertNotContains(
            ChangeType::DATE_RANGE_CHANGED->value,
            $this->listener->changeTypesFor(self::TABLE_EVENT)
        );
    }

    #[Test]
    public function changingTheLocationOfAnEventAlsoDispatchesLocationChanged(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['location' => 1],
            ],
        ]);

        self::assertContains(
            ChangeType::LOCATION_CHANGED->value,
            $this->listener->changeTypesFor(self::TABLE_EVENT)
        );
    }

    #[Test]
    public function hidingARecordThroughTheDatamapDispatchesHidden(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['hidden' => 1],
            ],
        ]);

        self::assertContains(ChangeType::HIDDEN->value, $this->listener->changeTypesFor(self::TABLE_EVENT));
    }

    /**
     * Excluding `hidden` from the UPDATED definition is not enough on its own, because
     * DataHandler adds `tstamp` to the incoming field array before the hook sees it. The
     * hook therefore also excludes the DataHandler-managed control fields, so a pure
     * visibility toggle stays a single HIDDEN.
     */
    #[Test]
    public function hidingARecordDispatchesHiddenOnly(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['hidden' => 1],
            ],
        ]);

        self::assertSame([], $this->listener->for(self::TABLE_EVENT, ChangeType::UPDATED));
        self::assertSame(
            [ChangeType::HIDDEN->value],
            $this->listener->changeTypesFor(self::TABLE_EVENT)
        );
    }

    /**
     * The control-field exclusion must not swallow real edits: hiding a record while also
     * changing a content field still reports the content change.
     */
    #[Test]
    public function hidingARecordWhileEditingAFieldStillDispatchesUpdated(): void
    {
        $this->processDatamap([
            self::TABLE_EVENT => [
                1 => ['hidden' => 1, 'title' => 'Renamed while hiding'],
            ],
        ]);

        $updates = $this->listener->for(self::TABLE_EVENT, ChangeType::UPDATED);
        self::assertCount(1, $updates);
        self::assertSame(['title'], array_keys($updates[0]->changedFields));

        $hidden = $this->listener->for(self::TABLE_EVENT, ChangeType::HIDDEN);
        self::assertCount(1, $hidden, 'the visibility change is still reported alongside the edit');
        self::assertSame([], $hidden[0]->changedFields);
    }

    #[Test]
    public function unhidingARecordThroughTheDatamapDispatchesReactivated(): void
    {
        $this->processDatamap([self::TABLE_EVENT => [1 => ['hidden' => 1]]]);
        $this->listener->reset();

        $this->processDatamap([self::TABLE_EVENT => [1 => ['hidden' => 0]]]);

        self::assertContains(ChangeType::REACTIVATED->value, $this->listener->changeTypesFor(self::TABLE_EVENT));
    }

    #[Test]
    public function hiddenTransitionsCarryNoFieldDiff(): void
    {
        $this->processDatamap([self::TABLE_EVENT => [1 => ['hidden' => 1]]]);

        $events = $this->listener->for(self::TABLE_EVENT, ChangeType::HIDDEN);
        self::assertCount(1, $events);
        self::assertSame([], $events[0]->changedFields);
    }

    #[Test]
    public function theDisableCommandDispatchesHidden(): void
    {
        $this->processCmdmap([self::TABLE_EVENT => [1 => ['disable' => 1]]]);

        self::assertContains(ChangeType::HIDDEN->value, $this->listener->changeTypesFor(self::TABLE_EVENT));
    }

    #[Test]
    public function theEnableCommandDispatchesReactivated(): void
    {
        $this->processDatamap([self::TABLE_EVENT => [1 => ['hidden' => 1]]]);
        $this->listener->reset();

        $this->processCmdmap([self::TABLE_EVENT => [1 => ['enable' => 1]]]);

        self::assertContains(ChangeType::REACTIVATED->value, $this->listener->changeTypesFor(self::TABLE_EVENT));
    }

    #[Test]
    public function deletingAnEventDispatchesDeleted(): void
    {
        $this->processCmdmap([self::TABLE_EVENT => [3 => ['delete' => 1]]]);

        $events = $this->listener->for(self::TABLE_EVENT, ChangeType::DELETED);
        self::assertCount(1, $events);
        self::assertSame(3, $events[0]->uid);
        self::assertSame([], $events[0]->changedFields);
    }

    #[Test]
    public function copyingAnEventDispatchesCreatedForTheCopy(): void
    {
        $this->processCmdmap([self::TABLE_EVENT => [1 => ['copy' => 2]]]);

        $events = $this->listener->for(self::TABLE_EVENT, ChangeType::CREATED);
        self::assertCount(1, $events);
        self::assertNotSame(1, $events[0]->uid, 'the copy must be reported, not the source record');
    }

    /**
     * The dedup keys are scoped to one DataHandler run so a long-lived process
     * (CLI, scheduler, import queue) is not silenced for its whole lifetime.
     */
    #[Test]
    public function theSameChangeInASecondDataHandlerRunIsDispatchedAgain(): void
    {
        $this->processDatamap([self::TABLE_EVENT => [1 => ['title' => 'First rename']]]);
        $this->processDatamap([self::TABLE_EVENT => [1 => ['title' => 'Second rename']]]);

        self::assertCount(2, $this->listener->for(self::TABLE_EVENT, ChangeType::UPDATED));
    }

    #[Test]
    public function unhandledTablesAreIgnored(): void
    {
        $this->processDatamap([
            'pages' => [
                2 => ['title' => 'Renamed storage'],
            ],
        ]);

        self::assertSame([], $this->listener->all());
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     */
    private function processDatamap(array $datamap): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $cmdmap
     */
    private function processCmdmap(array $cmdmap): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmdmap);
        $dataHandler->process_cmdmap();
    }
}

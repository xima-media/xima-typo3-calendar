<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Runs DataHandler as the real backend users of Fixtures/workflow-permissions.csv: an editor
 * without `publish_live_events` / `view_all_events`, a publisher and an administrator.
 *
 * Events 11–14 belong to the editor (live, draft, review, rejected), 15–17 to a colleague
 * (draft and review are hidden from the editor, 17 is live). Appointments 11–14 belong to
 * the events 11, 17, 12 and 15.
 */
final class EventWorkflowGuardHookTest extends AbstractCalendarFunctionalTestCase
{
    private const ADMIN = 1;
    private const EDITOR = 2;
    private const PUBLISHER = 3;
    private const COLLEAGUE = 4;

    private const OWN_LIVE = 11;
    private const OWN_DRAFT = 12;
    private const OWN_REVIEW = 13;
    private const OWN_REJECTED = 14;
    private const FOREIGN_DRAFT = 15;
    private const FOREIGN_LIVE = 17;

    private const OWN_LIVE_APPOINTMENT = 11;
    private const FOREIGN_LIVE_APPOINTMENT = 12;
    private const OWN_DRAFT_APPOINTMENT = 13;
    private const FOREIGN_DRAFT_APPOINTMENT = 14;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/workflow-permissions.csv');
    }

    #[Test]
    public function anEditorCannotPublishTheirDraft(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([self::TABLE_EVENT => [self::OWN_DRAFT => ['status' => EventStatus::LIVE->value]]]);

        self::assertSame(EventStatus::DRAFT->value, $this->eventField(self::OWN_DRAFT, 'status'));
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function anEditorCannotCreateALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $eventId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([self::TABLE_EVENT => [$eventId => [
            'pid' => 2,
            'record_type' => 'event',
            'title' => 'Published on create',
            'status' => EventStatus::LIVE->value,
        ]]]);

        self::assertArrayNotHasKey($eventId, $dataHandler->substNEWwithIDs);
        self::assertSame(0, $this->countEventsTitled('Published on create'));
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function anEditorCannotEditALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([self::TABLE_EVENT => [self::OWN_LIVE => ['title' => 'Changed live']]]);

        self::assertSame('Own live', $this->eventField(self::OWN_LIVE, 'title'));
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function anEditorCannotMoveALiveEventToAnotherStatus(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        foreach ([EventStatus::DRAFT, EventStatus::REVIEW, EventStatus::REJECTED] as $target) {
            $this->process([self::TABLE_EVENT => [self::OWN_LIVE => ['status' => $target->value]]]);

            self::assertSame(EventStatus::LIVE->value, $this->eventField(self::OWN_LIVE, 'status'), $target->name);
        }
    }

    #[Test]
    public function anEditorCannotRejectAnEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_EVENT => [self::OWN_REVIEW => ['status' => EventStatus::REJECTED->value]]]);

        self::assertSame(EventStatus::REVIEW->value, $this->eventField(self::OWN_REVIEW, 'status'));
    }

    #[Test]
    public function anEditorCannotHideALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([self::TABLE_EVENT => [self::OWN_LIVE => ['hidden' => 1]]]);

        self::assertSame(0, $this->eventField(self::OWN_LIVE, 'hidden'));
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function anEditorCannotDeleteALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([], [self::TABLE_EVENT => [self::OWN_LIVE => ['delete' => 1]]]);

        self::assertSame(0, $this->eventField(self::OWN_LIVE, 'deleted'));
        self::assertSame(0, $this->appointmentField(self::OWN_LIVE_APPOINTMENT, 'deleted'));
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function anEditorMayDeleteTheirDraft(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([], [self::TABLE_EVENT => [self::OWN_DRAFT => ['delete' => 1]]]);

        self::assertSame(1, $this->eventField(self::OWN_DRAFT, 'deleted'));
    }

    #[Test]
    public function aCopyOfALiveEventIsADraftOfTheCopyingEditor(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([], [self::TABLE_EVENT => [self::FOREIGN_LIVE => ['copy' => 2]]]);

        $copyUid = (int)($dataHandler->copyMappingArray_merged[self::TABLE_EVENT][self::FOREIGN_LIVE] ?? 0);
        self::assertGreaterThan(0, $copyUid);
        self::assertSame(EventStatus::DRAFT->value, $this->eventField($copyUid, 'status'));
        self::assertSame(self::EDITOR, $this->eventField($copyUid, 'owner_be_user'));
        self::assertSame(EventStatus::LIVE->value, $this->eventField(self::FOREIGN_LIVE, 'status'));
        self::assertSame([], $dataHandler->errorLog);
        self::assertSame(1, $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)
            ->count('uid', self::TABLE_ENTRY, ['event' => $copyUid, 'deleted' => 0]));
    }

    #[Test]
    public function aCopyOfALiveEventIsADraftOfTheCopyingPublisher(): void
    {
        $this->setUpBackendUser(self::PUBLISHER);

        $dataHandler = $this->process([], [self::TABLE_EVENT => [self::FOREIGN_LIVE => ['copy' => 2]]]);

        $copyUid = (int)($dataHandler->copyMappingArray_merged[self::TABLE_EVENT][self::FOREIGN_LIVE] ?? 0);
        self::assertSame(EventStatus::DRAFT->value, $this->eventField($copyUid, 'status'));
        self::assertSame(self::PUBLISHER, $this->eventField($copyUid, 'owner_be_user'));
    }

    #[Test]
    public function anEditorCannotCopyAnEventHiddenFromThem(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([], [self::TABLE_EVENT => [self::FOREIGN_DRAFT => ['copy' => 2]]]);

        self::assertSame([], $dataHandler->copyMappingArray_merged[self::TABLE_EVENT] ?? []);
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function anEditorCannotEditOrDeleteAnEventHiddenFromThem(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_EVENT => [self::FOREIGN_DRAFT => ['title' => 'Taken over']]]);
        $this->process([], [self::TABLE_EVENT => [self::FOREIGN_DRAFT => ['delete' => 1]]]);

        self::assertSame('Foreign draft', $this->eventField(self::FOREIGN_DRAFT, 'title'));
        self::assertSame(0, $this->eventField(self::FOREIGN_DRAFT, 'deleted'));
    }

    #[Test]
    public function anEditorCannotEditOrDeleteAnAppointmentOfAnEventHiddenFromThem(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_ENTRY => [self::FOREIGN_DRAFT_APPOINTMENT => ['title' => 'Changed']]]);
        $this->process([], [self::TABLE_ENTRY => [self::FOREIGN_DRAFT_APPOINTMENT => ['delete' => 1]]]);

        self::assertSame('Foreign draft appointment', $this->appointmentField(self::FOREIGN_DRAFT_APPOINTMENT, 'title'));
        self::assertSame(0, $this->appointmentField(self::FOREIGN_DRAFT_APPOINTMENT, 'deleted'));
    }

    #[Test]
    public function anEditorCannotAddAnAppointmentToAnEventHiddenFromThem(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([self::TABLE_ENTRY => [$appointmentId => [
            'pid' => 2,
            'record_type' => 'event-appointment',
            'event' => self::FOREIGN_DRAFT,
            'calendar' => 1,
            'start_date' => 1767225600,
        ]]]);

        self::assertArrayNotHasKey($appointmentId, $dataHandler->substNEWwithIDs);
    }

    #[Test]
    public function anEditorCannotMoveAnAppointmentToAnEventHiddenFromThem(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_ENTRY => [self::OWN_DRAFT_APPOINTMENT => ['event' => self::FOREIGN_DRAFT]]]);

        self::assertSame(self::OWN_DRAFT, $this->appointmentField(self::OWN_DRAFT_APPOINTMENT, 'event'));
    }

    #[Test]
    public function anEditorCannotPullAnAppointmentOfAHiddenEventIntoTheirEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_EVENT => [self::OWN_DRAFT => [
            'appointments' => self::OWN_DRAFT_APPOINTMENT . ',' . self::FOREIGN_DRAFT_APPOINTMENT,
        ]]]);

        self::assertSame(self::FOREIGN_DRAFT, $this->appointmentField(self::FOREIGN_DRAFT_APPOINTMENT, 'event'));
    }

    #[Test]
    public function anEditorAlwaysOwnsTheEventsTheyCreate(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $eventId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([self::TABLE_EVENT => [$eventId => [
            'pid' => 2,
            'record_type' => 'event',
            'title' => 'Created for a colleague',
            'owner_be_user' => self::COLLEAGUE,
        ]]]);

        self::assertSame(self::EDITOR, $this->eventField((int)$dataHandler->substNEWwithIDs[$eventId], 'owner_be_user'));
    }

    #[Test]
    public function anEditorCannotHandTheirEventToSomebodyElse(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_EVENT => [self::OWN_DRAFT => ['owner_be_user' => self::COLLEAGUE]]]);

        self::assertSame(self::EDITOR, $this->eventField(self::OWN_DRAFT, 'owner_be_user'));
    }

    #[Test]
    public function anEditorCannotTakeOverALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_EVENT => [self::FOREIGN_LIVE => ['owner_be_user' => self::EDITOR]]]);

        self::assertSame(self::COLLEAGUE, $this->eventField(self::FOREIGN_LIVE, 'owner_be_user'));
    }

    #[Test]
    public function anEditorMayWorkOnTheirDraft(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([self::TABLE_EVENT => [self::OWN_DRAFT => ['title' => 'Renamed', 'status' => EventStatus::REVIEW->value]]]);

        self::assertSame([], $dataHandler->errorLog);
        self::assertSame('Renamed', $this->eventField(self::OWN_DRAFT, 'title'));
        self::assertSame(EventStatus::REVIEW->value, $this->eventField(self::OWN_DRAFT, 'status'));
    }

    #[Test]
    public function anEditorMayEditTheirEventDuringReview(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_EVENT => [self::OWN_REVIEW => ['title' => 'Fixed typo']]]);
        $this->process([self::TABLE_EVENT => [self::OWN_REJECTED => ['status' => EventStatus::DRAFT->value]]]);

        self::assertSame('Fixed typo', $this->eventField(self::OWN_REVIEW, 'title'));
        self::assertSame(EventStatus::DRAFT->value, $this->eventField(self::OWN_REJECTED, 'status'));
    }

    #[Test]
    public function anEditorMayEditAndDeleteAppointmentsOfALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $dataHandler = $this->process([self::TABLE_ENTRY => [self::FOREIGN_LIVE_APPOINTMENT => ['title' => 'Moved to noon', 'start_date' => 1767268800]]]);
        self::assertSame([], $dataHandler->errorLog);
        self::assertSame('Moved to noon', $this->appointmentField(self::FOREIGN_LIVE_APPOINTMENT, 'title'));

        $this->process([], [self::TABLE_ENTRY => [self::OWN_LIVE_APPOINTMENT => ['delete' => 1]]]);
        self::assertSame(1, $this->appointmentField(self::OWN_LIVE_APPOINTMENT, 'deleted'));
    }

    /**
     * The calendar module creates the appointment through the appointment form.
     */
    #[Test]
    public function anEditorMayAddAnAppointmentToALiveEventInTheAppointmentForm(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([self::TABLE_ENTRY => [$appointmentId => [
            'pid' => 2,
            'record_type' => 'event-appointment',
            'event' => self::FOREIGN_LIVE,
            'calendar' => 1,
            'start_date' => 1767225600,
        ]]]);

        self::assertSame([], $dataHandler->errorLog);
        self::assertSame(self::FOREIGN_LIVE, $this->appointmentField((int)$dataHandler->substNEWwithIDs[$appointmentId], 'event'));
    }

    /**
     * Saving the read-only event form submits the appointments relation and the inline children.
     */
    #[Test]
    public function anEditorMayAddAnAppointmentToALiveEventInTheEventForm(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([
            self::TABLE_EVENT => [self::OWN_LIVE => ['appointments' => self::OWN_LIVE_APPOINTMENT . ',' . $appointmentId]],
            self::TABLE_ENTRY => [
                self::OWN_LIVE_APPOINTMENT => ['title' => 'Edited inline'],
                $appointmentId => ['pid' => 2, 'record_type' => 'event-appointment', 'calendar' => 1, 'start_date' => 1767225600],
            ],
        ]);

        self::assertSame([], $dataHandler->errorLog);
        self::assertSame(self::OWN_LIVE, $this->appointmentField((int)$dataHandler->substNEWwithIDs[$appointmentId], 'event'));
        self::assertSame('Edited inline', $this->appointmentField(self::OWN_LIVE_APPOINTMENT, 'title'));
        self::assertSame('Own live', $this->eventField(self::OWN_LIVE, 'title'));
    }

    /**
     * The read-only form resubmits the stored values of every field it renders as hidden input.
     */
    #[Test]
    public function anEditorMaySaveTheReadOnlyFormOfALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([
            self::TABLE_EVENT => [self::OWN_LIVE => [
                'title' => 'Own live',
                'categories' => '',
                'files' => '',
                'appointments' => self::OWN_LIVE_APPOINTMENT . ',' . $appointmentId,
            ]],
            self::TABLE_ENTRY => [
                $appointmentId => ['pid' => 2, 'record_type' => 'event-appointment', 'calendar' => 1, 'start_date' => 1767225600],
            ],
        ]);

        self::assertSame([], $dataHandler->errorLog);
        self::assertSame(self::OWN_LIVE, $this->appointmentField((int)$dataHandler->substNEWwithIDs[$appointmentId], 'event'));
    }

    #[Test]
    public function anEditorSavingALiveEventFormKeepsTheAppointmentsButNotOtherChanges(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([
            self::TABLE_EVENT => [self::OWN_LIVE => [
                'title' => 'Sneaked in',
                'hidden' => 1,
                'appointments' => self::OWN_LIVE_APPOINTMENT . ',' . $appointmentId,
            ]],
            self::TABLE_ENTRY => [
                $appointmentId => ['pid' => 2, 'record_type' => 'event-appointment', 'calendar' => 1, 'start_date' => 1767225600],
            ],
        ]);

        self::assertSame('Own live', $this->eventField(self::OWN_LIVE, 'title'));
        self::assertSame(0, $this->eventField(self::OWN_LIVE, 'hidden'));
        self::assertSame(self::OWN_LIVE, $this->appointmentField((int)$dataHandler->substNEWwithIDs[$appointmentId], 'event'));
        self::assertRefusalLogged($dataHandler);
    }

    #[Test]
    public function aPublisherMayPublishEditAndDeleteLiveEvents(): void
    {
        $this->setUpBackendUser(self::PUBLISHER);

        $this->process([self::TABLE_EVENT => [
            self::OWN_DRAFT => ['status' => EventStatus::LIVE->value],
            self::FOREIGN_LIVE => ['title' => 'Edited by a publisher', 'owner_be_user' => self::PUBLISHER],
            self::FOREIGN_DRAFT => ['status' => EventStatus::REJECTED->value],
        ]]);
        $this->process([], [self::TABLE_EVENT => [self::OWN_LIVE => ['delete' => 1]]]);

        self::assertSame(EventStatus::LIVE->value, $this->eventField(self::OWN_DRAFT, 'status'));
        self::assertSame('Edited by a publisher', $this->eventField(self::FOREIGN_LIVE, 'title'));
        self::assertSame(self::PUBLISHER, $this->eventField(self::FOREIGN_LIVE, 'owner_be_user'));
        self::assertSame(EventStatus::REJECTED->value, $this->eventField(self::FOREIGN_DRAFT, 'status'));
        self::assertSame(1, $this->eventField(self::OWN_LIVE, 'deleted'));
    }

    #[Test]
    public function anAdministratorMayCreateALiveEventForSomebodyElse(): void
    {
        $this->setUpBackendUser(self::ADMIN);
        $eventId = StringUtility::getUniqueId('NEW');

        $dataHandler = $this->process([self::TABLE_EVENT => [$eventId => [
            'pid' => 2,
            'record_type' => 'event',
            'title' => 'Published by an admin',
            'status' => EventStatus::LIVE->value,
            'owner_be_user' => self::COLLEAGUE,
        ]]]);

        $uid = (int)$dataHandler->substNEWwithIDs[$eventId];
        self::assertSame(EventStatus::LIVE->value, $this->eventField($uid, 'status'));
        self::assertSame(self::COLLEAGUE, $this->eventField($uid, 'owner_be_user'));
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @param array<string, array<int|string, array<string, mixed>>> $cmdMap
     */
    private function process(array $dataMap, array $cmdMap = []): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, $cmdMap, $GLOBALS['BE_USER']);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        return $dataHandler;
    }

    private static function assertRefusalLogged(DataHandler $dataHandler): void
    {
        self::assertNotSame([], array_filter(
            $dataHandler->errorLog,
            static fn (string $message): bool => str_contains($message, 'refused by the event workflow permissions')
        ), implode("\n", $dataHandler->errorLog));
    }

    private function eventField(int $uid, string $field): int|string|null
    {
        return $this->normalize($this->fetchRawRecord(self::TABLE_EVENT, $uid)[$field] ?? null);
    }

    private function appointmentField(int $uid, string $field): int|string|null
    {
        return $this->normalize($this->fetchRawRecord(self::TABLE_ENTRY, $uid)[$field] ?? null);
    }

    private function normalize(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) && !is_numeric($value) || $value === null ? $value : (int)$value;
    }

    private function countEventsTitled(string $title): int
    {
        return $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->count('uid', self::TABLE_EVENT, ['title' => $title]);
    }
}

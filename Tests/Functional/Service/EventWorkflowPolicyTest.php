<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * The transition and content tables of the workflow, evaluated for the real backend users of
 * Fixtures/workflow-permissions.csv (the permissions come from their groups).
 */
final class EventWorkflowPolicyTest extends AbstractCalendarFunctionalTestCase
{
    private const ADMIN = 1;
    private const EDITOR = 2;
    private const PUBLISHER = 3;
    private const COLLEAGUE = 4;

    private const EDITOR_TRANSITIONS = [
        [EventStatus::DRAFT, EventStatus::REVIEW],
        [EventStatus::REVIEW, EventStatus::DRAFT],
        [EventStatus::REJECTED, EventStatus::DRAFT],
        [EventStatus::REJECTED, EventStatus::REVIEW],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/workflow-permissions.csv');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_calendar']['restrictions']['unrestrictedRecordTypes'] = '';
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: EventStatus, 1: EventStatus}>
     */
    public static function transitionProvider(): array
    {
        $cases = [];
        foreach (EventStatus::cases() as $from) {
            foreach (EventStatus::cases() as $to) {
                if ($from !== $to) {
                    $cases[$from->name . ' to ' . $to->name] = [$from, $to];
                }
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('transitionProvider')]
    public function anEditorMovesTheirOwnEventOnlyBetweenDraftAndReview(EventStatus $from, EventStatus $to): void
    {
        $editor = $this->setUpBackendUser(self::EDITOR);

        self::assertSame(
            in_array([$from, $to], self::EDITOR_TRANSITIONS, true),
            $this->policy()->canTransition($editor, $this->event($from, self::EDITOR), $from, $to)
        );
    }

    #[Test]
    #[DataProvider('transitionProvider')]
    public function anEditorCannotMoveAForeignEvent(EventStatus $from, EventStatus $to): void
    {
        $editor = $this->setUpBackendUser(self::EDITOR);

        self::assertFalse($this->policy()->canTransition($editor, $this->event($from, self::COLLEAGUE), $from, $to));
    }

    #[Test]
    #[DataProvider('transitionProvider')]
    public function publishersAndAdministratorsMayMoveEveryEvent(EventStatus $from, EventStatus $to): void
    {
        foreach ([self::PUBLISHER, self::ADMIN] as $uid) {
            $user = $this->setUpBackendUser($uid);

            self::assertTrue($this->policy()->canTransition($user, $this->event($from, self::COLLEAGUE), $from, $to));
        }
    }

    #[Test]
    public function aNewEventBelongsToTheCreatingEditor(): void
    {
        $editor = $this->setUpBackendUser(self::EDITOR);
        $newEvent = ['uid' => 'NEW1', 'owner_be_user' => self::COLLEAGUE];

        self::assertSame([EventStatus::REVIEW], $this->policy()->allowedTargetStatuses($editor, $newEvent));
    }

    #[Test]
    public function offersAnEditorNoTargetForALiveEvent(): void
    {
        $editor = $this->setUpBackendUser(self::EDITOR);

        self::assertSame([], $this->policy()->allowedTargetStatuses($editor, $this->event(EventStatus::LIVE, self::EDITOR)));
    }

    #[Test]
    public function offersAPublisherEveryOtherStatus(): void
    {
        $publisher = $this->setUpBackendUser(self::PUBLISHER);

        self::assertSame(
            [EventStatus::DRAFT, EventStatus::LIVE, EventStatus::REJECTED],
            $this->policy()->allowedTargetStatuses($publisher, $this->event(EventStatus::REVIEW, self::COLLEAGUE))
        );
    }

    /**
     * @return array<string, array{0: EventStatus, 1: int, 2: bool, 3: bool}>
     */
    public static function eventContentProvider(): array
    {
        return [
            'own draft' => [EventStatus::DRAFT, self::EDITOR, true, true],
            'own review' => [EventStatus::REVIEW, self::EDITOR, true, true],
            'own live' => [EventStatus::LIVE, self::EDITOR, false, true],
            'foreign draft' => [EventStatus::DRAFT, self::COLLEAGUE, false, false],
            'foreign rejected' => [EventStatus::REJECTED, self::COLLEAGUE, false, false],
            'foreign live' => [EventStatus::LIVE, self::COLLEAGUE, false, true],
        ];
    }

    #[Test]
    #[DataProvider('eventContentProvider')]
    public function anEditorEditsVisibleNonLiveEventsAndManagesAppointmentsOfVisibleEvents(
        EventStatus $status,
        int $owner,
        bool $canEdit,
        bool $canManageAppointments,
    ): void {
        $editor = $this->setUpBackendUser(self::EDITOR);
        $event = $this->event($status, $owner);

        self::assertSame($canEdit, $this->policy()->canEditEvent($editor, $event));
        self::assertSame($canManageAppointments, $this->policy()->canManageAppointmentsOf($editor, $event));
        self::assertSame($canManageAppointments, $this->policy()->canCopyEvent($editor, $event));
    }

    #[Test]
    public function anEditorWithViewAllEventsEditsForeignDrafts(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update(
            'be_groups',
            ['custom_options' => 'tx_ximatypo3calendar_permissions:view_all_events'],
            ['uid' => 11]
        );
        $editor = $this->setUpBackendUser(self::EDITOR);
        $foreignDraft = $this->event(EventStatus::DRAFT, self::COLLEAGUE);

        self::assertTrue($this->policy()->canEditEvent($editor, $foreignDraft));
        self::assertFalse($this->policy()->canTransition($editor, $foreignDraft, EventStatus::DRAFT, EventStatus::REVIEW));
    }

    #[Test]
    public function anEditorEditsForeignEventsOfAnUnrestrictedRecordType(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_calendar']['restrictions']['unrestrictedRecordTypes'] = 'holiday';
        $editor = $this->setUpBackendUser(self::EDITOR);

        self::assertTrue($this->policy()->canEditEvent($editor, ['record_type' => 'holiday'] + $this->event(EventStatus::DRAFT, self::COLLEAGUE)));
        self::assertFalse($this->policy()->canEditEvent($editor, ['record_type' => 'event'] + $this->event(EventStatus::DRAFT, self::COLLEAGUE)));
    }

    #[Test]
    public function onlyPublishersAssignOwners(): void
    {
        self::assertFalse($this->policy()->canAssignOwner($this->setUpBackendUser(self::EDITOR)));
        self::assertTrue($this->policy()->canAssignOwner($this->setUpBackendUser(self::PUBLISHER)));
        self::assertTrue($this->policy()->canAssignOwner($this->setUpBackendUser(self::ADMIN)));
    }

    #[Test]
    public function readsTheStatusOfFormEngineRowsAndFallsBackToDraft(): void
    {
        self::assertSame(EventStatus::LIVE, $this->policy()->statusOf(['status' => ['2']]));
        self::assertSame(EventStatus::DRAFT, $this->policy()->statusOf([]));
        self::assertSame(EventStatus::DRAFT, $this->policy()->statusOf(['status' => 99]));
    }

    private function policy(): EventWorkflowPolicy
    {
        return $this->get(EventWorkflowPolicy::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(EventStatus $status, int $owner): array
    {
        return ['uid' => 100, 'record_type' => 'event', 'status' => $status->value, 'owner_be_user' => $owner];
    }
}

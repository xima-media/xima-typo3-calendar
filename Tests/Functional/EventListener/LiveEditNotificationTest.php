<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;
use Xima\XimaTypo3Calendar\Tests\Functional\Fixtures\RecordingMailListener;

/**
 * Content and appointment changes on live events reach the live-edit subscribers, one mail per
 * event and save. Appointments are changed by editors without the publish permission too. Runs
 * as the users of Fixtures/workflow-permissions.csv, whose `live-subscriber` subscribed to live
 * changes.
 */
final class LiveEditNotificationTest extends AbstractCalendarFunctionalTestCase
{
    private const EDITOR = 2;
    private const PUBLISHER = 3;

    private const OWN_LIVE = 11;
    private const OWN_DRAFT = 12;
    private const FOREIGN_LIVE = 17;
    private const OWN_LIVE_APPOINTMENT = 11;
    private const FOREIGN_LIVE_APPOINTMENT = 12;
    private const OWN_DRAFT_APPOINTMENT = 13;

    private RecordingMailListener $mails;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/workflow-permissions.csv');

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/typo3/', 'POST'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams(['HTTP_HOST' => 'example.com', 'HTTPS' => 'on']));
        $this->mails = $this->get(RecordingMailListener::class);
        $this->mails->reset();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['BE_USER']);
        parent::tearDown();
    }

    #[Test]
    public function notifiesWhenAnEditorChangesAnAppointmentOfALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_ENTRY => [self::FOREIGN_LIVE_APPOINTMENT => ['start_date' => 1767268800]]]);

        self::assertSame([['template' => 'EventLiveNotification', 'to' => ['live@example.com']]], $this->mails->all());
    }

    #[Test]
    public function notifiesWhenAnEditorAddsAnAppointmentToALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $this->process([self::TABLE_ENTRY => [$appointmentId => [
            'pid' => 2,
            'record_type' => 'event-appointment',
            'event' => self::FOREIGN_LIVE,
            'calendar' => 1,
            'start_date' => 1767225600,
        ]]]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    #[Test]
    public function notifiesWhenAnEditorDeletesAnAppointmentOfALiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([], [self::TABLE_ENTRY => [self::OWN_LIVE_APPOINTMENT => ['delete' => 1]]]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    #[Test]
    public function notifiesOncePerSaveOfALiveEventForm(): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');

        $this->process([
            self::TABLE_EVENT => [self::OWN_LIVE => ['appointments' => self::OWN_LIVE_APPOINTMENT . ',' . $appointmentId]],
            self::TABLE_ENTRY => [
                self::OWN_LIVE_APPOINTMENT => ['title' => 'Edited inline'],
                $appointmentId => ['pid' => 2, 'record_type' => 'event-appointment', 'calendar' => 1, 'start_date' => 1767225600],
            ],
        ]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    /**
     * The form sends a new inline appointment without `event`; DataHandler sets it only when
     * it remaps the parent relation, after the appointment was created.
     */
    #[Test]
    #[DataProvider('inlineAdditionOrders')]
    public function notifiesWhenTheLiveEventFormOnlyAddsAnInlineAppointment(bool $parentFirst): void
    {
        $this->setUpBackendUser(self::EDITOR);
        $appointmentId = StringUtility::getUniqueId('NEW');
        $parent = [self::TABLE_EVENT => [self::OWN_LIVE => ['appointments' => self::OWN_LIVE_APPOINTMENT . ',' . $appointmentId]]];
        $child = [self::TABLE_ENTRY => [$appointmentId => ['pid' => 2, 'record_type' => 'event-appointment', 'calendar' => 1, 'start_date' => 1767225600]]];

        $this->process($parentFirst ? $parent + $child : $child + $parent);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function inlineAdditionOrders(): array
    {
        return ['parent first' => [true], 'appointment first' => [false]];
    }

    #[Test]
    public function notifiesOnceWhenOneSaveEditsAndDeletesAppointmentsOfALiveEvent(): void
    {
        $this->insertSecondOwnLiveAppointment();
        $this->setUpBackendUser(self::EDITOR);

        $this->process(
            [self::TABLE_ENTRY => [self::OWN_LIVE_APPOINTMENT => ['title' => 'Edited inline']]],
            [self::TABLE_ENTRY => [15 => ['delete' => 1]]],
        );

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    #[Test]
    public function notifiesTheLiveEventAnAppointmentIsMovedAwayFrom(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_ENTRY => [self::OWN_LIVE_APPOINTMENT => ['event' => self::OWN_DRAFT]]]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    #[Test]
    public function notifiesOnceWhenOneSaveChangesTheContentAndAnAppointmentOfALiveEvent(): void
    {
        $this->setUpBackendUser(self::PUBLISHER);

        $this->process([
            self::TABLE_EVENT => [self::OWN_LIVE => ['title' => 'Renamed live event']],
            self::TABLE_ENTRY => [self::OWN_LIVE_APPOINTMENT => ['title' => 'Edited inline']],
        ]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    #[Test]
    public function notifiesWhenAPublisherChangesTheContentOfALiveEvent(): void
    {
        $this->setUpBackendUser(self::PUBLISHER);

        $this->process([self::TABLE_EVENT => [self::OWN_LIVE => ['title' => 'Renamed live event']]]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    /**
     * DataHandler copies each record through a nested instance, which must not send on its own.
     */
    #[Test]
    public function notifiesOnceWhenOneCommandCopiesSeveralAppointmentsOfALiveEvent(): void
    {
        $this->insertSecondOwnLiveAppointment();
        $this->setUpBackendUser(self::PUBLISHER);

        $this->process([], [self::TABLE_ENTRY => [
            self::OWN_LIVE_APPOINTMENT => ['copy' => 2],
            15 => ['copy' => 2],
        ]]);

        self::assertSame(['EventLiveNotification'], $this->templates());
    }

    #[Test]
    public function notifiesWhenOnlyTheAppointmentRelationOfALiveEventChanges(): void
    {
        $this->setUpBackendUser(self::PUBLISHER);

        $this->process([self::TABLE_EVENT => [self::OWN_LIVE => [
            'appointments' => self::OWN_LIVE_APPOINTMENT . ',' . self::OWN_DRAFT_APPOINTMENT,
        ]]]);

        self::assertContains('EventLiveNotification', $this->templates());
    }

    #[Test]
    public function staysQuietForAppointmentsOfANonLiveEvent(): void
    {
        $this->setUpBackendUser(self::EDITOR);

        $this->process([self::TABLE_ENTRY => [self::OWN_DRAFT_APPOINTMENT => ['start_date' => 1767268800]]]);

        self::assertSame([], $this->mails->all());
    }

    /**
     * Deleting the event removes its appointments first; that is no change to a live event.
     */
    #[Test]
    public function staysQuietWhenALiveEventIsDeleted(): void
    {
        $this->setUpBackendUser(self::PUBLISHER);

        $this->process([], [self::TABLE_EVENT => [self::OWN_LIVE => ['delete' => 1]]]);

        self::assertSame([], $this->mails->all());
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @param array<string, array<int|string, array<string, mixed>>> $cmdMap
     */
    private function process(array $dataMap, array $cmdMap = []): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, $cmdMap, $GLOBALS['BE_USER']);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        self::assertSame([], $dataHandler->errorLog);
    }

    private function insertSecondOwnLiveAppointment(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)->insert(self::TABLE_ENTRY, [
            'uid' => 15,
            'pid' => 2,
            'record_type' => 'event-appointment',
            'event' => self::OWN_LIVE,
            'calendar' => 1,
            'title' => 'Second own live appointment',
            'start_date' => 1767312000,
            'end_date' => 1767315600,
        ]);
    }

    /**
     * @return list<string>
     */
    private function templates(): array
    {
        return array_column($this->mails->all(), 'template');
    }
}

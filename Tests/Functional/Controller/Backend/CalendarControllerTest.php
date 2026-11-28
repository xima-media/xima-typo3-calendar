<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Controller\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Xima\XimaTypo3Calendar\Controller\Backend\CalendarEventCreationController;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\EventRecordTypeService;
use Xima\XimaTypo3Calendar\Service\EventSearchService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;
use Xima\XimaTypo3Calendar\Tests\Functional\Fixtures\RecordingEventListener;

/**
 * Runs as real backend users from Fixtures/backend-permissions.csv, so the
 * permission check sees the same table, page and mount rights as DataHandler.
 */
final class CalendarControllerTest extends AbstractCalendarFunctionalTestCase
{
    private const ADMIN = 1;
    private const EDITOR = 2;
    private const EDITOR_WITHOUT_TABLES = 3;
    private const EDITOR_WITHOUT_PAGE_ACCESS = 4;
    private const EDITOR_WITHOUT_MOUNT = 5;
    private const APPOINTMENT_EDITOR = 6;
    private const SECOND_SITE_EDITOR = 7;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/backend-permissions.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/second-site-calendar.csv');
    }

    #[Test]
    public function rejectsANonArrayPayload(): void
    {
        $this->actAs(self::ADMIN);

        $response = $this->controller()->newRecordAction(
            (new ServerRequest('https://example.com/typo3/'))->withParsedBody(new \stdClass())
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid event data.'], $this->decode($response));
    }

    #[Test]
    public function rejectsAnUnknownCreationType(): void
    {
        $this->actAs(self::ADMIN);

        $response = $this->newRecord(['type' => 'unknown']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid creation type.'], $this->decode($response));
    }

    #[Test]
    public function opensANewAppointmentFormPrefilledWithTheSelection(): void
    {
        $this->actAs(self::EDITOR);

        $query = $this->formQuery($this->newRecord(['type' => 'event-appointment', 'allDay' => 1]));

        self::assertSame([self::TABLE_ENTRY => [2 => 'new']], $query['edit'] ?? null);
        self::assertSame(
            [
                self::TABLE_ENTRY => [
                    'record_type' => 'event-appointment',
                    'start_date' => '1767225600',
                    'end_date' => '1767229200',
                    'all_day' => '1',
                    'calendar' => '1',
                ],
            ],
            $query['defVals'] ?? null,
        );
        self::assertStringContainsString('/module/calendar/calendar', (string)($query['returnUrl'] ?? ''));
    }

    #[Test]
    public function opensANewEventFormWithTheSelectionAsAppointmentDefaults(): void
    {
        $this->actAs(self::EDITOR);

        $query = $this->formQuery($this->newRecord(['type' => 'event', 'recordType' => 'plain-event']));

        self::assertSame([self::TABLE_EVENT => [2 => 'new']], $query['edit'] ?? null);
        self::assertSame(['record_type' => 'plain-event'], $query['defVals'][self::TABLE_EVENT] ?? null);
        self::assertSame(
            [
                'record_type' => 'event-appointment',
                'start_date' => '1767225600',
                'end_date' => '1767229200',
                'all_day' => '0',
                'calendar' => '1',
            ],
            $query['defVals'][self::TABLE_ENTRY] ?? null,
        );
    }

    #[Test]
    public function writesNoRecordAndDispatchesNoChangeEventBeforeTheFormIsSaved(): void
    {
        $this->actAs(self::ADMIN);
        $listener = $this->get(RecordingEventListener::class);
        $listener->reset();
        $eventsBefore = $this->countRows(self::TABLE_EVENT);
        $entriesBefore = $this->countRows(self::TABLE_ENTRY);

        foreach (['event', 'event-appointment'] as $type) {
            self::assertSame(200, $this->newRecord(['type' => $type])->getStatusCode());
        }

        self::assertSame($eventsBefore, $this->countRows(self::TABLE_EVENT));
        self::assertSame($entriesBefore, $this->countRows(self::TABLE_ENTRY));
        self::assertSame([], $listener->all());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function allowedCreationDataProvider(): iterable
    {
        yield 'admin, event' => [self::ADMIN, 'event'];
        yield 'admin, appointment' => [self::ADMIN, 'event-appointment'];
        yield 'editor with table and page permission, event' => [self::EDITOR, 'event'];
        yield 'editor with table and page permission, appointment' => [self::EDITOR, 'event-appointment'];
        yield 'appointment editor, appointment' => [self::APPOINTMENT_EDITOR, 'event-appointment'];
    }

    #[Test]
    #[DataProvider('allowedCreationDataProvider')]
    public function opensTheFormWhenTheUserMayEditTheStoragePage(int $backendUserUid, string $type): void
    {
        $this->actAs($backendUserUid);

        $response = $this->newRecord(['type' => $type]);

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertTrue($this->decode($response)['success'] ?? false);
    }

    /**
     * @return iterable<string, array{int, string, int, string}>
     */
    public static function refusedCreationDataProvider(): iterable
    {
        $noCalendar = 'You cannot create records in this calendar.';
        $noPermission = 'No permission to create events.';
        yield 'editor without tables_modify, event' => [self::EDITOR_WITHOUT_TABLES, 'event', 1, $noCalendar];
        yield 'editor without tables_modify, appointment' => [self::EDITOR_WITHOUT_TABLES, 'event-appointment', 1, $noCalendar];
        yield 'editor without content-edit permission, event' => [self::EDITOR_WITHOUT_PAGE_ACCESS, 'event', 1, $noCalendar];
        yield 'editor without content-edit permission, appointment' => [self::EDITOR_WITHOUT_PAGE_ACCESS, 'event-appointment', 1, $noCalendar];
        yield 'editor without web mount, appointment' => [self::EDITOR_WITHOUT_MOUNT, 'event-appointment', 1, $noCalendar];
        yield 'editor, calendar of another site' => [self::EDITOR, 'event-appointment', 2, $noCalendar];
        yield 'editor, unknown calendar' => [self::EDITOR, 'event-appointment', 999, $noCalendar];
        yield 'editor, no calendar' => [self::EDITOR, 'event-appointment', 0, $noCalendar];
        yield 'appointment editor, event' => [self::APPOINTMENT_EDITOR, 'event', 1, $noPermission];
    }

    #[Test]
    #[DataProvider('refusedCreationDataProvider')]
    public function refusesCreationInCalendarsTheUserCannotWriteTo(
        int $backendUserUid,
        string $type,
        int $calendarUid,
        string $message,
    ): void {
        $this->actAs($backendUserUid);

        $response = $this->newRecord(['type' => $type, 'calendarUid' => $calendarUid]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => $message], $this->decode($response));
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function translatedRefusalDataProvider(): iterable
    {
        yield 'calendar not available' => [self::EDITOR_WITHOUT_TABLES, 'event-appointment', 'In diesem Kalender können Sie keine Datensätze anlegen.'];
        yield 'no event permission' => [self::APPOINTMENT_EDITOR, 'event', 'Keine Berechtigung, Events anzulegen.'];
    }

    #[Test]
    #[DataProvider('translatedRefusalDataProvider')]
    public function translatesTheRefusalMessage(int $backendUserUid, string $type, string $message): void
    {
        $this->actAs($backendUserUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('de');

        $response = $this->newRecord(['type' => $type]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame($message, $this->decode($response)['message'] ?? null);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function storagePageDataProvider(): iterable
    {
        yield 'admin, first site' => [self::ADMIN, 1, 2];
        yield 'admin, second site' => [self::ADMIN, 2, 11];
        yield 'second site editor, calendar on a hidden sysfolder' => [self::SECOND_SITE_EDITOR, 2, 11];
    }

    #[Test]
    #[DataProvider('storagePageDataProvider')]
    public function storesNewRecordsOnThePageOfTheSelectedCalendar(int $backendUserUid, int $calendarUid, int $expectedPid): void
    {
        $this->actAs($backendUserUid);

        foreach ([self::TABLE_EVENT => 'event', self::TABLE_ENTRY => 'event-appointment'] as $table => $type) {
            $query = $this->formQuery($this->newRecord(['type' => $type, 'calendarUid' => $calendarUid]));

            self::assertSame([$table => [$expectedPid => 'new']], $query['edit'] ?? null);
            self::assertSame((string)$calendarUid, $query['defVals'][self::TABLE_ENTRY]['calendar'] ?? null);
        }
    }

    #[Test]
    public function refusesARecordTypeRemovedByThePageTsConfigOfTheCalendarPage(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->update(
            'pages',
            ['TSconfig' => 'TCEFORM.tx_ximatypo3calendar_domain_model_event.record_type.removeItems = plain-event'],
            ['uid' => 11],
        );
        $this->actAs(self::ADMIN);

        $refused = $this->newRecord(['type' => 'event', 'recordType' => 'plain-event', 'calendarUid' => 2]);
        $allowedElsewhere = $this->newRecord(['type' => 'event', 'recordType' => 'plain-event', 'calendarUid' => 1]);

        self::assertSame(400, $refused->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid record type.'], $this->decode($refused));
        self::assertSame(200, $allowedElsewhere->getStatusCode());
    }

    #[Test]
    public function refusesARecordTypeDeniedByAuthMode(): void
    {
        $GLOBALS['TCA'][self::TABLE_EVENT]['columns']['record_type']['config']['authMode'] = 'explicitAllow';
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')->update(
            'be_groups',
            ['explicit_allowdeny' => self::TABLE_EVENT . ':record_type:event'],
            ['uid' => 2],
        );
        $this->actAs(self::EDITOR);

        self::assertSame(400, $this->newRecord(['type' => 'event', 'recordType' => 'plain-event'])->getStatusCode());
        self::assertSame(200, $this->newRecord(['type' => 'event', 'recordType' => 'event'])->getStatusCode());
    }

    #[Test]
    public function leavesTheRecordTypeToFormEngineWhenNoneIsChosen(): void
    {
        $this->actAs(self::EDITOR);

        $query = $this->formQuery($this->newRecord(['type' => 'event']));

        self::assertArrayNotHasKey(self::TABLE_EVENT, $query['defVals'] ?? []);
    }

    #[Test]
    public function prefillsTheEventChosenForANewAppointment(): void
    {
        $this->actAs(self::EDITOR);

        $query = $this->formQuery($this->newRecord(['type' => 'event-appointment', 'eventUid' => 1]));

        self::assertSame('1', $query['defVals'][self::TABLE_ENTRY]['event'] ?? null);
    }

    #[Test]
    public function ignoresAChosenEventForANewEvent(): void
    {
        $this->actAs(self::EDITOR);

        $query = $this->formQuery($this->newRecord(['type' => 'event', 'eventUid' => 1]));

        self::assertArrayNotHasKey('event', $query['defVals'][self::TABLE_ENTRY] ?? []);
    }

    #[Test]
    public function refusesAnEventTheUserCannotSee(): void
    {
        // EventRestriction only applies to backend requests.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->actAs(self::EDITOR);

        $response = $this->newRecord(['type' => 'event-appointment', 'eventUid' => 2]);
        unset($GLOBALS['TYPO3_REQUEST']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid event.'], $this->decode($response));
    }

    #[Test]
    public function remembersTheCalendarOfTheLastCreation(): void
    {
        $this->actAs(self::ADMIN);
        $selection = $this->get(CalendarSelectionService::class);

        $this->newRecord(['calendarUid' => 2]);

        self::assertSame(2, $selection->getLastUsedCalendarUid($selection->getAvailableCalendars()));
    }

    private function actAs(int $backendUserUid): void
    {
        $backendUser = $this->setUpBackendUser($backendUserUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function newRecord(array $overrides = []): ResponseInterface
    {
        $payload = array_replace([
            'start' => 1767225600,
            'end' => 1767229200,
            'allDay' => 0,
            'type' => 'event-appointment',
            'calendarUid' => 1,
        ], $overrides);

        return $this->controller()->newRecordAction(
            (new ServerRequest('https://example.com/typo3/'))->withParsedBody($payload)
        );
    }

    /**
     * @return array<string, mixed> the query of the returned record_edit URL
     */
    private function formQuery(ResponseInterface $response): array
    {
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $url = (string)($this->decode($response)['url'] ?? '');
        self::assertStringContainsString('/record/edit', $url);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function countRows(string $table): int
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable($table)->count('*', $table, []);
    }

    private function controller(): CalendarEventCreationController
    {
        return new CalendarEventCreationController(
            $this->get(CalendarSelectionService::class),
            new EventRecordTypeService($this->get(FormDataCompiler::class), $this->get(ConnectionPool::class)),
            $this->get(EventSearchService::class),
            $this->get(UriBuilder::class),
        );
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        return (array)json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}

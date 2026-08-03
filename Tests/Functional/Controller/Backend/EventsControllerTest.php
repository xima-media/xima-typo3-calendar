<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Controller\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3Calendar\Controller\Backend\EventsController;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\StatusChangeContext;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Covers the status modal endpoint. It is the only write surface of the backend
 * module and carries the publish permission check, so it is exercised end to end
 * through the real DataHandler.
 *
 * The controller is instantiated directly rather than pulled from the container:
 * updateStatus() only uses the three constructor dependencies, none of the
 * setter-injected ones AbstractBackendController declares for rendering.
 */
final class EventsControllerTest extends AbstractCalendarFunctionalTestCase
{
    private StatusChangeContext $statusChangeContext;

    /** @var array<string, mixed>|null */
    private ?array $extensionConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar.csv');

        $this->statusChangeContext = new StatusChangeContext();
        $this->extensionConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->extensionConfigurationBackup !== null) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'] = $this->extensionConfigurationBackup;
        }
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'missing uid'          => [['status' => EventStatus::REVIEW->value]],
            'uid zero'             => [['uid' => 0, 'status' => EventStatus::REVIEW->value]],
            'negative uid'         => [['uid' => -5, 'status' => EventStatus::REVIEW->value]],
            'non numeric uid'      => [['uid' => 'abc', 'status' => EventStatus::REVIEW->value]],
            'missing status'       => [['uid' => 2]],
            'unknown status'       => [['uid' => 2, 'status' => 99]],
            'negative status'      => [['uid' => 2, 'status' => -1]],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function rejectsAnInvalidPayloadWithBadRequest(array $payload): void
    {
        $this->setUpBackendUser(1);

        $response = $this->updateStatus($payload);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false], $this->decode($response));
    }

    /**
     * A rejected payload must not reach the DataHandler.
     */
    #[Test]
    public function leavesTheRecordUntouchedOnAnInvalidPayload(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus(['uid' => 2, 'status' => 99]);

        self::assertSame(EventStatus::DRAFT->value, $this->currentStatus(2));
    }

    /**
     * Publishing requires the dedicated permission — without it the endpoint has
     * to refuse, otherwise the status modal would be a way around the TCA
     * itemsProcFunc that hides the LIVE option in the edit form.
     */
    #[Test]
    public function refusesToPublishWithoutThePublishPermission(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUserWithoutPublishPermission();

        $response = $this->updateStatus(['uid' => 2, 'status' => EventStatus::LIVE->value]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['success' => false], $this->decode($response));
    }

    #[Test]
    public function doesNotPersistTheStatusWhenPublishingIsRefused(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUserWithoutPublishPermission();

        $this->updateStatus(['uid' => 2, 'status' => EventStatus::LIVE->value]);

        self::assertSame(EventStatus::DRAFT->value, $this->currentStatus(2));
    }

    /**
     * The permission gate is scoped to publishing: every other transition stays
     * open to a user without it.
     */
    #[Test]
    public function allowsANonPublishingTransitionWithoutThePublishPermission(): void
    {
        $this->setUpBackendUser(1);

        $response = $this->updateStatus(['uid' => 2, 'status' => EventStatus::REVIEW->value]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function persistsTheNewStatus(): void
    {
        $this->setUpBackendUser(1);

        $response = $this->updateStatus(['uid' => 2, 'status' => EventStatus::REVIEW->value]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], $this->decode($response));
        self::assertSame(EventStatus::REVIEW->value, $this->currentStatus(2));
    }

    #[Test]
    public function persistsTheStatusMessage(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus([
            'uid' => 2,
            'status' => EventStatus::REVIEW->value,
            'message' => 'Please have a look',
        ]);

        self::assertSame('Please have a look', $this->currentStatusMessage(2));
    }

    #[Test]
    public function trimsTheStatusMessage(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus([
            'uid' => 2,
            'status' => EventStatus::REVIEW->value,
            'message' => "  Please have a look \n",
        ]);

        self::assertSame('Please have a look', $this->currentStatusMessage(2));
    }

    #[Test]
    public function anAdministratorMayPublish(): void
    {
        $this->setUpBackendUser(1);

        $response = $this->updateStatus(['uid' => 2, 'status' => EventStatus::LIVE->value]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(EventStatus::LIVE->value, $this->currentStatus(2));
    }

    /**
     * The notify flag travels to the workflow listener through the singleton
     * context rather than through the DataHandler datamap.
     */
    #[Test]
    public function forwardsTheOwnerNotificationOptOut(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus([
            'uid' => 2,
            'status' => EventStatus::REVIEW->value,
            'notifyOwner' => 0,
        ]);

        self::assertFalse($this->statusChangeContext->shouldNotifyOwner());
    }

    #[Test]
    public function keepsOwnerNotificationEnabledByDefault(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus(['uid' => 2, 'status' => EventStatus::REVIEW->value]);

        self::assertTrue($this->statusChangeContext->shouldNotifyOwner());
    }

    #[Test]
    public function marksTheChangeAsBackendInitiated(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus(['uid' => 2, 'status' => EventStatus::REVIEW->value]);

        self::assertTrue($this->statusChangeContext->isFromBackend());
    }

    #[Test]
    public function recordsTheActingBackendUser(): void
    {
        $this->setUpBackendUser(1);

        $this->updateStatus(['uid' => 2, 'status' => EventStatus::REVIEW->value]);

        self::assertSame(
            ['uid' => 1, 'name' => 'Ada Admin', 'email' => 'admin@example.com'],
            $this->statusChangeContext->getBackendUser()
        );
    }

    /**
     * The username stands in when the backend user has no real name set.
     */
    #[Test]
    public function fallsBackToTheUsernameForTheActingBackendUser(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_users')
            ->update('be_users', ['realName' => ''], ['uid' => 1]);
        $this->setUpBackendUser(1);

        $this->updateStatus(['uid' => 2, 'status' => EventStatus::REVIEW->value]);

        self::assertSame('admin', $this->statusChangeContext->getBackendUserName());
    }

    #[Test]
    public function recordsNoActingUserWithoutABackendUser(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUserWithoutPublishPermission();
        // Refused before the DataHandler runs, so no backend user is needed here.
        $this->updateStatus(['uid' => 2, 'status' => EventStatus::LIVE->value]);

        self::assertNull($this->statusChangeContext->getBackendUser());
    }

    #[Test]
    public function exposesTheCalendarTablesForTheBackendModule(): void
    {
        $tableNames = $this->controller()->getTableNames();

        self::assertContains(self::TABLE_EVENT, $tableNames);
        self::assertContains(self::TABLE_ENTRY, $tableNames);
        self::assertContains(self::TABLE_CALENDAR, $tableNames);
        self::assertContains(self::TABLE_LOCATION, $tableNames);
        self::assertContains('tx_ximatypo3calendar_domain_model_organizer', $tableNames);
        self::assertContains('tx_ximatypo3calendar_domain_model_speaker', $tableNames);
    }

    /**
     * Requirements management is opt-in, so its table must stay out of the module
     * until the feature is switched on.
     */
    #[Test]
    public function hidesTheRequirementTableWhileTheFeatureIsDisabled(): void
    {
        $this->givenRequirementsManagement(false);

        self::assertNotContains(self::TABLE_REQUIREMENT, $this->controller()->getTableNames());
    }

    #[Test]
    public function exposesTheRequirementTableOnceTheFeatureIsEnabled(): void
    {
        $this->givenRequirementsManagement(true);

        self::assertContains(self::TABLE_REQUIREMENT, $this->controller()->getTableNames());
    }

    private function givenRequirementsManagement(bool $enabled): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_calendar']['features']['requirementsManagement']
            = $enabled ? '1' : '0';
    }

    /**
     * @param array<string, mixed> $parsedBody
     */
    private function updateStatus(array $parsedBody): ResponseInterface
    {
        $request = (new ServerRequest('https://example.com/typo3/'))->withParsedBody($parsedBody);

        return $this->controller()->updateStatus($request);
    }

    private function controller(): EventsController
    {
        return new EventsController(
            $this->get(ExtensionConfiguration::class),
            $this->statusChangeContext,
            new CalendarPermissionService(),
        );
    }

    private function backendUserWithoutPublishPermission(): BackendUserAuthentication
    {
        $backendUser = self::createStub(BackendUserAuthentication::class);
        $backendUser->method('check')->willReturn(false);

        return $backendUser;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        return (array)json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function currentStatus(int $uid): int
    {
        return (int)($this->fetchRawRecord(self::TABLE_EVENT, $uid)['status'] ?? -1);
    }

    private function currentStatusMessage(int $uid): string
    {
        return (string)($this->fetchRawRecord(self::TABLE_EVENT, $uid)['status_message'] ?? '');
    }
}

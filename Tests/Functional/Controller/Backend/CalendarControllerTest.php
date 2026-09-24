<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Controller\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3Calendar\Controller\Backend\CalendarEventCreationController;
use Xima\XimaTypo3Calendar\Service\CalendarEventCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPendingCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\CalendarStoragePidResolver;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class CalendarControllerTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar.csv');
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['module' => 'events'], ['uid' => 2]);
    }

    #[Test]
    public function rejectsANonArrayPayload(): void
    {
        $response = $this->controller()->createEventAction(
            (new ServerRequest('https://example.com/typo3/'))->withParsedBody(new \stdClass())
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid event data.'], $this->decode($response));
    }

    #[Test]
    public function rejectsAnUnknownCreationType(): void
    {
        $response = $this->createEvent(['type' => 'unknown']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid creation type.'], $this->decode($response));
    }

    #[Test]
    public function refusesAnEventWithoutEventCreatePermission(): void
    {
        $this->setBackendUserPermissions(false, true);

        $response = $this->createEvent(['type' => 'event']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'No permission to create events.'], $this->decode($response));
    }

    #[Test]
    public function refusesAnAppointmentWithoutAppointmentCreatePermission(): void
    {
        $this->setBackendUserPermissions(true, false);

        $response = $this->createEvent(['type' => 'event-appointment']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'No permission to create events.'], $this->decode($response));
    }

    #[Test]
    public function refusesACalendarThatDoesNotBelongToTheStoragePage(): void
    {
        $this->setBackendUserPermissions(true, true);

        $response = $this->createEvent(['calendarUid' => 999]);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'message' => 'Invalid calendar.'], $this->decode($response));
    }

    #[Test]
    public function rejectsCleanupWithoutExactlyOneRecordUid(): void
    {
        $request = new ServerRequest('https://example.com/typo3/');
        $response = $this->controller()->cleanupEventAction($request);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function rejectsCleanupWithEventAndEntryUid(): void
    {
        $request = (new ServerRequest('https://example.com/typo3/'))->withQueryParams([
            'eventUid' => 1,
            'entryUid' => 1,
        ]);
        $response = $this->controller()->cleanupEventAction($request);

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): ResponseInterface
    {
        $payload = array_replace([
            'start' => 1767225600,
            'end' => 1767229200,
            'allDay' => 0,
            'type' => 'event-appointment',
            'calendarUid' => 1,
        ], $overrides);

        return $this->controller()->createEventAction(
            (new ServerRequest('https://example.com/typo3/'))->withParsedBody($payload)
        );
    }

    private function setBackendUserPermissions(bool $event, bool $appointment): void
    {
        $backendUser = self::createStub(BackendUserAuthentication::class);
        $backendUser->method('recordEditAccessInternals')->willReturnCallback(
            static function (string $table) use ($event, $appointment): bool {
                return match ($table) {
                    'tx_ximatypo3calendar_domain_model_event' => $event,
                    'tx_ximatypo3calendar_domain_model_entry' => $appointment,
                    default => false,
                };
            }
        );
        $GLOBALS['BE_USER'] = $backendUser;
    }

    private function controller(): CalendarEventCreationController
    {
        $connectionPool = $this->get(ConnectionPool::class);

        return new CalendarEventCreationController(
            $this->get(CalendarStoragePidResolver::class),
            new CalendarPermissionService(),
            $this->get(CalendarSelectionService::class),
            new CalendarEventCreationService(
                $connectionPool,
                $this->get(CalendarPendingCreationService::class),
            ),
            $this->get(CalendarPendingCreationService::class),
        );
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        return (array)json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Frontend APIs write events on behalf of a website user through DataHandler, with a
 * synthetic backend user that has no uid. The enforced entry restriction must not hide the
 * appointments DataHandler is about to link while it processes that write.
 */
final class FrontendWriteTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.org/', 'POST'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);

        $writeUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $writeUser->user = ['uid' => 0, 'admin' => 1, 'username' => '_api_writer'];
        $writeUser->workspace = 0;
        $writeUser->initializeUserSessionManager();
        $GLOBALS['BE_USER'] = $writeUser;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function appointmentsCreatedWithANewEventAreLinkedToIt(): void
    {
        $eventId = StringUtility::getUniqueId('NEW');
        $firstAppointmentId = StringUtility::getUniqueId('NEW');
        $secondAppointmentId = StringUtility::getUniqueId('NEW');

        $substitutions = $this->process([
            self::TABLE_EVENT => [
                $eventId => [
                    'pid' => 2,
                    'record_type' => 'event',
                    'title' => 'Created from the frontend',
                    'appointments' => $firstAppointmentId . ',' . $secondAppointmentId,
                ],
            ],
            self::TABLE_ENTRY => [
                $firstAppointmentId => ['pid' => 2, 'calendar' => 1, 'start_date' => 1798790400],
                $secondAppointmentId => ['pid' => 2, 'calendar' => 1, 'start_date' => 1798704000],
            ],
        ]);

        $eventUid = $substitutions[$eventId];
        self::assertSame($eventUid, (int)$this->fetchRawRecord(self::TABLE_ENTRY, $substitutions[$firstAppointmentId])['event']);
        self::assertSame($eventUid, (int)$this->fetchRawRecord(self::TABLE_ENTRY, $substitutions[$secondAppointmentId])['event']);
        self::assertSame(2, (int)$this->fetchRawRecord(self::TABLE_EVENT, $eventUid)['appointments']);
    }

    #[Test]
    public function anAppointmentAddedToAnExistingEventIsLinkedToIt(): void
    {
        $eventId = StringUtility::getUniqueId('NEW');
        $eventUid = $this->process([
            self::TABLE_EVENT => [$eventId => ['pid' => 2, 'record_type' => 'event', 'title' => 'Existing event']],
        ])[$eventId];

        $appointmentId = StringUtility::getUniqueId('NEW');
        $appointmentUid = $this->process([
            self::TABLE_EVENT => [$eventUid => ['appointments' => $appointmentId]],
            self::TABLE_ENTRY => [$appointmentId => ['pid' => 2, 'calendar' => 1, 'start_date' => 1798790400]],
        ])[$appointmentId];

        self::assertSame($eventUid, (int)$this->fetchRawRecord(self::TABLE_ENTRY, $appointmentUid)['event']);
    }

    #[Test]
    public function theRestrictionAppliesAgainOnceTheWriteIsDone(): void
    {
        $eventId = StringUtility::getUniqueId('NEW');
        $appointmentId = StringUtility::getUniqueId('NEW');
        $appointmentUid = $this->process([
            self::TABLE_EVENT => [$eventId => ['pid' => 2, 'record_type' => 'event', 'title' => 'Draft', 'appointments' => $appointmentId]],
            self::TABLE_ENTRY => [$appointmentId => ['pid' => 2, 'calendar' => 1, 'start_date' => 1798790400]],
        ])[$appointmentId];

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_ENTRY);
        $visible = $queryBuilder
            ->select('uid')
            ->from(self::TABLE_ENTRY)
            ->where($queryBuilder->expr()->eq('uid', $appointmentUid))
            ->executeQuery()
            ->fetchOne();

        self::assertFalse($visible);
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $dataMap
     * @return array<string, int>
     */
    private function process(array $dataMap): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, [], $GLOBALS['BE_USER']);
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        return array_map('intval', $dataHandler->substNEWwithIDs);
    }
}

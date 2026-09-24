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
 * The `plain-event` record type comes from the calendar_test fixture extension and
 * does not show the status field.
 */
final class DataHandlerHookTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function aNewEventOfARecordTypeWithTheStatusFieldStartsAsDraft(): void
    {
        $uid = $this->createEvent(['record_type' => 'event']);

        self::assertSame(EventStatus::DRAFT->value, (int)$this->fetchRawRecord(self::TABLE_EVENT, $uid)['status']);
    }

    #[Test]
    public function aNewEventKeepsAnExplicitlySubmittedStatus(): void
    {
        $uid = $this->createEvent(['record_type' => 'event', 'status' => EventStatus::REVIEW->value]);

        self::assertSame(EventStatus::REVIEW->value, (int)$this->fetchRawRecord(self::TABLE_EVENT, $uid)['status']);
    }

    #[Test]
    public function aNewEventOfARecordTypeWithoutTheStatusFieldHasNoStatus(): void
    {
        $uid = $this->createEvent(['record_type' => 'plain-event']);

        self::assertNull($this->fetchRawRecord(self::TABLE_EVENT, $uid)['status']);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createEvent(array $fields): int
    {
        $newId = StringUtility::getUniqueId('NEW');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE_EVENT => [$newId => ['pid' => 2, 'title' => 'New event'] + $fields]], []);
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        return (int)$dataHandler->substNEWwithIDs[$newId];
    }
}

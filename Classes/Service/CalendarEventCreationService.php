<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Xima\XimaTypo3Calendar\Utility\RecordTypeUtility;

final class CalendarEventCreationService
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CalendarPendingCreationService $pendingCreationService,
    ) {
    }

    /**
     * @return array{success: bool, eventUid?: int, entryUid?: int, errors?: array<int, mixed>, message?: string}
     */
    public function create(int $pid, int $start, int $end, bool $allDay, ?int $calendarUid = null): array
    {
        $newEventId = StringUtility::getUniqueId('NEW');
        $eventData = ['pid' => $pid];
        $eventRecordType = RecordTypeUtility::getDefault(self::EVENT_TABLE);
        if ($eventRecordType !== null) {
            $eventData['record_type'] = $eventRecordType;
        }

        $dataMap = [
            self::EVENT_TABLE => [
                $newEventId => $eventData,
            ],
        ];

        $newEntryId = StringUtility::getUniqueId('NEW');
        $entryData = [
            'pid' => $pid,
            'record_type' => 'event-appointment',
            'event' => $newEventId,
            'start_date' => $start,
            'end_date' => $end,
            'all_day' => $allDay ? 1 : 0,
        ];
        if ($calendarUid !== null) {
            $entryData['calendar'] = $calendarUid;
        }
        $dataMap[self::ENTRY_TABLE] = [
            $newEntryId => $entryData,
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            $eventUid = (int)($dataHandler->substNEWwithIDs[$newEventId] ?? 0);
            if ($eventUid > 0) {
                $this->pendingCreationService->registerEvent($eventUid, $pid);
                $this->pendingCreationService->cleanupEvent($eventUid, $pid);
            }

            return ['success' => false, 'errors' => $dataHandler->errorLog];
        }

        $eventUid = (int)($dataHandler->substNEWwithIDs[$newEventId] ?? 0);
        if ($eventUid <= 0) {
            return ['success' => false, 'message' => 'Event could not be created.'];
        }

        $result = ['success' => true, 'eventUid' => $eventUid];
        $entryUid = (int)($dataHandler->substNEWwithIDs[$newEntryId] ?? 0);
        if ($entryUid <= 0) {
            $this->pendingCreationService->registerEvent($eventUid, $pid);
            $this->pendingCreationService->cleanupEvent($eventUid, $pid);

            return ['success' => false, 'message' => 'Appointment could not be created.'];
        }
        $result['entryUid'] = $entryUid;
        $this->pendingCreationService->registerEvent($eventUid, $pid);

        return $result;
    }

    /**
     * Creates an appointment below the first existing event on the storage page.
     *
     * @return array{success: bool, entryUid?: int, errors?: array<int, mixed>, message?: string}
     */
    public function createAppointment(int $pid, int $start, int $end, bool $allDay, ?int $calendarUid = null): array
    {
        $eventUid = $this->findFirstEventUid($pid);
        if ($eventUid <= 0) {
            return ['success' => false, 'message' => 'No event exists for the appointment.'];
        }

        $newEntryId = StringUtility::getUniqueId('NEW');
        $entryData = [
            'pid' => $pid,
            'record_type' => 'event-appointment',
            'event' => $eventUid,
            'start_date' => $start,
            'end_date' => $end,
            'all_day' => $allDay ? 1 : 0,
        ];
        if ($calendarUid !== null) {
            $entryData['calendar'] = $calendarUid;
        }
        $dataMap = [
            self::ENTRY_TABLE => [
                $newEntryId => $entryData,
            ],
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            return ['success' => false, 'errors' => $dataHandler->errorLog];
        }

        $entryUid = (int)($dataHandler->substNEWwithIDs[$newEntryId] ?? 0);
        if ($entryUid <= 0) {
            return ['success' => false, 'message' => 'Appointment could not be created.'];
        }

        $this->pendingCreationService->registerEntry($entryUid, $pid);
        return ['success' => true, 'entryUid' => $entryUid];
    }

    private function findFirstEventUid(int $pid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        return (int)$queryBuilder
            ->select('uid')
            ->from(self::EVENT_TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
            )
            ->orderBy('uid', 'ASC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }
}

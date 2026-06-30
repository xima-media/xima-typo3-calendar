<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Hooks;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EntryChangedEvent;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Event\RequirementBookingChangedEvent;

class DataHandlerEventDispatcherHook
{
    private const TABLE_EVENT = 'tx_ximatypo3calendar_domain_model_event';
    private const TABLE_ENTRY = 'tx_ximatypo3calendar_domain_model_entry';
    private const TABLE_REQUIREMENT_BOOKING = 'tx_ximatypo3calendar_domain_model_requirementbooking';

    /** @var array<string, bool> */
    private static array $dispatchedEventKeys = [];

    /**
     * Tracks records that are being deleted via the main cmdmap flow (direct user action).
     * Inline child deletions triggered by a parent delete are NOT registered here,
     * which allows processCmdmap_deleteAction to distinguish them.
     *
     * @var array<string, bool>
     */
    private static array $directDeleteKeys = [];

    /** @var array<string, array<string, array{old: mixed, new: mixed}>> */
    private array $pendingDatamapEvents = [];

    /** @var array<string, array<string, mixed>> */
    private array $cmdPreProcessRecords = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        mixed $id,
        array &$fieldArray,
        DataHandler $parentObject
    ): void {
        if ($status !== 'update' || !$this->isHandledTable($table)) {
            return;
        }

        $record = $this->fetchRecord($table, $id);
        if ($record === null) {
            return;
        }

        if ($table === self::TABLE_ENTRY || $table === self::TABLE_REQUIREMENT_BOOKING) {
            $updatedFields = $this->buildChangedFields($record, $fieldArray);
            if ($updatedFields !== []) {
                $this->rememberDatamapEvent($table, $id, ChangeType::UPDATED, $updatedFields);
            }
        }

        if ($table === self::TABLE_ENTRY || $table === self::TABLE_EVENT) {
            $locationFields = $this->buildChangedFields($record, $fieldArray, ['location']);
            if ($locationFields !== []) {
                $this->rememberDatamapEvent($table, $id, ChangeType::LOCATION_CHANGED, $locationFields);
            }
        }

        if ($table === self::TABLE_ENTRY) {
            $dateRangeFields = $this->buildChangedFields($record, $fieldArray, ['start_date', 'end_date']);
            if ($dateRangeFields !== []) {
                $this->rememberDatamapEvent($table, $id, ChangeType::DATE_RANGE_CHANGED, $dateRangeFields);
            }
        }

        $hiddenFields = $this->buildChangedFields($record, $fieldArray, ['hidden']);
        if ($hiddenFields !== []) {
            $changeType = ((int)$hiddenFields['hidden']['new'] === 1) ? ChangeType::HIDDEN : ChangeType::REACTIVATED;
            $this->rememberDatamapEvent($table, $id, $changeType, []);
        }
    }

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        mixed $id,
        array $fieldArray,
        DataHandler $parentObject
    ): void {
        if (!$this->isHandledTable($table)) {
            return;
        }

        $uid = $this->resolveUid($status, $id, $parentObject);
        if ($uid <= 0) {
            return;
        }

        if ($status === 'new') {
            $createdFields = $this->buildCreatedFields($fieldArray);
            $this->dispatchLifecycleEvent($table, $uid, ChangeType::CREATED, $createdFields);

            if ($table === self::TABLE_ENTRY || $table === self::TABLE_EVENT) {
                $locationFields = $this->buildChangedFields([], $fieldArray, ['location']);
                if ($locationFields !== []) {
                    $this->dispatchLifecycleEvent($table, $uid, ChangeType::LOCATION_CHANGED, $locationFields);
                }
            }

            if ($table === self::TABLE_ENTRY) {
                $dateRangeFields = $this->buildChangedFields([], $fieldArray, ['start_date', 'end_date']);
                if ($dateRangeFields !== []) {
                    $this->dispatchLifecycleEvent($table, $uid, ChangeType::DATE_RANGE_CHANGED, $dateRangeFields);
                }
            }
            return;
        }

        if ($status !== 'update') {
            return;
        }

        $this->dispatchPendingDatamapEvent($table, $id, $uid, ChangeType::UPDATED);
        $this->dispatchPendingDatamapEvent($table, $id, $uid, ChangeType::HIDDEN);
        $this->dispatchPendingDatamapEvent($table, $id, $uid, ChangeType::REACTIVATED);
        $this->dispatchPendingDatamapEvent($table, $id, $uid, ChangeType::LOCATION_CHANGED);
        $this->dispatchPendingDatamapEvent($table, $id, $uid, ChangeType::DATE_RANGE_CHANGED);
    }

    public function processCmdmap_preProcess(
        string $command,
        string $table,
        mixed $id,
        mixed $value,
        DataHandler $parentObject,
        mixed $pasteUpdate
    ): void {
        if (!$this->isHandledTable($table)) {
            return;
        }
        if (!in_array($command, ['disable', 'enable', 'delete', 'copy'], true)) {
            return;
        }

        $record = $this->fetchRecord($table, $id);
        if ($record !== null) {
            $this->cmdPreProcessRecords[$this->buildCmdKey($command, $table, $id)] = $record;
        }

        if ($command === 'delete') {
            self::$directDeleteKeys[$this->buildCmdKey('delete', $table, $id)] = true;
        }
    }

    public function processCmdmap_postProcess(
        string $command,
        string $table,
        mixed $id,
        mixed $value,
        DataHandler $parentObject,
        mixed $pasteUpdate,
        mixed $pasteDatamap
    ): void {
        if (!$this->isHandledTable($table)) {
            return;
        }

        if ($command === 'copy') {
            $sourceUid = $this->resolveUid('update', $id, $parentObject);
            $newUid = $sourceUid > 0 ? (int)($parentObject->copyMappingArray_merged[$table][$sourceUid] ?? 0) : 0;
            if ($newUid > 0) {
                $newRecord = $this->fetchRecord($table, $newUid);
                $createdFields = $this->buildCreatedFields($newRecord ?? []);
                $this->dispatchLifecycleEvent($table, $newUid, ChangeType::CREATED, $createdFields);
            }
            return;
        }

        $uid = $this->resolveUid('update', $id, $parentObject);
        if ($uid <= 0) {
            return;
        }

        $beforeRecord = $this->cmdPreProcessRecords[$this->buildCmdKey($command, $table, $id)] ?? null;
        if ($beforeRecord === null) {
            return;
        }

        if ($command === 'delete') {
            $afterRecord = $this->fetchRecord($table, $uid);
            $deletedChanged = (int)($beforeRecord['deleted'] ?? 0) !== (int)($afterRecord['deleted'] ?? 0);
            if ($deletedChanged && (int)($afterRecord['deleted'] ?? 0) === 1) {
                $this->dispatchLifecycleEvent($table, $uid, ChangeType::DELETED, []);
            }
            unset($this->cmdPreProcessRecords[$this->buildCmdKey($command, $table, $id)]);
            return;
        }

        if ($command === 'disable') {
            $hiddenChanged = (int)($beforeRecord['hidden'] ?? 0) !== 1;
            if ($hiddenChanged) {
                $this->dispatchLifecycleEvent($table, $uid, ChangeType::HIDDEN, []);
            }
            unset($this->cmdPreProcessRecords[$this->buildCmdKey($command, $table, $id)]);
            return;
        }

        if ($command === 'enable') {
            $hiddenChanged = (int)($beforeRecord['hidden'] ?? 0) !== 0;
            if ($hiddenChanged) {
                $this->dispatchLifecycleEvent($table, $uid, ChangeType::REACTIVATED, []);
            }
            unset($this->cmdPreProcessRecords[$this->buildCmdKey($command, $table, $id)]);
        }
    }

    public function processCmdmap_deleteAction(
        string $table,
        int $uid,
        array $recordToDelete,
        bool &$recordWasDeleted,
        DataHandler $parentObject
    ): void {
        if (!$this->isHandledTable($table)) {
            return;
        }

        // Direct deletions are handled in processCmdmap_postProcess after the DB operation.
        // Only inline child deletions (cascaded from a parent delete) reach this point without a preProcess key.
        if (isset(self::$directDeleteKeys[$this->buildCmdKey('delete', $table, $uid)])) {
            return;
        }

        $this->dispatchLifecycleEvent($table, $uid, ChangeType::DELETED, []);
    }

    /**
     * @param array<string, mixed> $currentRecord
     * @param array<string, mixed> $incomingFieldArray
     * @param array<int, string>|null $allowedFields
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function buildChangedFields(array $currentRecord, array $incomingFieldArray, ?array $allowedFields = null): array
    {
        $changedFields = [];
        foreach ($incomingFieldArray as $field => $newValue) {
            if ($allowedFields !== null && !in_array($field, $allowedFields, true)) {
                continue;
            }

            $oldValue = $currentRecord[$field] ?? null;
            if ($this->valuesEqual($oldValue, $newValue)) {
                continue;
            }

            $changedFields[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changedFields;
    }

    /**
     * @param array<string, mixed> $fieldArray
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function buildCreatedFields(array $fieldArray): array
    {
        $changedFields = [];
        foreach ($fieldArray as $field => $newValue) {
            if ($this->valuesEqual(null, $newValue)) {
                continue;
            }

            $changedFields[$field] = [
                'old' => null,
                'new' => $newValue,
            ];
        }

        return $changedFields;
    }

    private function dispatchPendingDatamapEvent(string $table, mixed $rawId, int $uid, string $changeType): void
    {
        $key = $this->buildDatamapKey($table, $rawId, $changeType);
        if (!array_key_exists($key, $this->pendingDatamapEvents)) {
            return;
        }

        $changedFields = $this->pendingDatamapEvents[$key];
        unset($this->pendingDatamapEvents[$key]);

        if ($changedFields === [] && !in_array($changeType, [ChangeType::HIDDEN, ChangeType::REACTIVATED], true)) {
            return;
        }

        $this->dispatchLifecycleEvent($table, $uid, $changeType, $changedFields);
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changedFields
     */
    private function dispatchLifecycleEvent(string $table, int $uid, string $changeType, array $changedFields): void
    {
        if ($table === self::TABLE_EVENT && $changeType === ChangeType::UPDATED) {
            return;
        }
        if ($changedFields === [] && !in_array($changeType, [ChangeType::HIDDEN, ChangeType::DELETED, ChangeType::REACTIVATED], true)) {
            return;
        }
        if (!$this->registerDispatch($table, $uid, $changeType)) {
            return;
        }

        $event = match ($table) {
            self::TABLE_EVENT => new EventChangedEvent($uid, $table, $changeType, $changedFields),
            self::TABLE_ENTRY => new EntryChangedEvent($uid, $table, $changeType, $changedFields),
            self::TABLE_REQUIREMENT_BOOKING => new RequirementBookingChangedEvent($uid, $table, $changeType, $changedFields),
            default => null,
        };

        if ($event !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    private function rememberDatamapEvent(string $table, mixed $id, string $changeType, array $changedFields): void
    {
        $this->pendingDatamapEvents[$this->buildDatamapKey($table, $id, $changeType)] = $changedFields;
    }

    private function buildDatamapKey(string $table, mixed $id, string $changeType): string
    {
        return $table . ':' . (string)$id . ':' . $changeType;
    }

    private function buildCmdKey(string $command, string $table, mixed $id): string
    {
        return $command . ':' . $table . ':' . (string)$id;
    }

    private function registerDispatch(string $table, int $uid, string $changeType): bool
    {
        $eventKey = $table . ':' . $uid . ':' . $changeType;
        if (isset(self::$dispatchedEventKeys[$eventKey])) {
            return false;
        }
        self::$dispatchedEventKeys[$eventKey] = true;
        return true;
    }

    private function isHandledTable(string $table): bool
    {
        return in_array($table, [self::TABLE_EVENT, self::TABLE_ENTRY, self::TABLE_REQUIREMENT_BOOKING], true);
    }

    private function resolveUid(string $status, mixed $id, DataHandler $parentObject): int
    {
        if ($status === 'new') {
            return (int)($parentObject->substNEWwithIDs[(string)$id] ?? 0);
        }

        return is_numeric($id) ? (int)$id : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRecord(string $table, mixed $id): ?array
    {
        $uid = is_numeric($id) ? (int)$id : 0;
        if ($uid <= 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $record = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : null;
    }

    private function valuesEqual(mixed $oldValue, mixed $newValue): bool
    {
        if (is_array($oldValue) || is_array($newValue)) {
            return $oldValue == $newValue;
        }

        return (string)($oldValue ?? '') === (string)($newValue ?? '');
    }
}

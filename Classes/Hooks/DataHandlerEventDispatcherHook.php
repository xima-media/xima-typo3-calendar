<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Hooks;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
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

        foreach ($this->fieldChangeDefinitions() as $definition) {
            if (!in_array($table, $definition['tables'], true)) {
                continue;
            }
            $changedFields = $this->buildChangedFields($record, $fieldArray, $definition['fields']);
            if ($changedFields !== []) {
                $this->rememberDatamapEvent($table, $id, $definition['type'], $changedFields);
            }
        }

        // Hidden ↔ reactivated transitions carry no field diff.
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
            $this->dispatchLifecycleEvent($table, $uid, ChangeType::CREATED, $this->buildChangedFields([], $fieldArray));

            foreach ($this->fieldChangeDefinitions() as $definition) {
                // A brand-new record has no prior state to have "updated" against.
                if ($definition['type'] === ChangeType::UPDATED || !in_array($table, $definition['tables'], true)) {
                    continue;
                }
                $changedFields = $this->buildChangedFields([], $fieldArray, $definition['fields']);
                if ($changedFields !== []) {
                    $this->dispatchLifecycleEvent($table, $uid, $definition['type'], $changedFields);
                }
            }
            return;
        }

        if ($status !== 'update') {
            return;
        }

        foreach ([ChangeType::UPDATED, ChangeType::HIDDEN, ChangeType::REACTIVATED, ChangeType::LOCATION_CHANGED, ChangeType::DATE_RANGE_CHANGED] as $changeType) {
            $this->dispatchPendingDatamapEvent($table, $id, $uid, $changeType);
        }
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
                $createdFields = $this->buildChangedFields([], $newRecord ?? []);
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
     * Field-scoped change types and what triggers them: a non-empty diff on
     * `fields` (null = any field) within one of `tables` emits `type`.
     * Single source of truth shared by the datamap remember and dispatch paths.
     * Note: Event records are intentionally absent from UPDATED — they only
     * emit lifecycle and location changes.
     *
     * @return list<array{type: ChangeType, tables: list<string>, fields: list<string>|null}>
     */
    private function fieldChangeDefinitions(): array
    {
        return [
            ['type' => ChangeType::UPDATED, 'tables' => [self::TABLE_ENTRY, self::TABLE_REQUIREMENT_BOOKING], 'fields' => null],
            ['type' => ChangeType::LOCATION_CHANGED, 'tables' => [self::TABLE_ENTRY, self::TABLE_EVENT], 'fields' => ['location']],
            ['type' => ChangeType::DATE_RANGE_CHANGED, 'tables' => [self::TABLE_ENTRY], 'fields' => ['start_date', 'end_date']],
        ];
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

    private function dispatchPendingDatamapEvent(string $table, mixed $rawId, int $uid, ChangeType $changeType): void
    {
        $key = $this->buildDatamapKey($table, $rawId, $changeType);
        if (!array_key_exists($key, $this->pendingDatamapEvents)) {
            return;
        }

        $changedFields = $this->pendingDatamapEvents[$key];
        unset($this->pendingDatamapEvents[$key]);

        // The empty-field guard lives in dispatchLifecycleEvent (ChangeType::allowsEmptyFields()).
        $this->dispatchLifecycleEvent($table, $uid, $changeType, $changedFields);
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changedFields
     */
    private function dispatchLifecycleEvent(string $table, int $uid, ChangeType $changeType, array $changedFields): void
    {
        if ($changedFields === [] && !$changeType->allowsEmptyFields()) {
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

    private function rememberDatamapEvent(string $table, mixed $id, ChangeType $changeType, array $changedFields): void
    {
        $this->pendingDatamapEvents[$this->buildDatamapKey($table, $id, $changeType)] = $changedFields;
    }

    private function buildDatamapKey(string $table, mixed $id, ChangeType $changeType): string
    {
        return $table . ':' . (string)$id . ':' . $changeType->value;
    }

    private function buildCmdKey(string $command, string $table, mixed $id): string
    {
        return $command . ':' . $table . ':' . (string)$id;
    }

    private function registerDispatch(string $table, int $uid, ChangeType $changeType): bool
    {
        $eventKey = $table . ':' . $uid . ':' . $changeType->value;
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

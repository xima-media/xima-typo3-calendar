<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3Calendar\Database\RawRecordReader;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;

/**
 * Enforces the EventWorkflowPolicy on every DataHandler write to events and appointments.
 *
 * Refused records and commands are dropped and logged to the DataHandler error log, the rest
 * of the datamap or cmdmap is processed as usual. Copies of events start as a draft of the
 * copying user: DataHandler copies through a nested datamap, which is rewritten while a copy
 * command runs.
 */
#[Autoconfigure(public: true)]
class EventWorkflowGuardHook
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    private int $copyDepth = 0;

    /** @var array<int, true> */
    private array $readOnlyEventUids = [];

    public function __construct(
        private readonly EventWorkflowPolicy $policy,
        private readonly RawRecordReader $recordReader,
    ) {
    }

    /**
     * @param array<string, mixed>|null $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(?array &$incomingFieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        if (!is_array($incomingFieldArray)) {
            return;
        }

        $allowed = match ($table) {
            self::EVENT_TABLE => $this->mayWriteEvent($incomingFieldArray, $id, $dataHandler),
            self::ENTRY_TABLE => $this->mayWriteAppointment($incomingFieldArray, $id, $dataHandler),
            default => true,
        };

        if (!$allowed) {
            $this->logRefusal($dataHandler, $table, $id, 'write');
            $incomingFieldArray = null;
        }
    }

    /**
     * Refuses every change to an event that is read-only for the user, except its appointments.
     * Runs after DataHandler removed the values equal to the stored ones, so resubmitting the
     * read-only form passes while any actual change is dropped.
     *
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        if ($status !== 'update' || $table !== self::EVENT_TABLE || !isset($this->readOnlyEventUids[(int)$id])) {
            return;
        }
        unset($this->readOnlyEventUids[(int)$id]);

        $updatedAtField = (string)($GLOBALS['TCA'][self::EVENT_TABLE]['ctrl']['tstamp'] ?? '');
        $refusedFields = array_diff(array_keys($fieldArray), ['appointments', $updatedAtField]);
        if ($refusedFields !== []) {
            $this->logRefusal($dataHandler, $table, $id, 'change ' . implode(', ', $refusedFields) . ' of');
        }

        $fieldArray = array_key_exists('appointments', $fieldArray)
            ? array_intersect_key($fieldArray, ['appointments' => true, $updatedAtField => true])
            : [];
    }

    public function processCmdmap_beforeStart(DataHandler $dataHandler): void
    {
        foreach ([self::EVENT_TABLE, self::ENTRY_TABLE] as $table) {
            foreach ($dataHandler->cmdmap[$table] ?? [] as $id => $commands) {
                foreach (array_keys($commands) as $command) {
                    if (!$this->mayRunCommand((string)$command, $table, (int)$id, $dataHandler)) {
                        $this->logRefusal($dataHandler, $table, $id, (string)$command);
                        unset($dataHandler->cmdmap[$table][$id][$command]);
                    }
                }
            }
        }
    }

    public function processCmdmap_preProcess(string $command, string $table, mixed $id, mixed $value, DataHandler $dataHandler, mixed $pasteUpdate): void
    {
        if ($command === 'copy') {
            $this->copyDepth++;
        }
    }

    public function processCmdmap_postProcess(string $command, string $table, mixed $id, mixed $value, DataHandler $dataHandler, mixed $pasteUpdate, mixed $pasteDatamap): void
    {
        if ($command === 'copy') {
            $this->copyDepth = max(0, $this->copyDepth - 1);
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    private function mayWriteEvent(array &$incomingFieldArray, int|string $id, DataHandler $dataHandler): bool
    {
        $backendUser = $dataHandler->BE_USER;

        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            if ($this->copyDepth > 0) {
                $incomingFieldArray['status'] = EventStatus::DRAFT->value;
                $incomingFieldArray['owner_be_user'] = (int)($backendUser->user['uid'] ?? 0);
                $incomingFieldArray['approval_date'] = null;
                return true;
            }
            $newEvent = ['uid' => $id] + $incomingFieldArray;

            return $this->policy->canTransition($backendUser, $newEvent, EventStatus::DRAFT, $this->policy->statusOf($newEvent))
                && $this->mayLinkAppointments($incomingFieldArray, (string)$id, $dataHandler);
        }

        $event = $this->recordReader->fetch(self::EVENT_TABLE, (int)$id);
        if ($event === null) {
            return true;
        }

        if (!$this->policy->canEditEvent($backendUser, $event)) {
            if (!$this->policy->canManageAppointmentsOf($backendUser, $event)
                || !$this->mayLinkAppointments($incomingFieldArray, (string)$id, $dataHandler)
            ) {
                return false;
            }
            // The read-only form still submits the hidden values of its relation fields. They
            // would be written before DataHandler drops unchanged values, so they are ignored;
            // the remaining fields are checked once DataHandler knows which of them changed.
            $incomingFieldArray = array_diff_key($incomingFieldArray, $this->relationColumnsWrittenDirectly());
            $this->readOnlyEventUids[(int)$id] = true;
            return true;
        }

        if (array_key_exists('status', $incomingFieldArray)) {
            $from = $this->policy->statusOf($event);
            $to = $this->policy->statusOf($incomingFieldArray);
            if ($from !== $to && !$this->policy->canTransition($backendUser, $event, $from, $to)) {
                return false;
            }
        }

        if (array_key_exists('owner_be_user', $incomingFieldArray)
            && (int)$incomingFieldArray['owner_be_user'] !== (int)$event['owner_be_user']
            && !$this->policy->canAssignOwner($backendUser)
        ) {
            return false;
        }

        return $this->mayLinkAppointments($incomingFieldArray, (string)$id, $dataHandler);
    }

    /**
     * The inline relation re-parents every listed appointment, so each of them has to belong
     * to an event whose appointments the user may manage.
     *
     * @param array<string, mixed> $incomingFieldArray
     */
    private function mayLinkAppointments(array $incomingFieldArray, string $eventId, DataHandler $dataHandler): bool
    {
        if (!array_key_exists('appointments', $incomingFieldArray)) {
            return true;
        }

        foreach ($this->relationIds($incomingFieldArray['appointments']) as $appointmentId) {
            if (!MathUtility::canBeInterpretedAsInteger($appointmentId)) {
                continue;
            }
            $appointment = $this->recordReader->fetch(self::ENTRY_TABLE, (int)$appointmentId);
            $currentEventUid = (int)($appointment['event'] ?? 0);
            if ($currentEventUid > 0 && (string)$currentEventUid !== $eventId && !$this->mayManageAppointmentsOfEvent($currentEventUid, $dataHandler)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    private function mayWriteAppointment(array $incomingFieldArray, int|string $id, DataHandler $dataHandler): bool
    {
        $eventUids = [];
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            $eventUids[] = (int)($this->recordReader->fetch(self::ENTRY_TABLE, (int)$id)['event'] ?? 0);
        } else {
            $eventUids[] = $this->parentEventInDatamap((string)$id, $dataHandler);
        }
        if (MathUtility::canBeInterpretedAsInteger($incomingFieldArray['event'] ?? null)) {
            $eventUids[] = (int)$incomingFieldArray['event'];
        }

        foreach (array_unique($eventUids) as $eventUid) {
            if ($eventUid > 0 && !$this->mayManageAppointmentsOfEvent($eventUid, $dataHandler)) {
                return false;
            }
        }

        return true;
    }

    private function mayRunCommand(string $command, string $table, int $id, DataHandler $dataHandler): bool
    {
        $backendUser = $dataHandler->BE_USER;

        if ($table === self::ENTRY_TABLE) {
            $eventUid = (int)($this->recordReader->fetch(self::ENTRY_TABLE, $id)['event'] ?? 0);

            return $eventUid <= 0 || $this->mayManageAppointmentsOfEvent($eventUid, $dataHandler);
        }

        $event = $this->recordReader->fetch(self::EVENT_TABLE, $id);
        if ($event === null) {
            return true;
        }

        return $command === 'copy'
            ? $this->policy->canCopyEvent($backendUser, $event)
            : $this->policy->canEditEvent($backendUser, $event);
    }

    private function mayManageAppointmentsOfEvent(int $eventUid, DataHandler $dataHandler): bool
    {
        $event = $this->recordReader->fetch(self::EVENT_TABLE, $eventUid);

        return $event === null || $this->policy->canManageAppointmentsOf($dataHandler->BE_USER, $event);
    }

    /**
     * A new appointment created inline has no event yet: its parent is the event whose
     * appointments relation in the same datamap lists it.
     */
    private function parentEventInDatamap(string $appointmentId, DataHandler $dataHandler): int
    {
        foreach ($dataHandler->datamap[self::EVENT_TABLE] ?? [] as $eventId => $fields) {
            if (MathUtility::canBeInterpretedAsInteger($eventId)
                && in_array($appointmentId, $this->relationIds($fields['appointments'] ?? ''), true)
            ) {
                return (int)$eventId;
            }
        }

        return 0;
    }

    /**
     * Inline relation values come as a comma list of ids, which may carry the child table as
     * prefix (`<table>_<uid>`).
     *
     * @return list<string>
     */
    private function relationIds(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return array_map(
            static fn (string $id): string => str_starts_with($id, self::ENTRY_TABLE . '_') ? substr($id, strlen(self::ENTRY_TABLE) + 1) : $id,
            GeneralUtility::trimExplode(',', (string)$value, true)
        );
    }

    /**
     * Event columns whose relations DataHandler writes while it processes the field (inline
     * children, file references, MM tables), except the appointments.
     *
     * @return array<string, true>
     */
    private function relationColumnsWrittenDirectly(): array
    {
        $columns = [];
        foreach ($GLOBALS['TCA'][self::EVENT_TABLE]['columns'] ?? [] as $name => $column) {
            $config = $column['config'] ?? [];
            if ($name !== 'appointments'
                && (in_array($config['type'] ?? '', ['inline', 'file', 'category'], true) || isset($config['MM']))
            ) {
                $columns[(string)$name] = true;
            }
        }

        return $columns;
    }

    private function logRefusal(DataHandler $dataHandler, string $table, int|string $id, string $operation): void
    {
        $isExistingRecord = MathUtility::canBeInterpretedAsInteger($id);
        $dataHandler->log(
            $table,
            $isExistingRecord ? (int)$id : 0,
            $isExistingRecord ? SystemLogDatabaseAction::UPDATE : SystemLogDatabaseAction::INSERT,
            null,
            SystemLogErrorClassification::USER_ERROR,
            'Attempt to {operation} record "{table}:{uid}" refused by the event workflow permissions',
            null,
            ['operation' => $operation, 'table' => $table, 'uid' => $id]
        );
    }
}

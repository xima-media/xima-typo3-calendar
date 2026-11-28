<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Backend\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Database\RawRecordReader;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;

/**
 * Renders the edit form read-only where the EventWorkflowPolicy refuses the write.
 *
 * An event the user may not edit (a live event, for users without the publish permission)
 * gets every column read-only except its appointments: those stay manageable inline.
 * An appointment is read-only only when the user may not manage the appointments of its
 * event; the parent's status alone never locks it.
 */
final class LiveEventReadOnlyForNonPublisher implements FormDataProviderInterface
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    public function addData(array $result): array
    {
        $table = $result['tableName'] ?? '';
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (($table !== self::EVENT_TABLE && $table !== self::ENTRY_TABLE) || !$backendUser instanceof BackendUserAuthentication) {
            return $result;
        }

        $policy = GeneralUtility::makeInstance(EventWorkflowPolicy::class);
        $editableColumns = [];
        if ($table === self::EVENT_TABLE) {
            $event = $result['databaseRow'];
            if ($policy->canEditEvent($backendUser, $event)) {
                return $result;
            }
            if ($policy->canManageAppointmentsOf($backendUser, $event)) {
                $editableColumns[] = 'appointments';
            }
        } else {
            $event = GeneralUtility::makeInstance(RawRecordReader::class)
                ->fetch(self::EVENT_TABLE, $this->relationUid($result['databaseRow']['event'] ?? null));
            if ($event === null || $policy->canManageAppointmentsOf($backendUser, $event)) {
                return $result;
            }
        }

        foreach (array_keys($result['processedTca']['columns'] ?? []) as $columnName) {
            if (!in_array($columnName, $editableColumns, true)) {
                $result['processedTca']['columns'][$columnName]['config']['readOnly'] = true;
            }
        }

        return $result;
    }

    private function relationUid(mixed $value): int
    {
        if (is_array($value)) {
            $value = $value[0] ?? 0;
        }

        return (int)$value;
    }
}

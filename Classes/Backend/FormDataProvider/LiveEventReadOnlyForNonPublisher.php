<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Backend\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;

/**
 * Makes the whole edit form read-only when a backend user without the publish
 * permission opens a live event or an appointment of a live event.
 *
 * Editing published content is reserved for users who may handle live events.
 * Flagging every column as readOnly renders the form non-editable; disabled
 * fields are not submitted, so DataHandler writes nothing back either.
 *
 * An appointment (entry) has no status of its own — it inherits the state of its
 * parent event, whose status is looked up here.
 */
final class LiveEventReadOnlyForNonPublisher implements FormDataProviderInterface
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    public function addData(array $result): array
    {
        $table = $result['tableName'] ?? '';
        if ($table !== self::EVENT_TABLE && $table !== self::ENTRY_TABLE) {
            return $result;
        }

        if (GeneralUtility::makeInstance(CalendarPermissionService::class)->canPublishLiveEvents()) {
            return $result;
        }

        $isLive = $table === self::EVENT_TABLE
            ? $this->resolveStatus($result['databaseRow']['status'] ?? 0) === EventStatus::LIVE->value
            : $this->parentEventIsLive($result);
        if (!$isLive) {
            return $result;
        }

        foreach (array_keys($result['processedTca']['columns'] ?? []) as $columnName) {
            $result['processedTca']['columns'][$columnName]['config']['readOnly'] = true;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function parentEventIsLive(array $result): bool
    {
        $eventUid = $this->resolveRelationUid($result['databaseRow']['event'] ?? null);
        if ($eventUid <= 0) {
            return false;
        }

        // Raw lookup (no query restrictions) to get the true parent status.
        $status = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::EVENT_TABLE)
            ->select(['status'], self::EVENT_TABLE, ['uid' => $eventUid])
            ->fetchOne();

        return $status !== false && (int)$status === EventStatus::LIVE->value;
    }

    private function resolveStatus(mixed $status): int
    {
        if (is_array($status)) {
            $status = $status[0] ?? 0;
        }

        return (int)$status;
    }

    private function resolveRelationUid(mixed $value): int
    {
        if (is_array($value)) {
            $value = $value[0] ?? 0;
        }

        return (int)$value;
    }
}

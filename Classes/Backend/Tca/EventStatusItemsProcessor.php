<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Backend\Tca;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;

/**
 * itemsProcFunc for the event `status` field.
 *
 * Removes the LIVE option for backend users without the publish permission so
 * they cannot move an event live through the edit form. An event that is already
 * LIVE keeps the option, so its status still renders correctly and is not
 * silently downgraded on save.
 */
final class EventStatusItemsProcessor
{
    /**
     * @param array{items: array<int, array<string, mixed>>, row: array<string, mixed>} $params
     */
    public function removeUnauthorizedItems(array &$params): void
    {
        $permissionService = GeneralUtility::makeInstance(CalendarPermissionService::class);
        if ($permissionService->canPublishLiveEvents()) {
            return;
        }

        if ($this->currentStatus($params) === EventStatus::LIVE->value) {
            return;
        }

        foreach ($params['items'] as $key => $item) {
            if ((int)($item['value'] ?? $item[1] ?? -1) === EventStatus::LIVE->value) {
                unset($params['items'][$key]);
            }
        }

        $params['items'] = array_values($params['items']);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function currentStatus(array $params): int
    {
        $status = $params['row']['status'] ?? 0;
        if (is_array($status)) {
            $status = $status[0] ?? 0;
        }

        return (int)$status;
    }
}

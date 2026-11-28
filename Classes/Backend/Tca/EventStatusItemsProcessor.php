<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Backend\Tca;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;

/**
 * itemsProcFunc for the event `status` field.
 *
 * Offers the event's current status plus the statuses the EventWorkflowPolicy lets the
 * backend user move it to. The current status stays, so it still renders correctly and
 * is not silently changed on save.
 */
final class EventStatusItemsProcessor
{
    /**
     * @param array{items: array<int, array<array-key, mixed>>, row: array<string, mixed>} $params
     */
    public function removeUnauthorizedItems(array &$params): void
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $policy = GeneralUtility::makeInstance(EventWorkflowPolicy::class);
        $targets = $backendUser instanceof BackendUserAuthentication ? $policy->allowedTargetStatuses($backendUser, $params['row']) : [];
        $offeredValues = array_map(
            static fn (EventStatus $status): int => $status->value,
            [$policy->statusOf($params['row']), ...$targets]
        );

        foreach ($params['items'] as $key => $item) {
            $itemValue = $item['value'] ?? null;
            if ($itemValue === null) {
                $itemValues = array_values($item);
                $itemValue = $itemValues[1] ?? -1;
            }

            if (!in_array((int)$itemValue, $offeredValues, true)) {
                unset($params['items'][$key]);
            }
        }

        $params['items'] = array_values($params['items']);
    }
}

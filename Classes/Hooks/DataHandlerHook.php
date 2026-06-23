<?php

namespace Xima\XimaTypo3Calendar\Hooks;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

class DataHandlerHook
{
    protected bool $updatedEventStatus = false;

    public function processDatamap_postProcessFieldArray(string $status, string $table, mixed $id, array &$fieldArray, DataHandler $parentObject): void
    {
        // Save approvalDate of an event if status was updated to LIVE, otherwise reset approvalDate
        if ($status === 'update' && $table === 'tx_ximatypo3calendar_domain_model_event' && array_key_exists('status', $fieldArray)) {
            if ($fieldArray['status'] == EventStatus::LIVE->value) {
                $currentDateTime = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
                $fieldArray['approval_date'] = $currentDateTime;
            } else {
                $fieldArray['approval_date'] = null;
            }
            $this->updatedEventStatus = true;
        }

        if ($status === 'update' && $table === 'tx_ximatypo3calendar_domain_model_entry') {
            if (count($parentObject->datamap['tx_ximatypo3calendar_domain_model_event'] ?? []) === 1) {
                // If event's status was changed, reset all logged modifications
                if ($this->updatedEventStatus) {
                    $fieldArray['modified_date'] = null;
                    $fieldArray['modified_fields'] = '';
                } else {
                    // Save modifiedDate of an event appointment if startDate/endDate/canceled was changed
                    $modifiedFields = array_intersect_key(array_flip(['start_date', 'end_date', 'canceled']), $fieldArray);
                    if (count($modifiedFields)) {
                        $event = array_pop($parentObject->datamap['tx_ximatypo3calendar_domain_model_event']);
                        if ($event['status'] == EventStatus::LIVE->value) {
                            $currentDateTime = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
                            $fieldArray['modified_date'] = $currentDateTime;
                            $history = [];
                            foreach ($modifiedFields as $fieldName => $key) {
                                $history[$fieldName] = $parentObject->getHistoryRecords()[$table . ':' . $id]['oldRecord'][$fieldName] ?? $fieldArray[$fieldName];
                            }
                            $fieldArray['modified_fields'] = json_encode($history);
                        }
                    }
                }
            }
        }
    }
}

<?php

namespace Xima\XimaTypo3Calendar\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

#[Autoconfigure(public: true)]
/**
 * Maintains the derived workflow columns on events and appointments.
 *
 * Sole writer of approval_date on the event and of modified_date / modified_fields on the
 * appointment; the latter two feed the changed-field list in the live-edit notification.
 */
class DataHandlerHook
{
    protected bool $updatedEventStatus = false;

    public function processDatamap_postProcessFieldArray(string $status, string $table, mixed $id, array &$fieldArray, DataHandler $parentObject): void
    {
        if ($status === 'update' && $table === 'tx_ximatypo3calendar_domain_model_event' && array_key_exists('status', $fieldArray)) {
            if ($fieldArray['status'] == EventStatus::LIVE->value) {
                $currentDateTime = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
                $fieldArray['approval_date'] = $currentDateTime->getTimestamp();
            } else {
                $fieldArray['approval_date'] = null;
            }
            $this->updatedEventStatus = true;
        }

        if ($status === 'update' && $table === 'tx_ximatypo3calendar_domain_model_entry') {
            if (count($parentObject->datamap['tx_ximatypo3calendar_domain_model_event'] ?? []) === 1) {
                if ($this->updatedEventStatus) {
                    $fieldArray['modified_date'] = null;
                    $fieldArray['modified_fields'] = '';
                } else {
                    $modifiedFields = array_intersect_key(array_flip(['start_date', 'end_date', 'canceled']), $fieldArray);
                    if (count($modifiedFields)) {
                        $event = array_pop($parentObject->datamap['tx_ximatypo3calendar_domain_model_event']);
                        if ($event['status'] == EventStatus::LIVE->value) {
                            $currentDateTime = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
                            $fieldArray['modified_date'] = $currentDateTime->getTimestamp();
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

<?php

namespace Xima\XimaTypo3Calendar\Hooks;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

class DataHandlerHook
{
    public function processDatamap_postProcessFieldArray(string $status, string $table, mixed $id, array &$fieldArray, DataHandler $parentObject): void
    {
        // Save approvalDate of an event if status was updated
        if ($status === 'update' && $table === 'tx_ximatypo3calendar_domain_model_event') {
            if (array_key_exists('status', $fieldArray)) {
                if ($fieldArray['status'] == EventStatus::LIVE->value) {
                    $fieldArray['approval_date'] = time();
                } else {
                    $fieldArray['approval_date'] = null;
                }
            }
        }

        // Save modifiedDate of an event appointment if startDate/endDate/canceled was changed
        if ($status === 'update' && $table === 'tx_ximatypo3calendar_domain_model_entry') {
            if (array_intersect_key(array_flip(['start_date', 'end_date', 'canceled']), $fieldArray)) {
                $fieldArray['modified_date'] = time();
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Hooks;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Stamps the creating backend user onto an event's `owner_be_user` field.
 *
 * Runs on creation only: existing records keep whatever backend owner they were
 * assigned. An explicit owner_be_user provided with the create datamap is kept.
 */
class EventOwnerBackendUserHook
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';

    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        mixed $id,
        array &$fieldArray,
        DataHandler $parentObject
    ): void {
        if ($status !== 'new' || $table !== self::EVENT_TABLE) {
            return;
        }

        if (array_key_exists('owner_be_user', $fieldArray) && (int)$fieldArray['owner_be_user'] > 0) {
            return;
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return;
        }

        $backendUserUid = (int)($backendUser->user['uid'] ?? 0);
        if ($backendUserUid > 0) {
            $fieldArray['owner_be_user'] = $backendUserUid;
        }
    }
}

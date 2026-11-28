<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;

/**
 * Stamps the creating backend user onto an event's `owner_be_user` field.
 *
 * Runs on creation only: existing records keep whatever backend owner they were
 * assigned. An explicit owner_be_user in the create datamap is kept only for users
 * the EventWorkflowPolicy allows to assign owners.
 */
#[Autoconfigure(public: true)]
class EventOwnerBackendUserHook
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';

    public function __construct(
        private readonly EventWorkflowPolicy $policy,
    ) {
    }

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

        $backendUser = $parentObject->BE_USER;
        if ((int)($fieldArray['owner_be_user'] ?? 0) > 0 && $this->policy->canAssignOwner($backendUser)) {
            return;
        }

        $fieldArray['owner_be_user'] = (int)($backendUser->user['uid'] ?? 0);
    }
}

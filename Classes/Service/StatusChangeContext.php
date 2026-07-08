<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Carries the per-request intent of a backend status change from the status
 * modal endpoint to the workflow notification listener.
 *
 * The status update is persisted through the DataHandler, which dispatches the
 * EventChangedEvent synchronously within the same request. This singleton lets
 * the endpoint tell the listener whether the event owner should be notified,
 * without threading the flag through the DataHandler datamap.
 *
 * Owner notifications default to enabled: any status change that does not
 * originate from the modal (e.g. a regular record edit) keeps the previous
 * behaviour of always notifying the owner.
 */
final class StatusChangeContext implements SingletonInterface
{
    private bool $notifyOwner = true;

    public function setNotifyOwner(bool $notifyOwner): void
    {
        $this->notifyOwner = $notifyOwner;
    }

    public function shouldNotifyOwner(): bool
    {
        return $this->notifyOwner;
    }
}

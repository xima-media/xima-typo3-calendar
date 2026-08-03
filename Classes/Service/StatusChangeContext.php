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
 * the endpoint tell the listener whether the event owner should be notified and
 * which backend user performed the change, without threading that information
 * through the DataHandler datamap.
 *
 * The presence of a backend user (set via {@see setBackendUser()}) also marks
 * the change as backend-initiated: a status change that does not originate from
 * the modal (e.g. an event owner submitting for review from the frontend) never
 * populates it, so {@see isFromBackend()} stays false.
 *
 * Owner notifications default to enabled, so status changes not coming from the
 * modal always notify the owner.
 */
final class StatusChangeContext implements SingletonInterface
{
    private bool $notifyOwner = true;

    private bool $fromBackend = false;

    /** @var array{uid: int, name: string, email: string}|null */
    private ?array $backendUser = null;

    public function setNotifyOwner(bool $notifyOwner): void
    {
        $this->notifyOwner = $notifyOwner;
    }

    public function shouldNotifyOwner(): bool
    {
        return $this->notifyOwner;
    }

    /**
     * Records the backend user who triggered the status change and thereby marks
     * the change as backend-initiated.
     *
     * @param array{uid: int, name: string, email: string}|null $backendUser
     */
    public function setBackendUser(?array $backendUser): void
    {
        $this->fromBackend = true;
        $this->backendUser = $backendUser;
    }

    public function isFromBackend(): bool
    {
        return $this->fromBackend;
    }

    /**
     * @return array{uid: int, name: string, email: string}|null
     */
    public function getBackendUser(): ?array
    {
        return $this->backendUser;
    }

    public function getBackendUserName(): string
    {
        return trim((string)($this->backendUser['name'] ?? ''));
    }
}

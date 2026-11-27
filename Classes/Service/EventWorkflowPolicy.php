<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

/**
 * Decides what a backend user may do with an event and its appointments.
 *
 * Every surface asks this class: the DataHandler guard, the status endpoint, the status
 * select and modal and the read-only edit form. Users with `publish_live_events` (and
 * administrators) may do everything. Everybody else:
 *   - only moves their own events between draft, review and rejected → draft/review;
 *   - never touches live events, except for their appointments;
 *   - never touches events the event restriction hides from them.
 *
 * `$event` is the raw event row. A row without a numeric uid is an event being created,
 * which belongs to the creating user.
 */
#[Autoconfigure(public: true)]
final class EventWorkflowPolicy
{
    public function __construct(
        private readonly CalendarPermissionService $permissionService,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * @param array<string, mixed> $event
     */
    public function canTransition(BackendUserAuthentication $backendUser, array $event, EventStatus $from, EventStatus $to): bool
    {
        if ($this->isPublisher($backendUser)) {
            return true;
        }

        return $from !== EventStatus::LIVE
            && $to !== EventStatus::LIVE
            && $to !== EventStatus::REJECTED
            && $this->isOwnEvent($backendUser, $event);
    }

    /**
     * @param array<string, mixed> $event
     * @return list<EventStatus> the statuses the event may be moved to, without its current one
     */
    public function allowedTargetStatuses(BackendUserAuthentication $backendUser, array $event): array
    {
        $current = $this->statusOf($event);

        return array_values(array_filter(
            EventStatus::cases(),
            fn (EventStatus $to): bool => $to !== $current && $this->canTransition($backendUser, $event, $current, $to)
        ));
    }

    /**
     * Whether the event's own fields (and hiding, deleting, moving it) may be changed.
     *
     * @param array<string, mixed> $event
     */
    public function canEditEvent(BackendUserAuthentication $backendUser, array $event): bool
    {
        if ($this->isPublisher($backendUser)) {
            return true;
        }

        return $this->statusOf($event) !== EventStatus::LIVE && $this->canSeeEvent($backendUser, $event);
    }

    /**
     * Appointments are managed independently of the event's status.
     *
     * @param array<string, mixed> $event
     */
    public function canManageAppointmentsOf(BackendUserAuthentication $backendUser, array $event): bool
    {
        return $this->isPublisher($backendUser) || $this->canSeeEvent($backendUser, $event);
    }

    /**
     * Copying needs nothing but visibility: the copy starts as the copying user's draft.
     *
     * @param array<string, mixed> $event
     */
    public function canCopyEvent(BackendUserAuthentication $backendUser, array $event): bool
    {
        return $this->isPublisher($backendUser) || $this->canSeeEvent($backendUser, $event);
    }

    /**
     * Only publishers hand an event to another backend user; everybody else owns what they create.
     */
    public function canAssignOwner(BackendUserAuthentication $backendUser): bool
    {
        return $this->isPublisher($backendUser);
    }

    /**
     * @param array<string, mixed> $event
     */
    public function statusOf(array $event): EventStatus
    {
        $status = $event['status'] ?? null;
        if (is_array($status)) {
            $status = $status[0] ?? null;
        }

        return EventStatus::tryFrom((int)$status) ?? EventStatus::DRAFT;
    }

    private function isPublisher(BackendUserAuthentication $backendUser): bool
    {
        return $backendUser->isAdmin() || $this->permissionService->canPublishLiveEvents($backendUser);
    }

    /**
     * Mirrors the backend rules of EventRestriction, which cannot answer for rows that are
     * being written: DataHandler lifts it during the write.
     *
     * @param array<string, mixed> $event
     */
    private function canSeeEvent(BackendUserAuthentication $backendUser, array $event): bool
    {
        return $this->permissionService->canViewAllEvents($backendUser)
            || $this->statusOf($event) === EventStatus::LIVE
            || $this->isOwnEvent($backendUser, $event)
            || in_array((string)($event['record_type'] ?? ''), $this->unrestrictedRecordTypes(), true);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function isOwnEvent(BackendUserAuthentication $backendUser, array $event): bool
    {
        if (!MathUtility::canBeInterpretedAsInteger($event['uid'] ?? null)) {
            return true;
        }

        $owner = $event['owner_be_user'] ?? 0;
        if (is_array($owner)) {
            $owner = $owner[0] ?? 0;
        }
        $userId = (int)($backendUser->user['uid'] ?? 0);

        return $userId > 0 && (int)$owner === $userId;
    }

    /**
     * @return list<string>
     */
    private function unrestrictedRecordTypes(): array
    {
        try {
            $configured = $this->extensionConfiguration->get('xima_typo3_calendar', 'restrictions/unrestrictedRecordTypes');
        } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
            return [];
        }

        return is_string($configured) ? GeneralUtility::trimExplode(',', $configured, true) : [];
    }
}

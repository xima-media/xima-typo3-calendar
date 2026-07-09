<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Service\NotificationMailService;
use Xima\XimaTypo3Calendar\Service\NotificationRecipientResolver;
use Xima\XimaTypo3Calendar\Service\StatusChangeContext;

#[AsEventListener(
    identifier: 'xima-typo3-calendar/event-workflow-notification',
)]
final readonly class EventWorkflowNotification
{
    public function __construct(
        private EventRepository $eventRepository,
        private NotificationRecipientResolver $recipientResolver,
        private NotificationMailService $mailService,
        private StatusChangeContext $statusChangeContext,
    ) {
    }

    public function __invoke(EventChangedEvent $event): void
    {
        if ($event->changeType !== ChangeType::UPDATED) {
            return;
        }
        $eventRecord = $this->eventRepository->getEventRecordByUid($event->uid);
        if ($eventRecord === null) {
            return;
        }

        if (array_key_exists('status', $event->changedFields)) {
            $newStatus = (int)($event->changedFields['status']['new'] ?? EventStatus::DRAFT->value);

            // Submitted for approval → notify the backend reviewers.
            if ($newStatus === EventStatus::REVIEW->value) {
                $recipients = $this->recipientResolver->getSubscribedBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_REVIEW);
                $this->mailService->sendNotification('EventReviewNotification', $recipients, $eventRecord);

                // When a backend user moves the event into review (e.g. pulling it
                // back from live), also inform the owner. Skip this when the owner
                // submitted the event for review themselves from the frontend — that
                // never populates the (backend-only) status change context.
                if ($this->statusChangeContext->isFromBackend() && $this->statusChangeContext->shouldNotifyOwner()) {
                    $owners = $this->recipientResolver->getOwnerRecipients($eventRecord, $this->actingBackendUserUid());
                    $this->mailService->sendNotification('EventReviewOwnerNotification', $owners, $eventRecord);
                }
                return;
            }

            // Every other transition is owner-facing: reset to draft, published
            // (live from any prior state) or rejected. The status modal's
            // "notify owner" toggle can suppress these.
            $ownerTemplate = match ($newStatus) {
                EventStatus::DRAFT->value => 'EventDraftNotification',
                EventStatus::LIVE->value => 'EventPublishedNotification',
                EventStatus::REJECTED->value => 'EventRejectedNotification',
                default => null,
            };

            if ($ownerTemplate && $this->statusChangeContext->shouldNotifyOwner()) {
                $owners = $this->recipientResolver->getOwnerRecipients($eventRecord, $this->actingBackendUserUid());
                $this->mailService->sendNotification($ownerTemplate, $owners, $eventRecord);
                return;
            }
        }

        if ($this->isLiveEventUpdate($event->changedFields, $eventRecord)) {
            $recipients = $this->recipientResolver->getSubscribedBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_LIVE);
            $this->mailService->sendNotification('EventLiveNotification', $recipients, $eventRecord, array_keys($event->changedFields));
        }
    }

    /**
     * The backend user who triggered the current status change, or 0 when the
     * change did not originate from the backend status modal. Used to skip the
     * owner notification when that user is also the (backend user) owner.
     */
    private function actingBackendUserUid(): int
    {
        return (int)($this->statusChangeContext->getBackendUser()['uid'] ?? 0);
    }

    /**
     * Determines whether a change to an event qualifies as an update to a live event
     * (i.e. content changed while already published), as opposed to the initial publication.
     *
     * Logic:
     * 1. If the event is not currently LIVE, it's not a live update → false.
     * 2. If the status field was not changed, the event was already LIVE and other fields
     *    were modified → this is a live content update → true.
     * 3. If the status was changed FROM non-LIVE TO LIVE, this is the initial publication
     *    transition, not a content update → false.
     * 4. Otherwise the event was already LIVE (e.g. status changed from LIVE to LIVE or
     *    another field triggered a status re-save) → treat as live update → true.
     *
     * @param array<string, array{old?: mixed, new?: mixed}> $changedFields
     * @param array<string, mixed> $eventRecord
     */
    private function isLiveEventUpdate(array $changedFields, array $eventRecord): bool
    {
        // 1. Event is not live → no live-update notification needed
        if ((int)($eventRecord['status'] ?? EventStatus::DRAFT->value) !== EventStatus::LIVE->value) {
            return false;
        }

        // 2. Status was not part of the change → event was already live, other fields changed
        if (!array_key_exists('status', $changedFields)) {
            return true;
        }

        // 3. Status transitioned from non-LIVE to LIVE → this is the initial go-live, not an update
        $oldStatus = (int)($changedFields['status']['old'] ?? EventStatus::DRAFT->value);
        $newStatus = (int)($changedFields['status']['new'] ?? EventStatus::DRAFT->value);
        if ($oldStatus !== EventStatus::LIVE->value && $newStatus === EventStatus::LIVE->value) {
            return false;
        }

        // 4. All other cases (e.g. status stayed LIVE) → treat as live content update
        return true;
    }
}

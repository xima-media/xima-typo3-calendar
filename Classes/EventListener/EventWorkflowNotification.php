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

#[AsEventListener(
    identifier: 'xima-typo3-calendar/event-workflow-notification',
)]
final readonly class EventWorkflowNotification
{
    public function __construct(
        private EventRepository $eventRepository,
        private NotificationRecipientResolver $recipientResolver,
        private NotificationMailService $mailService,
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

            if ($newStatus === EventStatus::REVIEW->value) {
                $recipients = $this->recipientResolver->getSubscribedBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_REVIEW);
                $this->mailService->sendNotification('EventReviewNotification', $recipients, $eventRecord);
                return;
            }

            if ($newStatus === EventStatus::REJECTED->value) {
                $owner = $this->recipientResolver->getOwnerRecipient((int)($eventRecord['owner'] ?? 0));
                $this->mailService->sendNotification('EventRejectedNotification', $owner === null ? [] : [$owner], $eventRecord);
                return;
            }
        }

        if ($this->isLiveEventUpdate($event->changedFields, $eventRecord)) {
            $recipients = $this->recipientResolver->getSubscribedBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_LIVE);
            $this->mailService->sendNotification('EventLiveNotification', $recipients, $eventRecord, array_keys($event->changedFields));
        }
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

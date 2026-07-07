<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Service\NotificationMailService;
use Xima\XimaTypo3Calendar\Service\NotificationRecipientResolver;

/**
 * Turns event workflow status changes into notification emails.
 *
 * This listener only holds the workflow policy — which template goes to which
 * audience for a given status transition. Recipient lookups are delegated to
 * {@see NotificationRecipientResolver} and both content assembly and delivery to
 * {@see NotificationMailService}.
 */
#[AsEventListener(
    identifier: 'xima-typo3-calendar/event-workflow-notification',
)]
final readonly class EventWorkflowNotification
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private NotificationMailService $mailService,
    ) {
    }

    public function __invoke(EventChangedEvent $event): void
    {
        if ($event->changeType !== ChangeType::UPDATED) {
            return;
        }
        $eventRecord = $this->recipientResolver->getEventRecord($event->uid);
        if ($eventRecord === null) {
            return;
        }
        $eventTitle = (string)($eventRecord['title'] ?? '');

        if (array_key_exists('status', $event->changedFields)) {
            $newStatus = (int)($event->changedFields['status']['new'] ?? EventStatus::DRAFT->value);

            if ($newStatus === EventStatus::REVIEW->value) {
                $recipients = $this->recipientResolver->getBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_REVIEW);
                $this->mailService->sendNotification('EventReviewNotification', $recipients, $event->uid, $eventTitle);
                return;
            }

            if ($newStatus === EventStatus::REJECTED->value) {
                $owner = $this->recipientResolver->getOwnerRecipient((int)($eventRecord['owner'] ?? 0));
                $this->mailService->sendNotification('EventRejectedNotification', $owner === null ? [] : [$owner], $event->uid, $eventTitle);
                return;
            }
        }

        if ($this->isLiveEventUpdate($event->changedFields, $eventRecord)) {
            $recipients = $this->recipientResolver->getBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_LIVE);
            $this->mailService->sendNotification('EventLiveNotification', $recipients, $event->uid, $eventTitle, array_keys($event->changedFields));
        }
    }

    /**
     * @param array<string, array{old?: mixed, new?: mixed}> $changedFields
     * @param array<string, mixed> $eventRecord
     */
    private function isLiveEventUpdate(array $changedFields, array $eventRecord): bool
    {
        if ((int)($eventRecord['status'] ?? EventStatus::DRAFT->value) !== EventStatus::LIVE->value) {
            return false;
        }

        if (!array_key_exists('status', $changedFields)) {
            return true;
        }

        $oldStatus = (int)($changedFields['status']['old'] ?? EventStatus::DRAFT->value);
        $newStatus = (int)($changedFields['status']['new'] ?? EventStatus::DRAFT->value);
        if ($oldStatus !== EventStatus::LIVE->value && $newStatus === EventStatus::LIVE->value) {
            return false;
        }

        return true;
    }
}

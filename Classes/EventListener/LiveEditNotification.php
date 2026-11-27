<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Xima\XimaTypo3Calendar\Database\RawRecordReader;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EntryChangedEvent;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Service\NotificationMailService;
use Xima\XimaTypo3Calendar\Service\NotificationRecipientResolver;

/**
 * Sends the live-edit notification when the content or the appointments of a live event change.
 *
 * Editors without the publish permission manage the appointments of live events, so
 * subscribers learn about changes to published dates without a publisher being involved.
 *
 * Changes are collected and sent once the save ends (this class is also registered as
 * DataHandler hook): after the outermost datamap, or after its cmdmap when the save carries
 * commands too; DataHandler copies through nested instances. Each changed live event gets one
 * mail per save listing every changed field. Deleting a live event, which deletes its
 * appointments first, sends none. New events and status changes (publishing included) are
 * skipped, the workflow notifications cover them.
 */
#[Autoconfigure(public: true)]
final class LiveEditNotification
{
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    /** @var array<int, array<string, true>> changed field names per event uid */
    private array $changedEventFields = [];

    /** @var array<int, true> */
    private array $changedAppointmentUids = [];

    /** @var array<int, true> */
    private array $skippedEventUids = [];

    public function __construct(
        private readonly RawRecordReader $recordReader,
        private readonly EventRepository $eventRepository,
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly NotificationMailService $mailService,
    ) {
    }

    #[AsEventListener(identifier: 'xima-typo3-calendar/live-edit-notification-appointment')]
    public function collectAppointmentChange(EntryChangedEvent $event): void
    {
        if (!in_array($event->changeType, [ChangeType::CREATED, ChangeType::UPDATED, ChangeType::DELETED, ChangeType::HIDDEN, ChangeType::REACTIVATED], true)) {
            return;
        }

        // The current parent is resolved when sending: a new inline appointment gets it only
        // once DataHandler remaps the event's relation, after this event was dispatched.
        $this->changedAppointmentUids[$event->uid] = true;

        $previousEventUid = (int)($event->changedFields['event']['old'] ?? 0);
        if ($previousEventUid > 0) {
            $this->changedEventFields[$previousEventUid]['appointments'] = true;
        }
    }

    #[AsEventListener(identifier: 'xima-typo3-calendar/live-edit-notification-event')]
    public function collectEventChange(EventChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED || array_key_exists('status', $event->changedFields)) {
            $this->skippedEventUids[$event->uid] = true;
            return;
        }

        if ($event->changeType === ChangeType::UPDATED) {
            foreach (array_keys($event->changedFields) as $fieldName) {
                $this->changedEventFields[$event->uid][$fieldName] = true;
            }
        }
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        if ($dataHandler->isOuterMostInstance() && $dataHandler->cmdmap === []) {
            $this->sendCollectedNotifications();
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        if ($dataHandler->isOuterMostInstance()) {
            $this->sendCollectedNotifications();
        }
    }

    private function sendCollectedNotifications(): void
    {
        $changedEventFields = $this->changedEventFields;
        foreach (array_keys($this->changedAppointmentUids) as $appointmentUid) {
            $eventUid = (int)($this->recordReader->fetch(self::ENTRY_TABLE, $appointmentUid)['event'] ?? 0);
            if ($eventUid > 0) {
                $changedEventFields[$eventUid]['appointments'] = true;
            }
        }
        $changedEventFields = array_diff_key($changedEventFields, $this->skippedEventUids);
        $this->changedEventFields = [];
        $this->changedAppointmentUids = [];
        $this->skippedEventUids = [];

        foreach ($changedEventFields as $eventUid => $fieldNames) {
            $eventRecord = $this->eventRepository->getEventRecordByUid($eventUid);
            if ($eventRecord === null
                || (int)($eventRecord['deleted'] ?? 0) === 1
                || (int)($eventRecord['status'] ?? -1) !== EventStatus::LIVE->value
            ) {
                continue;
            }

            $recipients = $this->recipientResolver->getSubscribedBackendRecipients($eventUid, NotificationRecipientResolver::PREFERENCE_LIVE);
            $this->mailService->sendNotification('EventLiveNotification', $recipients, $eventRecord, array_keys($fieldNames));
        }
    }
}

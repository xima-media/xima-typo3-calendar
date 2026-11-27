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
 * Sends the live-edit notification when appointments of a live event change.
 *
 * Editors without the publish permission manage these appointments, so subscribers learn
 * about changes to published content without a publisher being involved.
 *
 * Changes are collected per appointment and sent once the save ends (this class is also
 * registered as DataHandler hook): after the datamap, or after the cmdmap when the save
 * carries commands too. Saving several appointments sends one mail, and deleting a live
 * event, which deletes its appointments first, sends none. Events created or published in
 * the same run are skipped too, their own notification covers them.
 */
#[Autoconfigure(public: true)]
final class LiveAppointmentNotification
{
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

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

    #[AsEventListener(identifier: 'xima-typo3-calendar/live-appointment-notification')]
    public function collectAppointmentChange(EntryChangedEvent $event): void
    {
        if (in_array($event->changeType, [ChangeType::CREATED, ChangeType::UPDATED, ChangeType::DELETED, ChangeType::HIDDEN, ChangeType::REACTIVATED], true)) {
            // The parent is resolved when sending: a new inline appointment gets it only once
            // DataHandler remaps the event's relation, after this event was dispatched.
            $this->changedAppointmentUids[$event->uid] = true;
        }
    }

    #[AsEventListener(identifier: 'xima-typo3-calendar/live-appointment-notification-skip')]
    public function skipCreatedOrPublishedEvent(EventChangedEvent $event): void
    {
        $publishes = (int)($event->changedFields['status']['new'] ?? -1) === EventStatus::LIVE->value
            && (int)($event->changedFields['status']['old'] ?? -1) !== EventStatus::LIVE->value;

        if ($event->changeType === ChangeType::CREATED || $publishes) {
            $this->skippedEventUids[$event->uid] = true;
        }
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        if ($dataHandler->cmdmap === []) {
            $this->sendCollectedNotifications();
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $this->sendCollectedNotifications();
    }

    private function sendCollectedNotifications(): void
    {
        $eventUids = [];
        foreach (array_keys($this->changedAppointmentUids) as $appointmentUid) {
            $eventUid = (int)($this->recordReader->fetch(self::ENTRY_TABLE, $appointmentUid)['event'] ?? 0);
            if ($eventUid > 0 && !isset($this->skippedEventUids[$eventUid])) {
                $eventUids[$eventUid] = true;
            }
        }
        $this->changedAppointmentUids = [];
        $this->skippedEventUids = [];

        foreach (array_keys($eventUids) as $eventUid) {
            $eventRecord = $this->eventRepository->getEventRecordByUid($eventUid);
            if ($eventRecord === null
                || (int)($eventRecord['deleted'] ?? 0) === 1
                || (int)($eventRecord['status'] ?? -1) !== EventStatus::LIVE->value
            ) {
                continue;
            }

            $recipients = $this->recipientResolver->getSubscribedBackendRecipients($eventUid, NotificationRecipientResolver::PREFERENCE_LIVE);
            $this->mailService->sendNotification('EventLiveNotification', $recipients, $eventRecord, ['appointments']);
        }
    }
}

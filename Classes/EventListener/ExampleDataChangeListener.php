<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Log\LogDataTrait;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EntryDateRangeChangedEvent;
use Xima\XimaTypo3Calendar\Event\EntryLifecycleChangedEvent;
use Xima\XimaTypo3Calendar\Event\EventLifecycleChangedEvent;
use Xima\XimaTypo3Calendar\Event\LocationChangedEvent;
use Xima\XimaTypo3Calendar\Event\RequirementBookingLifecycleChangedEvent;

/**
 * Example event listener demonstrating how to consume calendar change events.
 *
 * @codeCoverageIgnore Example code for documentation
 */
#[AsEventListener(
    identifier: 'xima-typo3-calendar/example-change-listener',
)]
readonly class ExampleDataChangeListener
{
    use LogDataTrait;

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        RequirementBookingLifecycleChangedEvent|EntryLifecycleChangedEvent|EventLifecycleChangedEvent|LocationChangedEvent|EntryDateRangeChangedEvent $event
    ): void {
        match ($event::class) {
            RequirementBookingLifecycleChangedEvent::class => $this->handleRequirementBooking($event),
            EntryLifecycleChangedEvent::class => $this->handleEntry($event),
            EventLifecycleChangedEvent::class => $this->handleEvent($event),
            LocationChangedEvent::class => $this->handleLocationChanged($event),
            EntryDateRangeChangedEvent::class => $this->handleDateRangeChanged($event),
            default => null,
        };
    }

    private function handleRequirementBooking(RequirementBookingLifecycleChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            $this->logger->info('RequirementBooking created', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::DELETED) {
            $this->logger->info('RequirementBooking deleted', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::UPDATED) {
            $this->logger->info('RequirementBooking changed', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        }
    }

    private function handleEntry(EntryLifecycleChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            $this->logger->info('Entry created', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::UPDATED) {
            $this->logger->info('Entry updated', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::HIDDEN) {
            $this->logger->info('Entry hidden', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::DELETED) {
            $this->logger->info('Entry deleted', ['uid' => $event->uid]);
        }
    }

    private function handleEvent(EventLifecycleChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            $this->logger->info('Event created', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::DELETED) {
            $this->logger->info('Event deleted', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::REACTIVATED) {
            $this->logger->info('Event reactivated', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::HIDDEN) {
            $this->logger->info('Event hidden', ['uid' => $event->uid]);
        }
    }

    private function handleLocationChanged(LocationChangedEvent $event): void
    {
        $this->logger->info('Location changed', ['uid' => $event->uid, 'table' => $event->table]);
    }

    private function handleDateRangeChanged(EntryDateRangeChangedEvent $event): void
    {
        if (isset($event->changedFields['start_date'])) {
            $this->logger->info('Entry start_date changed', [
                'uid' => $event->uid,
                'old' => $event->changedFields['start_date']['old'],
                'new' => $event->changedFields['start_date']['new'],
            ]);
        }
        if (isset($event->changedFields['end_date'])) {
            $this->logger->info('Entry end_date changed', [
                'uid' => $event->uid,
                'old' => $event->changedFields['end_date']['old'],
                'new' => $event->changedFields['end_date']['new'],
            ]);
        }
    }
}

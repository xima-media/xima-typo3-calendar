<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EntryChangedEvent;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Event\RequirementBookingChangedEvent;

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
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        RequirementBookingChangedEvent|EntryChangedEvent|EventChangedEvent $event
    ): void {
        match ($event::class) {
            RequirementBookingChangedEvent::class => $this->handleRequirementBooking($event),
            EntryChangedEvent::class => $this->handleEntry($event),
            EventChangedEvent::class => $this->handleEvent($event),
            default => null,
        };
    }

    private function handleRequirementBooking(RequirementBookingChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            $this->logger->info('RequirementBooking created', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::DELETED) {
            $this->logger->info('RequirementBooking deleted', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::UPDATED) {
            $this->logger->info('RequirementBooking changed', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        }
    }

    private function handleEntry(EntryChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            $this->logger->info('Entry created', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::UPDATED) {
            $this->logger->info('Entry updated', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::HIDDEN) {
            $this->logger->info('Entry hidden', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::DELETED) {
            $this->logger->info('Entry deleted', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::LOCATION_CHANGED) {
            $this->logger->info('Entry location changed', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        } elseif ($event->changeType === ChangeType::DATE_RANGE_CHANGED) {
            $this->logger->info('Entry date range changed', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        }
    }

    private function handleEvent(EventChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            $this->logger->info('Event created', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::DELETED) {
            $this->logger->info('Event deleted', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::REACTIVATED) {
            $this->logger->info('Event reactivated', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::HIDDEN) {
            $this->logger->info('Event hidden', ['uid' => $event->uid]);
        } elseif ($event->changeType === ChangeType::LOCATION_CHANGED) {
            $this->logger->info('Event location changed', ['uid' => $event->uid, 'fields' => array_keys($event->changedFields)]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Fixtures;

use Xima\XimaTypo3Calendar\Event\AbstractRecordChangedEvent;
use Xima\XimaTypo3Calendar\Event\ChangeType;

/**
 * Records every record-changed event the extension dispatches.
 *
 * Registered as a shared, public service by the `calendar_test` fixture
 * extension for the abstract parent event — ListenerProvider walks the parent
 * classes of a dispatched event, so one registration catches the event, entry
 * and requirement-booking variants alike.
 */
final class RecordingEventListener
{
    /** @var list<AbstractRecordChangedEvent> */
    private array $events = [];

    public function __invoke(AbstractRecordChangedEvent $event): void
    {
        $this->events[] = $event;
    }

    public function reset(): void
    {
        $this->events = [];
    }

    /**
     * @return list<AbstractRecordChangedEvent>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @return list<AbstractRecordChangedEvent>
     */
    public function for(string $table, ?ChangeType $changeType = null): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (AbstractRecordChangedEvent $event): bool => $event->table === $table
                && ($changeType === null || $event->changeType === $changeType)
        ));
    }

    /**
     * @return list<string> the change types recorded for $table, in dispatch order
     */
    public function changeTypesFor(string $table): array
    {
        return array_map(
            static fn (AbstractRecordChangedEvent $event): string => $event->changeType->value,
            $this->for($table)
        );
    }
}

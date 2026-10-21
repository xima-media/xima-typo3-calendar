<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

final class CalendarEntrySerializationEvent
{
    /**
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $sourceRow
     */
    public function __construct(
        private array $eventData,
        private readonly array $sourceRow,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        return $this->eventData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSourceRow(): array
    {
        return $this->sourceRow;
    }

    public function addExtendedProperty(string $identifier, mixed $value): void
    {
        $this->eventData['extendedProps'][$identifier] = $value;
    }

    /**
     * @param string|int|array<int, string|int>|null $value
     */
    public function addFilterValue(
        string $identifier,
        string|int|array|null $value,
    ): void {
        $this->eventData['extendedProps']['filterValues'][$identifier] = $value;
    }
}

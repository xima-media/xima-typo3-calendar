<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

/**
 * Dispatched by EventSearchService before the event search of the creation modal answers.
 *
 * A listener may add lines to `details`, which the modal shows below the date range, or
 * drop and reorder results.
 *
 * @phpstan-type EventSearchResult array{uid: int, title: string, firstStart: int|null, lastEnd: int|null, details: list<string>}
 */
final class ModifyEventSearchResultsEvent
{
    /**
     * @param list<EventSearchResult> $results
     */
    public function __construct(
        private array $results,
        private readonly string $searchTerm,
    ) {
    }

    /**
     * @return list<EventSearchResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @param list<EventSearchResult> $results
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }
}

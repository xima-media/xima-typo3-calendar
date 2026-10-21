<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class ApplyCalendarFilterEvent
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        private QueryBuilder $queryBuilder,
        private readonly array $filters,
    ) {
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function setQueryBuilder(QueryBuilder $queryBuilder): void
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class BeforeWidgetItemsFetchedEvent
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private readonly string $dispatchingClassName,
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

    public function getDispatchingClassName(): string
    {
        return $this->dispatchingClassName;
    }
}

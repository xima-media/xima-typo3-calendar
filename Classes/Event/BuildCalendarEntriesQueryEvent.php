<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class BuildCalendarEntriesQueryEvent
{
    public function __construct(
        private QueryBuilder $queryBuilder,
        private int $languageId = 0,
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

    public function getLanguageId(): int
    {
        return $this->languageId;
    }
}

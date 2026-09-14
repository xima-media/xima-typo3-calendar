<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Extension point for the ke_search event indexer.
 *
 * Dispatched by Indexer\EventIndexer before the candidate query runs and re-read afterwards, so
 * a listener may narrow, re-order or replace it. The query selects `a.uid AS appointment_uid`
 * and `e.uid AS event_uid` from the entry table aliased `a`, joined to the event table aliased
 * `e`; the aliases and both column aliases are part of the contract.
 *
 * Constraining the appointment side narrows the index too: the time window and the sort date are
 * measured against the appointments this query admits.
 *
 * The extension constrains nothing beyond visibility and the time window. `record_type` and
 * `publish_to_website` are project decisions: installations that do not maintain the flag would
 * index nothing if it were filtered here.
 */
class ModifyKeSearchIndexerQueryEvent
{
    /**
     * @param array<string, mixed> $indexerConfig
     */
    public function __construct(
        private QueryBuilder $queryBuilder,
        private readonly array $indexerConfig,
        private readonly int $now,
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
    public function getIndexerConfig(): array
    {
        return $this->indexerConfig;
    }

    /**
     * The moment the time window is measured against, shared by every event of one run.
     */
    public function getNow(): int
    {
        return $this->now;
    }
}

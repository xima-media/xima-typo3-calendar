<?php

namespace Xima\XimaTypo3Calendar\Domain\Repository;

use Doctrine\DBAL\Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\Repository;
use Xima\XimaTypo3Calendar\Event\ApplyCalendarFilterEvent;
use Xima\XimaTypo3Calendar\Event\BuildCalendarEntriesQueryEvent;

class EntryRepository extends Repository
{
    private const TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Returns the last-write timestamps of the given entries, keyed by uid.
     *
     * `tstamp` is a control field rather than a TCA column, so Extbase never maps it onto the
     * domain model and it has to be read from the row.
     *
     * @param int[] $uids
     * @return array<int, int>
     * @throws Exception
     */
    public function getTimestampsByUids(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid', 'tstamp')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $timestamps = [];
        foreach ($rows as $row) {
            $timestamps[(int)$row['uid']] = (int)$row['tstamp'];
        }

        return $timestamps;
    }

    public function getBackendCalendarEntries(
        int $startTime,
        int $endTime,
        array $calendarUids = [],
        array $filters = [],
        int $languageId = 0,
    ): array {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $entryEndDateExpression = 'COALESCE(NULLIF('
            . $queryBuilder->quoteIdentifier('e.end_date')
            . ', 0), '
            . $queryBuilder->quoteIdentifier('e.start_date')
            . ')';

        $queryBuilder
            ->select(
                'e.uid',
                'e.pid',
                'e.title',
                'e.start_date',
                'e.end_date',
                'e.all_day',
                'e.calendar',
                'e.description',
                'e.canceled',
                'e.speakers',
                'c.title as calendar_title',
                'c.uid as calendar_uid',
                'v.title as event_title',
                'v.uid as event_uid',
                'v.description as event_description',
                'v.language as event_language',
                'v.owner as event_owner',
                'loc.name as location_name',
            )
            ->from(self::TABLE, 'e')
            ->leftJoin('e', 'tx_ximatypo3calendar_domain_model_calendar', 'c', 'e.calendar = c.uid')
            ->leftJoin('e', 'tx_ximatypo3calendar_domain_model_event', 'v', 'e.event = v.uid')
            ->leftJoin('e', 'tx_ximatypo3calendar_domain_model_location', 'loc', 'e.location = loc.uid')
            ->where(
                $queryBuilder->expr()->lte('e.start_date', $queryBuilder->createNamedParameter($endTime, Connection::PARAM_INT)),
                $entryEndDateExpression . ' >= ' . $queryBuilder->createNamedParameter($startTime, Connection::PARAM_INT),
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            );

        if ($calendarUids !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'e.calendar',
                    $queryBuilder->createNamedParameter($calendarUids, Connection::PARAM_INT_ARRAY),
                )
            );
        }

        $buildEvent = new BuildCalendarEntriesQueryEvent(
            queryBuilder: $queryBuilder,
            languageId: $languageId,
        );

        $this->eventDispatcher->dispatch($buildEvent);

        $queryBuilder = $buildEvent->getQueryBuilder();

        $event = new ApplyCalendarFilterEvent(
            queryBuilder: $queryBuilder,
            filters: $filters,
        );

        $this->eventDispatcher->dispatch($event);

        $queryBuilder = $event->getQueryBuilder();

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
}

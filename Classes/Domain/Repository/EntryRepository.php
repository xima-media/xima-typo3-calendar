<?php

namespace Xima\XimaTypo3Calendar\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

class EntryRepository extends Repository
{
    private const TABLE = 'tx_ximatypo3calendar_domain_model_entry';

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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
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

    /**
     * @param int[] $calendarUids
     * @throws Exception
     */
    public function getBackendCalendarEntries(int $startTime, int $endTime, array $calendarUids = []): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
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
                'e.record_type',
                'e.description',
                'e.canceled',
                'e.speakers',
                'c.title as calendar_title',
                'c.uid as calendar_uid',
                'v.title as event_title',
                'v.uid as event_uid',
                'v.description as event_description',
                'v.language as event_language',
                'v.status as event_status',
                'v.owner as event_owner',
                'loc.name as location_name',
            )
            ->addSelectLiteral(
                '(SELECT cat.title FROM sys_category cat'
                . ' JOIN sys_category_record_mm mm ON mm.uid_local = cat.uid'
                . ' WHERE mm.uid_foreign = v.uid'
                . ' AND mm.tablenames = ' . $queryBuilder->quote('tx_ximatypo3calendar_domain_model_event')
                . ' AND mm.fieldname = ' . $queryBuilder->quote('categories')
                . ' AND cat.deleted = 0'
                . ' ORDER BY mm.sorting LIMIT 1) as event_category_title'
            )
            ->addSelectLiteral(
                '(SELECT cat.uid FROM sys_category cat'
                . ' JOIN sys_category_record_mm mm ON mm.uid_local = cat.uid'
                . ' WHERE mm.uid_foreign = v.uid'
                . ' AND mm.tablenames = ' . $queryBuilder->quote('tx_ximatypo3calendar_domain_model_event')
                . ' AND mm.fieldname = ' . $queryBuilder->quote('categories')
                . ' AND cat.deleted = 0'
                . ' ORDER BY mm.sorting LIMIT 1) as event_category_id'
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
                $queryBuilder->expr()->in('e.calendar', $calendarUids)
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
}

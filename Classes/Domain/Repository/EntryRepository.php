<?php

namespace Xima\XimaTypo3Calendar\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

class EntryRepository extends Repository
{

    /**
     * @param int[] $calendarUids
     * @throws Exception
     */
    public function getBackendCalendarEntries(string $startTime, string $endTime, array $calendarUids = []): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
        $queryBuilder
            ->select(
                'e.uid',
                'e.title',
                'e.start_date',
                'e.end_date',
                'e.all_day',
                'e.calendar',
                'e.record_type',
                'c.title as calendar_title',
                'c.uid as calendar_uid',
                'v.title as event_title',
                'v.uid as event_uid',
            )
            ->from('tx_ximatypo3calendar_domain_model_entry', 'e')
            ->leftJoin('e', 'tx_ximatypo3calendar_domain_model_calendar', 'c', 'e.calendar = c.uid')
            ->leftJoin('e', 'tx_ximatypo3calendar_domain_model_event', 'v', 'e.event = v.uid')
            ->where(
                $queryBuilder->expr()->gte('e.start_date', $queryBuilder->createNamedParameter($startTime)),
                $queryBuilder->expr()->lte('e.start_date', $queryBuilder->createNamedParameter($endTime)),
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

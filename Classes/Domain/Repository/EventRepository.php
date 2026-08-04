<?php

namespace Xima\XimaTypo3Calendar\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class EventRepository extends Repository
{
    private const TABLE = 'tx_ximatypo3calendar_domain_model_event';

    protected $defaultOrderings = [
        'publishDate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * Returns the raw event database row, or null when it does not exist.
     *
     * @return array<string, mixed>|null
     * @throws Exception
     */
    public function getEventRecordByUid(int $uid): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $eventRecord = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $eventRecord === false ? null : $eventRecord;
    }
}

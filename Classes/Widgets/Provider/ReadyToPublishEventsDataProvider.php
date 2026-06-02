<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;

readonly class ReadyToPublishEventsDataProvider implements ListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private int $limit,
    ) {
    }

    public function getItems(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');

        $qb
            ->select('e.uid', 'e.title', 'en.start_date')
            ->distinct()
            ->from('tx_ximatypo3calendar_domain_model_entry', 'en')
            ->innerJoin(
                'en',
                'tx_ximatypo3calendar_domain_model_event',
                'e',
                $qb->expr()->eq('en.event', $qb->quoteIdentifier('e.uid'))
            )
            ->where(
                $qb->expr()->eq('e.record_type', $qb->createNamedParameter('public', Connection::PARAM_STR)),
                $qb->expr()->gte('en.start_date', $qb->createNamedParameter(time(), Connection::PARAM_INT)),
            )
            ->groupBy('e.uid')
            ->setMaxResults($this->limit)
            ->orderBy('en.start_date', 'ASC');

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

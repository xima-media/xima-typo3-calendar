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
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');
        $qb->select('uid', 'title')
            ->from('tx_ximatypo3calendar_domain_model_event')
            ->where(
                $qb->expr()->eq('status', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->setMaxResults($this->limit);

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

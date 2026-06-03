<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;

readonly class UpcomingAppointmentsDataProvider implements ListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
        private int $daysInPreview = 10,
        private int $limit = 10
    ) {
    }

    public function getItems(): array
    {
        $nowTimestap = time();
        $upperBoundDate = new \DateTime();
        $upperBoundDate->add(new \DateInterval('P' . $this->daysInPreview . 'D'))->setTime(23, 59, 59);
        $upperBoundTimestamp = $upperBoundDate->getTimestamp();

        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
        $qb->select(
            'a.uid AS entry_uid',
            'a.start_date',
            'a.title AS entry_title',
            'e.uid AS event_uid',
            'e.title AS event_title'
        )
            ->from('tx_ximatypo3calendar_domain_model_entry', 'a')
            ->innerJoin(
                'a',
                'tx_ximatypo3calendar_domain_model_event',
                'e',
                $qb->expr()->eq('a.event', $qb->quoteIdentifier('e.uid'))
            )
            ->where(
                $qb->expr()->gt('a.start_date', $qb->createNamedParameter($nowTimestap, Connection::PARAM_INT)),
                $qb->expr()->lt('a.start_date', $qb->createNamedParameter($upperBoundTimestamp, Connection::PARAM_INT)),
                $qb->expr()->eq('e.status', $qb->createNamedParameter(1, Connection::PARAM_INT))
            )
            ->orderBy('a.start_date', 'ASC')
            ->setMaxResults($this->limit);

        $event = new BeforeWidgetItemsFetchedEvent($qb, self::class);
        $this->eventDispatcher->dispatch($event);
        $qb = $event->getQueryBuilder();

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

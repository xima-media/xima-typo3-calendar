<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;

readonly class ReadyToPublishEventsDataProvider implements ListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
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
                $qb->expr()->eq('e.status', $qb->createNamedParameter(EventStatus::REVIEW->value, Connection::PARAM_INT)),
                $qb->expr()->gte('en.start_date', $qb->createNamedParameter(time(), Connection::PARAM_INT)),
            )
            ->groupBy('e.uid')
            ->setMaxResults($this->limit)
            ->orderBy('en.start_date', 'ASC');

        $event = new BeforeWidgetItemsFetchedEvent($qb, self::class);
        $this->eventDispatcher->dispatch($event);
        $qb = $event->getQueryBuilder();

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

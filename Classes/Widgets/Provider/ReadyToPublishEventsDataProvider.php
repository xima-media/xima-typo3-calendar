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

        // One row per event, carrying its *earliest* upcoming appointment. Selecting
        // en.start_date bare would be rejected under ONLY_FULL_GROUP_BY, since it is not
        // functionally dependent on the grouping key.
        $qb
            ->select('e.uid', 'e.title')
            ->addSelectLiteral('MIN(' . $qb->quoteIdentifier('en.start_date') . ') AS ' . $qb->quoteIdentifier('start_date'))
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
            ->groupBy('e.uid', 'e.title')
            ->setMaxResults($this->limit)
            ->orderBy('start_date', 'ASC');

        $event = new BeforeWidgetItemsFetchedEvent($qb, self::class);
        $this->eventDispatcher->dispatch($event);
        $qb = $event->getQueryBuilder();

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

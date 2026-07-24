<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;

readonly class CanceledAppointmentsDataProvider implements ListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getItems(): array
    {
        $nowTimestap = time();

        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
        $qb->getRestrictions()->removeAll()
            ->removeByType(DeletedRestriction::class)
            ->removeByType(HiddenRestriction::class);

        $qb
            ->select(
                'en.uid AS entry_uid',
                'e.title AS event_title',
                'e.uid AS event_uid',
                'en.start_date AS entry_start_date',
            )
            ->addSelectLiteral('GROUP_CONCAT(DISTINCT r.title SEPARATOR \', \') AS requirements')
            ->from('tx_ximatypo3calendar_domain_model_entry', 'en')
            ->innerJoin(
                'en',
                'tx_ximatypo3calendar_domain_model_event',
                'e',
                $qb->expr()->eq('en.event', $qb->quoteIdentifier('e.uid'))
            )
            ->leftJoin(
                'en',
                'tx_ximatypo3calendar_domain_model_requirementbooking',
                'b',
                $qb->expr()->eq('en.uid', $qb->quoteIdentifier('b.foreign_table_parent_uid'))
            )
            ->leftJoin(
                'b',
                'tx_ximatypo3calendar_domain_model_requirement',
                'r',
                $qb->expr()->eq('b.requirement', $qb->quoteIdentifier('r.uid'))
            )
            ->groupBy('en.uid')
            ->where(
                $qb->expr()->gt('en.start_date', $qb->createNamedParameter($nowTimestap, Connection::PARAM_INT)),
                $qb->expr()->or(
                    $qb->expr()->eq('en.canceled', $qb->createNamedParameter(1, Connection::PARAM_INT)),
                    $qb->expr()->eq('en.hidden', $qb->createNamedParameter(1, Connection::PARAM_INT)),
                    $qb->expr()->eq('en.deleted', $qb->createNamedParameter(1, Connection::PARAM_INT)),
                ),
            )
            ->orderBy('en.start_date', 'ASC');

        $event = new BeforeWidgetItemsFetchedEvent($qb, self::class);
        $this->eventDispatcher->dispatch($event);
        $qb = $event->getQueryBuilder();

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

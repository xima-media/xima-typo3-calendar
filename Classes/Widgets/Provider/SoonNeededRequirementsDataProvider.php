<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;

readonly class SoonNeededRequirementsDataProvider implements ListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
        private int $daysInPreview = 2,
        private int $limit = 10
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        $nowTimestamp = time();
        $upperBoundDate = new \DateTime();
        $upperBoundDate->add(new \DateInterval('P' . $this->daysInPreview . 'D'))->setTime(23, 59, 59);
        $upperBoundTimestamp = $upperBoundDate->getTimestamp();

        $bookingStartDateExpr = 'COALESCE(NULLIF(rb.start_date, 0), en.start_date)';

        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_requirementbooking');
        $qb->select(
            'rb.uid AS booking_uid',
            'rb.amount',
            'rb.note AS booking_note',
            'r.title AS requirement_title',
            'u.name AS requirement_assignee',
            'e.uid AS event_uid',
            'e.title AS event_title',
            'en.uid AS entry_uid',
            'en.title AS entry_title',
            'l.name AS entry_location',
            'l2.name AS event_location',
        )
            ->addSelectLiteral($bookingStartDateExpr . ' AS booking_start_date')
            ->from('tx_ximatypo3calendar_domain_model_requirementbooking', 'rb')
            ->innerJoin(
                'rb',
                'tx_ximatypo3calendar_domain_model_requirement',
                'r',
                $qb->expr()->eq('rb.requirement', $qb->quoteIdentifier('r.uid'))
            )
            ->innerJoin(
                'rb',
                'tx_ximatypo3calendar_domain_model_entry',
                'en',
                $qb->expr()->eq('rb.foreign_table_parent_uid', $qb->quoteIdentifier('en.uid'))
            )
            ->innerJoin(
                'en',
                'tx_ximatypo3calendar_domain_model_event',
                'e',
                $qb->expr()->eq('en.event', $qb->quoteIdentifier('e.uid'))
            )
            ->leftJoin(
                'en',
                'tx_ximatypo3calendar_domain_model_location',
                'l',
                $qb->expr()->eq('en.location', $qb->quoteIdentifier('l.uid'))
            )
            ->leftJoin(
                'e',
                'tx_ximatypo3calendar_domain_model_location',
                'l2',
                $qb->expr()->eq('e.location', $qb->quoteIdentifier('l2.uid'))
            )
            ->leftJoin(
                'r',
                'fe_users',
                'u',
                $qb->expr()->eq('r.assignee', $qb->quoteIdentifier('u.uid'))
            )
            ->where(
                $bookingStartDateExpr . ' > ' . $qb->createNamedParameter($nowTimestamp, Connection::PARAM_INT),
                $bookingStartDateExpr . ' < ' . $qb->createNamedParameter($upperBoundTimestamp, Connection::PARAM_INT),
                $qb->expr()->eq('e.status', $qb->createNamedParameter(1, Connection::PARAM_INT))
            )
            ->orderBy('booking_start_date', 'ASC')
            ->setMaxResults($this->limit);

        $event = new BeforeWidgetItemsFetchedEvent($qb, self::class);
        $this->eventDispatcher->dispatch($event);
        $qb = $event->getQueryBuilder();

        return $qb->executeQuery()->fetchAllAssociative();
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class CalendarSelectionService
{
    private const TABLE = 'tx_ximatypo3calendar_domain_model_calendar';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @return array<int, array{uid: int, title: string}>
     */
    public function getAvailableCalendars(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('uid', 'title')
            ->from(self::TABLE)
            ->orderBy('title', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'uid' => (int)$row['uid'],
                'title' => (string)$row['title'],
            ],
            $rows,
        );
    }

    public function isAvailable(int $calendarUid): bool
    {
        if ($calendarUid <= 0) {
            return false;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $uid = $queryBuilder
            ->select('uid')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($calendarUid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $uid !== false;
    }
}

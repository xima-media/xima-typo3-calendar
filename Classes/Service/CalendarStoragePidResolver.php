<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;

final class CalendarStoragePidResolver
{
    private ?int $resolvedPid = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function resolveStoragePid(): int
    {
        if ($this->resolvedPid !== null) {
            return $this->resolvedPid;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $pid = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('module', $queryBuilder->createNamedParameter('events')))
            ->orderBy('uid')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $this->resolvedPid = (int)$pid;
    }
}

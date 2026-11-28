<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Database;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reads a record as stored, including deleted, hidden and restricted rows.
 *
 * Permission checks need the true state of the record they decide on: the event restriction
 * would hide exactly the foreign drafts a check has to refuse, and an appointment deleted a
 * moment ago still tells which event it belonged to.
 */
#[Autoconfigure(public: true)]
final readonly class RawRecordReader
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(string $table, int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->removeByType(EventRestriction::class)
            ->removeByType(EntryRestriction::class);

        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }
}

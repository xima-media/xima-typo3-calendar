<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Xima\XimaTypo3Calendar\Database\EntryRestriction;
use Xima\XimaTypo3Calendar\Event\ModifyEventSearchResultsEvent;

/**
 * Finds the events an editor can assign to a new appointment in the creation modal.
 *
 * An event is offered under the same conditions as in the event select of the appointment
 * form: its page is readable (PAGE_SHOW) and EventRestriction lets the user see it.
 *
 * @phpstan-import-type EventSearchResult from ModifyEventSearchResultsEvent
 */
final class EventSearchService
{
    public const MIN_SEARCH_LENGTH = 2;
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';
    private const RESULT_LIMIT = 10;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly Context $context,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The appointment form ignores defaults for fields the user may not edit.
     */
    public function canSearch(): bool
    {
        $backendUser = $this->getBackendUser();

        return $backendUser !== null
            && $backendUser->check('tables_select', self::EVENT_TABLE)
            && $backendUser->check('non_exclude_fields', self::ENTRY_TABLE . ':event');
    }

    /**
     * Events with upcoming appointments come first, the next one at the top, followed by
     * past events, the most recent first.
     *
     * @return list<EventSearchResult>
     */
    public function search(string $searchTerm): array
    {
        $searchTerm = trim($searchTerm);
        if (mb_strlen($searchTerm) < self::MIN_SEARCH_LENGTH || !$this->canSearch()) {
            return [];
        }

        $queryBuilder = $this->createEventQuery();
        $rows = $queryBuilder
            ->andWhere($queryBuilder->expr()->like(
                'event.title',
                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($searchTerm) . '%'),
            ))
            ->orderBy('has_upcoming', 'DESC')
            ->addOrderBy('next_start', 'ASC')
            ->addOrderBy('last_end', 'DESC')
            ->addOrderBy('event.title', 'ASC')
            ->setMaxResults(self::RESULT_LIMIT)
            ->executeQuery()
            ->fetchAllAssociative();

        $results = array_map(
            static fn (array $row): array => [
                'uid' => (int)$row['uid'],
                'title' => (string)$row['title'],
                'firstStart' => $row['first_start'] === null ? null : (int)$row['first_start'],
                'lastEnd' => (int)$row['last_end'] === 0 ? null : (int)$row['last_end'],
                'details' => [],
            ],
            $rows,
        );

        $event = new ModifyEventSearchResultsEvent($results, $searchTerm);
        $this->eventDispatcher->dispatch($event);

        return $event->getResults();
    }

    public function isSelectable(int $eventUid): bool
    {
        if ($eventUid <= 0 || !$this->canSearch()) {
            return false;
        }

        $queryBuilder = $this->createEventQuery();

        return $queryBuilder
            ->andWhere($queryBuilder->expr()->eq(
                'event.uid',
                $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchOne() !== false;
    }

    private function createEventQuery(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        // Hidden events can be assigned in the form, too. EventRestriction is enforced and
        // stays; the appointments only provide dates, so EntryRestriction does not apply.
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->removeByType(EntryRestriction::class)
            ->add(new DeletedRestriction());

        $now = $queryBuilder->createNamedParameter(
            (int)$this->context->getPropertyFromAspect('date', 'timestamp'),
            Connection::PARAM_INT,
        );

        // The end date is optional; an appointment without one ends at its start.
        $appointmentEnd = 'COALESCE(NULLIF(appointment.end_date, 0), appointment.start_date)';

        $queryBuilder
            ->select('event.uid', 'event.title')
            ->addSelectLiteral(
                'MIN(appointment.start_date) AS first_start',
                // 0 instead of NULL: databases disagree on where NULL sorts.
                'COALESCE(MAX(' . $appointmentEnd . '), 0) AS last_end',
                'MAX(CASE WHEN ' . $appointmentEnd . ' >= ' . $now . ' THEN 1 ELSE 0 END) AS has_upcoming',
                'MIN(CASE WHEN ' . $appointmentEnd . ' >= ' . $now . ' THEN appointment.start_date END) AS next_start',
            )
            ->from(self::EVENT_TABLE, 'event')
            // The permission clause names the table "pages", so the alias must match.
            ->innerJoin(
                'event',
                'pages',
                'pages',
                (string)$queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('event.pid')),
                    $queryBuilder->expr()->eq('pages.deleted', 0),
                ),
            )
            ->leftJoin(
                'event',
                self::ENTRY_TABLE,
                'appointment',
                (string)$queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('appointment.event', $queryBuilder->quoteIdentifier('event.uid')),
                    $queryBuilder->expr()->eq('appointment.deleted', 0),
                ),
            )
            ->groupBy('event.uid', 'event.title');

        $permissionClause = $this->getBackendUser()?->getPagePermsClause(Permission::PAGE_SHOW) ?? '1=0';
        $queryBuilder->where(QueryHelper::stripLogicalOperatorPrefix($permissionClause));

        return $queryBuilder;
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }
}

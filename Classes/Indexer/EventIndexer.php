<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Indexer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Database\EntryRestriction;
use Xima\XimaTypo3Calendar\Database\EventRestriction;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\ModifyKeSearchIndexEntryEvent;
use Xima\XimaTypo3Calendar\Event\ModifyKeSearchIndexerQueryEvent;
use Xima\XimaTypo3Calendar\Utility\AppointmentDateUtility;
use Xima\XimaTypo3Calendar\Utility\PlainTextUtility;

/**
 * Indexes events that are currently running or still to come into the ke_search index.
 *
 * One entry per event; the appointments define the time window and supply the sort date.
 * Deliberately full-indexing only: ke_search runs its cleanup in that mode alone, and
 * the cleanup is what drops an event once its last appointment has passed. An incremental run
 * would leave finished events in the index until the next full one.
 */
class EventIndexer extends IndexerBase
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    /**
     * Appointments may end before the window opens without the event leaving it, because the
     * exact end depends on the all-day rule. The query casts a day-wide net and
     * AppointmentDateUtility decides.
     */
    private const PREFILTER_TOLERANCE_SECONDS = 86400;

    /**
     * @param array<string, mixed> $indexerConfig
     */
    public function customIndexer(array &$indexerConfig, IndexerRunner $indexerObject): string
    {
        if (($indexerConfig['type'] ?? '') !== EventIndexerConfiguration::INDEXER_TYPE) {
            return '';
        }

        $now = new \DateTimeImmutable();
        $languageId = $this->resolveDefaultLanguageId((int)($indexerConfig['targetpid'] ?? 0));
        if ($languageId === null) {
            return 'No site found for the configured target page, nothing indexed.';
        }

        $indexPastEvents = (bool)($indexerConfig['ximatypo3calendar_index_past_events'] ?? false);

        $admitted = $this->findAdmittedAppointments($indexerConfig, $now, $indexPastEvents);
        if ($admitted === []) {
            return 'No events found!';
        }

        $appointmentsByEvent = $this->fetchAppointments(array_merge(...array_values($admitted)));
        $eventUids = array_keys($admitted);
        $totalCount = count($eventUids);
        $counter = 0;
        $storedCount = 0;

        foreach ($this->fetchEvents($eventUids) as $eventRow) {
            $this->indexerStatusService->setRunningStatus($this->indexerConfig, $counter++, $totalCount);

            $appointments = $appointmentsByEvent[(int)$eventRow['uid']] ?? [];
            if (!$indexPastEvents && !$this->hasAppointmentInWindow($appointments, $now)) {
                continue;
            }

            $storedCount += (int)$this->storeEvent($eventRow, $appointments, $languageId, $indexerConfig, $now);
        }

        return $storedCount . ' entries have been indexed.';
    }

    /**
     * The appointments that may keep an event in the index, grouped by event uid.
     *
     * Selecting the appointments rather than the events alone is what lets a listener constrain
     * the appointment side as well: the time window and the sort date are then measured against
     * exactly the rows it admitted.
     *
     * @param array<string, mixed> $indexerConfig
     * @return array<int, list<int>>
     */
    private function findAdmittedAppointments(
        array $indexerConfig,
        \DateTimeImmutable $now,
        bool $indexPastEvents
    ): array {
        $indexPids = $this->getPagelist(
            (string)($indexerConfig['startingpoints_recursive'] ?? ''),
            (string)($indexerConfig['sysfolder'] ?? '')
        );
        if ($indexPids === []) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder();
        $appointmentEnd = 'COALESCE(NULLIF(' . $queryBuilder->quoteIdentifier('a.end_date') . ', 0), '
            . $queryBuilder->quoteIdentifier('a.start_date') . ')';

        $constraints = [
            $queryBuilder->expr()->eq('e.deleted', 0),
            $queryBuilder->expr()->eq('e.hidden', 0),
            $queryBuilder->expr()->eq(
                'e.status',
                $queryBuilder->createNamedParameter(EventStatus::LIVE->value, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->in(
                'e.pid',
                $queryBuilder->createNamedParameter($indexPids, Connection::PARAM_INT_ARRAY)
            ),
            $queryBuilder->expr()->eq('a.deleted', 0),
            $queryBuilder->expr()->eq('a.hidden', 0),
        ];

        if (!$indexPastEvents) {
            $constraints[] = $appointmentEnd . ' >= ' . $queryBuilder->createNamedParameter(
                $now->getTimestamp() - self::PREFILTER_TOLERANCE_SECONDS,
                Connection::PARAM_INT
            );
        }

        $queryBuilder
            ->select('a.uid AS appointment_uid', 'e.uid AS event_uid')
            ->from(self::EVENT_TABLE, 'e')
            ->innerJoin('e', self::ENTRY_TABLE, 'a', 'a.event = e.uid')
            ->where(...$constraints);

        $event = new ModifyKeSearchIndexerQueryEvent($queryBuilder, $indexerConfig, $now->getTimestamp());
        GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch($event);

        $rows = $event->getQueryBuilder()->executeQuery()->fetchAllAssociative();

        $admitted = [];
        foreach ($rows as $row) {
            $admitted[(int)$row['event_uid']][] = (int)$row['appointment_uid'];
        }

        return $admitted;
    }

    /**
     * @param list<int> $eventUids
     * @return list<array<string, mixed>>
     */
    private function fetchEvents(array $eventUids): array
    {
        $queryBuilder = $this->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from(self::EVENT_TABLE)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($eventUids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param list<int> $appointmentUids
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchAppointments(array $appointmentUids): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $rows = $queryBuilder
            ->select('a.*', 'l.name AS location_name')
            ->from(self::ENTRY_TABLE, 'a')
            ->leftJoin('a', 'tx_ximatypo3calendar_domain_model_location', 'l', 'a.location = l.uid')
            ->where(
                $queryBuilder->expr()->in(
                    'a.uid',
                    $queryBuilder->createNamedParameter($appointmentUids, Connection::PARAM_INT_ARRAY)
                )
            )
            ->orderBy('a.start_date')
            ->executeQuery()
            ->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['event']][] = $row;
        }

        return $grouped;
    }

    /**
     * Both restrictions implement EnforceableQueryRestrictionInterface and therefore survive
     * removeAll(). Leaving them in place would make the result depend on who triggers the run:
     * exempt on the command line, filtered for a backend user without the view-all permission.
     */
    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::EVENT_TABLE);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->removeByType(EventRestriction::class)
            ->removeByType(EntryRestriction::class);

        return $queryBuilder;
    }

    /**
     * @param list<array<string, mixed>> $appointments
     */
    private function hasAppointmentInWindow(array $appointments, \DateTimeImmutable $now): bool
    {
        foreach ($appointments as $appointment) {
            $end = AppointmentDateUtility::getEndFromRow($appointment);
            if ($end !== null && $end >= $now) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $eventRow
     * @param list<array<string, mixed>> $appointments
     * @param array<string, mixed> $indexerConfig
     */
    private function storeEvent(
        array $eventRow,
        array $appointments,
        int $languageId,
        array $indexerConfig,
        \DateTimeImmutable $now
    ): bool {
        $tags = '';
        SearchHelper::makeSystemCategoryTags($tags, (int)$eventRow['uid'], self::EVENT_TABLE);

        $representative = AppointmentDateUtility::pickRepresentativeRow($appointments, $now);

        $additionalFields = [
            'orig_uid' => (int)$eventRow['uid'],
            'orig_pid' => (int)$eventRow['pid'],
            'sortdate' => (int)($representative['start_date'] ?? $eventRow['tstamp'] ?? 0),
        ];

        $event = new ModifyKeSearchIndexEntryEvent(
            (string)$eventRow['title'],
            $this->buildContent($eventRow, $appointments),
            '',
            $tags,
            $this->buildParams((int)$eventRow['uid']),
            (int)($indexerConfig['targetpid'] ?? 0),
            $additionalFields,
            $eventRow,
            $appointments,
            $languageId
        );
        GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch($event);

        return $this->pObj->storeInIndex(
            $indexerConfig['storagepid'],
            $event->getTitle(),
            EventIndexerConfiguration::INDEXER_TYPE,
            (string)$event->getTargetPid(),
            $event->getContent(),
            $event->getTags(),
            $event->getParams(),
            $event->getAbstract(),
            $languageId,
            0,
            0,
            '',
            false,
            $event->getAdditionalFields()
        );
    }

    /**
     * @param array<string, mixed> $eventRow
     * @param list<array<string, mixed>> $appointments
     */
    private function buildContent(array $eventRow, array $appointments): string
    {
        $parts = [
            $eventRow['title'] ?? '',
            $eventRow['description'] ?? '',
            $eventRow['additional_information'] ?? '',
            $eventRow['fee'] ?? '',
            $eventRow['registration_address'] ?? '',
        ];

        foreach ($appointments as $appointment) {
            $parts[] = $appointment['title'] ?? '';
            $parts[] = $appointment['description'] ?? '';
            $parts[] = $appointment['speakers'] ?? '';
            $parts[] = $appointment['address'] ?? '';
            $parts[] = $appointment['directions'] ?? '';
            $parts[] = $appointment['location_name'] ?? '';
        }

        $parts = array_filter(array_map(
            static fn (mixed $part): string => trim(PlainTextUtility::fromHtml((string)$part)),
            $parts
        ));

        return implode("\n", array_unique($parts));
    }

    private function buildParams(int $eventUid): string
    {
        return '&' . rawurldecode(http_build_query([
            'tx_ximatypo3calendar_eventdetail' => [
                'event' => $eventUid,
                'controller' => 'Event',
                'action' => 'show',
            ],
        ]));
    }

    /**
     * Events are not translatable, and the `language` field states the language an event is held
     * in rather than a translation, so there is only ever the default language to index into.
     */
    private function resolveDefaultLanguageId(int $targetPid): ?int
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($targetPid);
        } catch (SiteNotFoundException) {
            return null;
        }

        return $site->getDefaultLanguage()->getLanguageId();
    }
}

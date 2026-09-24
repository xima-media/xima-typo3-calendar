<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CalendarPendingCreationService
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';
    private const PENDING_EVENTS_SESSION_KEY = 'xima_calendar_pending_events';
    private const PENDING_ENTRIES_SESSION_KEY = 'xima_calendar_pending_entries';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function registerEvent(int $eventUid, int $pid): void
    {
        $this->register(self::PENDING_EVENTS_SESSION_KEY, $eventUid, $pid);
    }

    public function registerEntry(int $entryUid, int $pid): void
    {
        $this->register(self::PENDING_ENTRIES_SESSION_KEY, $entryUid, $pid);
    }

    public function cleanupEvent(int $eventUid, int $expectedPid): bool
    {
        if (!$this->isPending(self::PENDING_EVENTS_SESSION_KEY, $eventUid, $expectedPid)) {
            return false;
        }

        $event = $this->fetchRecord(self::EVENT_TABLE, $eventUid, $expectedPid, [
            'title', 'description', 'additional_information', 'slug', 'url', 'registration_link',
        ]);

        if ($event === false) {
            $this->forget(self::PENDING_EVENTS_SESSION_KEY, $eventUid);
            return true;
        }

        $entryQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::ENTRY_TABLE);
        $entries = $entryQueryBuilder
            ->select('uid', 'title', 'description', 'online_link', 'ticket_link', 'address', 'directions')
            ->from(self::ENTRY_TABLE)
            ->where($entryQueryBuilder->expr()->eq(
                'event',
                $entryQueryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchAllAssociative();

        $hasContent = $this->hasContent($event, [
            'title', 'description', 'additional_information', 'registration_link',
        ]) || array_reduce(
            $entries,
            fn (bool $hasEntryContent, array $entry): bool => $hasEntryContent || $this->hasContent($entry, [
                'title', 'description', 'online_link', 'ticket_link', 'address', 'directions',
            ]),
            false,
        );
        if ($hasContent) {
            $this->forget(self::PENDING_EVENTS_SESSION_KEY, $eventUid);
            return true;
        }

        $commandMap = [self::EVENT_TABLE => [$eventUid => ['delete' => 1]]];
        foreach ($entries as $entry) {
            $commandMap[self::ENTRY_TABLE][(int)$entry['uid']] = ['delete' => 1];
        }

        if (!$this->delete($commandMap)) {
            return false;
        }

        $this->forget(self::PENDING_EVENTS_SESSION_KEY, $eventUid);
        return true;
    }

    public function cleanupEntry(int $entryUid, int $expectedPid): bool
    {
        if (!$this->isPending(self::PENDING_ENTRIES_SESSION_KEY, $entryUid, $expectedPid)) {
            return false;
        }

        $entry = $this->fetchRecord(self::ENTRY_TABLE, $entryUid, $expectedPid, [
            'title', 'description', 'online_link', 'ticket_link', 'address', 'directions',
        ]);

        if ($entry === false || $this->hasContent($entry, [
            'title', 'description', 'online_link', 'ticket_link', 'address', 'directions',
        ])) {
            $this->forget(self::PENDING_ENTRIES_SESSION_KEY, $entryUid);
            return true;
        }

        if (!$this->delete([self::ENTRY_TABLE => [$entryUid => ['delete' => 1]]])) {
            return false;
        }

        $this->forget(self::PENDING_ENTRIES_SESSION_KEY, $entryUid);
        return true;
    }

    private function delete(array $commandMap): bool
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $commandMap);
        $dataHandler->process_cmdmap();

        return $dataHandler->errorLog === [];
    }

    /**
     * @param list<string> $fields
     * @return array<string, mixed>|false
     */
    private function fetchRecord(string $table, int $uid, int $pid, array $fields): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);

        return $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
    }

    private function register(string $key, int $uid, int $pid): void
    {
        $backendUser = $this->getBackendUser();
        if ($uid <= 0 || $pid <= 0 || $backendUser === null) {
            return;
        }

        $pending = $backendUser->getSessionData($key);
        $pending = is_array($pending) ? $pending : [];
        $pending[$uid] = $pid;
        $backendUser->setAndSaveSessionData($key, $pending);
    }

    private function isPending(string $key, int $uid, int $pid): bool
    {
        $pending = $this->getBackendUser()?->getSessionData($key);
        return is_array($pending) && (int)($pending[$uid] ?? 0) === $pid;
    }

    private function forget(string $key, int $uid): void
    {
        $backendUser = $this->getBackendUser();
        $pending = $backendUser?->getSessionData($key);
        if (!is_array($pending) || !array_key_exists($uid, $pending)) {
            return;
        }

        unset($pending[$uid]);
        $backendUser->setAndSaveSessionData($key, $pending);
    }

    private function hasContent(array $record, array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim((string)($record[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }
}

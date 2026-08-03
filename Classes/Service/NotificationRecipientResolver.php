<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Resolves who receives a workflow notification.
 *
 * Owners resolve to both the frontend owner and owner_be_user, deduplicated by e-mail. Backend
 * subscribers opt in per preference in their user settings; an *empty* category selection means
 * "all categories", not "none".
 */
final readonly class NotificationRecipientResolver
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const CATEGORY_MM_TABLE = 'sys_category_record_mm';

    public const PREFERENCE_REVIEW = 'tx_ximatypo3calendar_notify_review';
    public const PREFERENCE_LIVE = 'tx_ximatypo3calendar_notify_live';
    private const PREFERENCE_CATEGORIES = 'tx_ximatypo3calendar_notify_categories';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    /**
     * Backend users that enabled $preferenceField and — if they restricted
     * themselves to categories — share at least one category with the event.
     *
     * @return array<int, array{email: string, name: string, isBackendUser: bool}>
     */
    public function getSubscribedBackendRecipients(int $eventUid, string $preferenceField): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $rows = $queryBuilder
            ->select('uid', 'email', 'realName', 'username')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->isNotNull('email'),
                $queryBuilder->expr()->neq('email', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->eq($preferenceField, $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            return [];
        }

        $eventCategoryUids = $this->fetchEventCategoryUids($eventUid);
        $beUserUids = array_map(static fn (array $row): int => (int)$row['uid'], $rows);
        $categorySubscriptionsByUser = $this->fetchBackendUserCategorySubscriptions($beUserUids);

        $recipients = [];
        foreach ($rows as $row) {
            $beUserUid = (int)$row['uid'];
            $selectedCategoryUids = $categorySubscriptionsByUser[$beUserUid] ?? [];
            if ($selectedCategoryUids !== [] && array_intersect($selectedCategoryUids, $eventCategoryUids) === []) {
                continue;
            }

            $email = trim((string)($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }

            $normalizedEmail = mb_strtolower($email);
            if (isset($recipients[$normalizedEmail])) {
                continue;
            }

            $name = trim((string)($row['realName'] ?? ''));
            if ($name === '') {
                $name = trim((string)($row['username'] ?? ''));
            }
            $recipients[$normalizedEmail] = [
                'email' => $email,
                'name' => $name,
                'isBackendUser' => true,
            ];
        }

        return array_values($recipients);
    }

    /**
     * Resolves every owner recipient of an event. Ownership can be held by a
     * frontend user (`owner`), a backend user (`owner_be_user`) or, in edge
     * cases, both. Every distinct address is returned, deduplicated by email.
     *
     * When $excludeBackendUserUid is given, a backend user owner matching that
     * uid is left out — this suppresses the self-notification a backend user
     * would otherwise receive for changing the status of their own event.
     *
     * @param array<string, mixed> $eventRecord
     * @return array<int, array{email: string, name: string, isBackendUser: bool}>
     */
    public function getOwnerRecipients(array $eventRecord, int $excludeBackendUserUid = 0): array
    {
        $recipients = [];
        $feOwner = $this->getOwnerRecipient((int)($eventRecord['owner'] ?? 0));

        $beOwnerUid = (int)($eventRecord['owner_be_user'] ?? 0);
        $beOwner = ($excludeBackendUserUid > 0 && $beOwnerUid === $excludeBackendUserUid)
            ? null
            : $this->getBackendOwnerRecipient($beOwnerUid);

        foreach ([$feOwner, $beOwner] as $recipient) {
            if ($recipient === null) {
                continue;
            }
            $recipients[mb_strtolower($recipient['email'])] = $recipient;
        }

        return array_values($recipients);
    }

    /**
     * @return array{email: string, name: string, isBackendUser: bool}|null
     */
    public function getOwnerRecipient(int $ownerUid): ?array
    {
        if ($ownerUid <= 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $row = $queryBuilder
            ->select('email', 'first_name', 'last_name', 'username')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($ownerUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        $email = trim((string)($row['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        $firstName = trim((string)($row['first_name'] ?? ''));
        $lastName = trim((string)($row['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') {
            $name = trim((string)($row['username'] ?? ''));
        }

        return [
            'email' => $email,
            'name' => $name,
            'isBackendUser' => false,
        ];
    }

    /**
     * Resolves a backend user owner (`owner_be_user`) to an email recipient.
     *
     * @return array{email: string, name: string, isBackendUser: bool}|null
     */
    public function getBackendOwnerRecipient(int $backendUserUid): ?array
    {
        if ($backendUserUid <= 0) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $row = $queryBuilder
            ->select('email', 'realName', 'username')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        $email = trim((string)($row['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        $name = trim((string)($row['realName'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['username'] ?? ''));
        }

        return [
            'email' => $email,
            'name' => $name,
            'isBackendUser' => true,
        ];
    }

    /**
     * @return int[]
     */
    private function fetchEventCategoryUids(int $eventUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $rows = $queryBuilder
            ->select('uid_local')
            ->from(self::CATEGORY_MM_TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::EVENT_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('categories')),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $rows);
    }

    /**
     * @param int[] $beUserUids
     * @return array<int, int[]>
     */
    private function fetchBackendUserCategorySubscriptions(array $beUserUids): array
    {
        if ($beUserUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $rows = $queryBuilder
            ->select('uid_foreign', 'uid_local')
            ->from(self::CATEGORY_MM_TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('be_users')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::PREFERENCE_CATEGORIES)),
                $queryBuilder->expr()->in(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($beUserUids, ArrayParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $subscriptions = [];
        foreach ($rows as $row) {
            $beUserUid = (int)$row['uid_foreign'];
            $categoryUid = (int)$row['uid_local'];
            $subscriptions[$beUserUid][] = $categoryUid;
        }

        return $subscriptions;
    }
}

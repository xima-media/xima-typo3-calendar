<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Resolves the audiences for the event workflow notifications.
 *
 * Owns every database lookup behind the notifications: the event record itself,
 * the backend users that opted into a given workflow preference (filtered by
 * their subscribed categories) and the front-end owner of a rejected event.
 * Keeping this apart from the event listener isolates the data access from the
 * workflow policy and makes both testable on their own.
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
     * @return array<string, mixed>|null
     */
    public function getEventRecord(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::EVENT_TABLE);
        $eventRecord = $queryBuilder
            ->select('*')
            ->from(self::EVENT_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $eventRecord === false ? null : $eventRecord;
    }

    /**
     * Backend users that enabled $preferenceField and — if they restricted
     * themselves to categories — share at least one category with the event.
     *
     * @return array<int, array{email: string, name: string}>
     */
    public function getBackendRecipients(int $eventUid, string $preferenceField): array
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
            ];
        }

        return array_values($recipients);
    }

    /**
     * @return array{email: string, name: string}|null
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

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use Doctrine\DBAL\ArrayParameterType;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;

#[AsEventListener(
    identifier: 'xima-typo3-calendar/event-workflow-notification',
)]
final readonly class EventWorkflowNotification
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const BE_USER_NOTIFY_REVIEW = 'tx_ximatypo3calendar_notify_review';
    private const BE_USER_NOTIFY_LIVE = 'tx_ximatypo3calendar_notify_live';
    private const BE_USER_NOTIFY_CATEGORIES = 'tx_ximatypo3calendar_notify_categories';

    public function __construct(
        private ConnectionPool $connectionPool,
        private MailerInterface $mailer,
        private UriBuilder $backendUriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    public function __invoke(EventChangedEvent $event): void
    {
        if ($event->changeType !== ChangeType::UPDATED) {
            return;
        }
        $eventRecord = $this->fetchEventRecord($event->uid);
        if ($eventRecord === null) {
            return;
        }

        if (array_key_exists('status', $event->changedFields)) {
            $newValue = (int)($event->changedFields['status']['new'] ?? EventStatus::DRAFT->value);
            if ($newValue === EventStatus::REVIEW->value) {
                $this->sendNotificationToBackendUsers(
                    $event->uid,
                    'EventReviewNotification',
                    self::BE_USER_NOTIFY_REVIEW
                );
                return;
            }

            if ($newValue === EventStatus::REJECTED->value) {
                $this->sendNotificationToOwner($event->uid, 'EventRejectedNotification');
                return;
            }
        }

        if ($this->isLiveEventUpdate($event->changedFields, $eventRecord)) {
            $changedFieldLabels = $this->resolveChangedFieldLabels(array_keys($event->changedFields));
            $this->sendNotificationToBackendUsers(
                $event->uid,
                'EventLiveNotification',
                self::BE_USER_NOTIFY_LIVE,
                $changedFieldLabels
            );
        }
    }

    /**
     * @param string[] $changedFieldLabels
     */
    private function sendNotificationToBackendUsers(
        int $uid,
        string $template,
        string $statusPreferenceField,
        array $changedFieldLabels = []
    ): void {
        $recipients = $this->resolveBackendRecipients($uid, $statusPreferenceField);
        if ($recipients === []) {
            return;
        }

        $eventRecord = $this->fetchEventRecord($uid);
        if ($eventRecord === null) {
            return;
        }

        $email = GeneralUtility::makeInstance(FluidEmail::class)
            ->format(FluidEmail::FORMAT_HTML)
            ->setTemplate($template)
            ->assignMultiple([
                'eventUid' => $uid,
                'eventTitle' => $eventRecord['title'] ?? '',
                'eventEditUrl' => $this->buildAbsoluteEditUrl($uid),
                'changedFieldLabels' => $changedFieldLabels,
            ]);

        foreach ($recipients as $recipient) {
            $email->addTo(new Address($recipient['email'], $recipient['name']));
        }

        $request = $this->getRequest();
        if ($request instanceof ServerRequestInterface) {
            $email->setRequest($request);
        }

        $this->mailer->send($email);
    }

    /**
     * @return array<int, array{email: string, name: string}>
     */
    private function resolveBackendRecipients(int $eventUid, string $statusPreferenceField): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $rows = $queryBuilder
            ->select('uid', 'email', 'realName', 'username')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('disable', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('email'),
                $queryBuilder->expr()->neq('email', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->eq($statusPreferenceField, $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
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
     * @return int[]
     */
    private function fetchEventCategoryUids(int $eventUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category_record_mm');
        $rows = $queryBuilder
            ->select('uid_local')
            ->from('sys_category_record_mm')
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

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category_record_mm');
        $rows = $queryBuilder
            ->select('uid_foreign', 'uid_local')
            ->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('be_users')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::BE_USER_NOTIFY_CATEGORIES)),
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

    private function fetchEventRecord(int $uid): ?array
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

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }

    private function sendNotificationToOwner(int $uid, string $template): void
    {
        $eventRecord = $this->fetchEventRecord($uid);
        if ($eventRecord === null) {
            return;
        }

        $recipient = $this->resolveOwnerRecipient((int)($eventRecord['owner'] ?? 0));
        if ($recipient === null) {
            return;
        }

        $email = GeneralUtility::makeInstance(FluidEmail::class)
            ->format(FluidEmail::FORMAT_HTML)
            ->setTemplate($template)
            ->assignMultiple([
                'eventUid' => $uid,
                'eventTitle' => $eventRecord['title'] ?? '',
                'eventEditUrl' => $this->buildAbsoluteEditUrl($uid),
            ])
            ->addTo(new Address($recipient['email'], $recipient['name']));

        $request = $this->getRequest();
        if ($request instanceof ServerRequestInterface) {
            $email->setRequest($request);
        }

        $this->mailer->send($email);
    }

    /**
     * @return array{email: string, name: string}|null
     */
    private function resolveOwnerRecipient(int $ownerUid): ?array
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

    private function buildAbsoluteEditUrl(int $uid): string
    {
        $returnUrl = (string)$this->backendUriBuilder->buildUriFromRoute('dashboard');
        return (string)$this->backendUriBuilder->buildUriFromRoute(
            'record_edit',
            [
                'edit' => [
                    self::EVENT_TABLE => [
                        $uid => 'edit',
                    ],
                ],
                'returnUrl' => $returnUrl,
            ],
            UriBuilder::ABSOLUTE_URL
        );
    }

    /**
     * @param array<string, array{old?: mixed, new?: mixed}> $changedFields
     * @param array<string, mixed> $eventRecord
     */
    private function isLiveEventUpdate(array $changedFields, array $eventRecord): bool
    {
        if ((int)($eventRecord['status'] ?? EventStatus::DRAFT->value) !== EventStatus::LIVE->value) {
            return false;
        }

        if (!array_key_exists('status', $changedFields)) {
            return true;
        }

        $oldStatus = (int)($changedFields['status']['old'] ?? EventStatus::DRAFT->value);
        $newStatus = (int)($changedFields['status']['new'] ?? EventStatus::DRAFT->value);
        if ($oldStatus !== EventStatus::LIVE->value && $newStatus === EventStatus::LIVE->value) {
            return false;
        }

        return true;
    }

    /**
     * @param string[] $changedFieldNames
     * @return string[]
     */
    private function resolveChangedFieldLabels(array $changedFieldNames): array
    {
        $labels = [];

        foreach ($changedFieldNames as $fieldName) {
            if ($fieldName === '') {
                continue;
            }

            $label = (string)($GLOBALS['TCA'][self::EVENT_TABLE]['columns'][$fieldName]['label'] ?? '');
            if ($label === '') {
                $labels[] = $fieldName;
                continue;
            }

            $labels[] = $this->translateLabel($label);
        }

        return array_values(array_unique($labels));
    }

    private function translateLabel(string $label): string
    {
        if (!str_starts_with($label, 'LLL:')) {
            return $label;
        }

        /** @var BackendUserAuthentication|null $backendUser */
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $languageService = $this->languageServiceFactory->createFromUserPreferences($backendUser);
        $translated = trim((string)$languageService->sL($label));
        if ($translated !== '') {
            return $translated;
        }

        return $label;
    }
}

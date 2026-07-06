<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        private ConnectionPool $connectionPool,
        private MailerInterface $mailer,
        private UriBuilder $backendUriBuilder,
    ) {
    }

    public function __invoke(EventChangedEvent $event): void
    {
        if (!$this->isWorkflowNotificationEnabled()) {
            return;
        }
        if ($event->changeType !== ChangeType::UPDATED) {
            return;
        }
        if (!array_key_exists('status', $event->changedFields)) {
            return;
        }

        $newValue = $event->changedFields['status']['new'] ?? null;
        if ((int)$newValue === EventStatus::REVIEW->value) {
            $this->sendNotificationToReviewer($event->uid);
            return;
        }

        if ((int)$newValue === EventStatus::REJECTED->value) {
            $this->sendNotificationToOwner($event->uid, 'EventRejectedNotification');
            return;
        }

        if ((int)$newValue === EventStatus::LIVE->value) {
            $this->sendNotificationToOwner($event->uid, 'EventLiveNotification');
        }
    }

    private function sendNotificationToReviewer(int $uid): void
    {
        $reviewerGroupUid = (int)$this->extensionConfiguration->get(
            'xima_typo3_calendar',
            'notifications/reviewerGroup'
        );
        if ($reviewerGroupUid <= 0) {
            return;
        }

        $recipients = $this->resolveReviewerRecipients($reviewerGroupUid);
        if ($recipients === []) {
            return;
        }

        $eventRecord = $this->fetchEventRecord($uid);

        $email = GeneralUtility::makeInstance(FluidEmail::class)
            ->format(FluidEmail::FORMAT_HTML)
            ->setTemplate('EventReviewNotification')
            ->assignMultiple([
                'eventUid' => $uid,
                'eventTitle' => $eventRecord['title'] ?? '',
                'reviewerGroupUid' => $reviewerGroupUid,
                'eventEditUrl' => $this->buildAbsoluteEditUrl($uid),
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
    private function resolveReviewerRecipients(int $reviewerGroupUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $rows = $queryBuilder
            ->select('email', 'realName', 'username')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->isNotNull('email'),
                $queryBuilder->expr()->neq('email', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->inSet('usergroup', (string)$reviewerGroupUid)
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $recipients = [];
        foreach ($rows as $row) {
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

    private function fetchEventRecord(int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');
        $eventRecord = $queryBuilder
            ->select('uid', 'title', 'owner')
            ->from('tx_ximatypo3calendar_domain_model_event')
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

    private function isWorkflowNotificationEnabled(): bool
    {
        return (int)$this->extensionConfiguration->get(
            'xima_typo3_calendar',
            'notifications/enabled'
        ) === 1;
    }

    private function buildAbsoluteEditUrl(int $uid): string
    {
        $returnUrl = (string)$this->backendUriBuilder->buildUriFromRoute('dashboard');
        return (string)$this->backendUriBuilder->buildUriFromRoute(
            'record_edit',
            [
                'edit' => [
                    'tx_ximatypo3calendar_domain_model_event' => [
                        $uid => 'edit',
                    ],
                ],
                'returnUrl' => $returnUrl,
            ],
            UriBuilder::ABSOLUTE_URL
        );
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Service\NotificationMailService;
use Xima\XimaTypo3Calendar\Service\NotificationRecipientResolver;

/**
 * Turns event workflow status changes into notification emails.
 *
 * This listener only holds the workflow policy — which template goes to which
 * audience for a given status transition — and assembles the template variables.
 * Recipient lookups are delegated to {@see NotificationRecipientResolver} and the
 * actual delivery to {@see NotificationMailService}.
 */
#[AsEventListener(
    identifier: 'xima-typo3-calendar/event-workflow-notification',
)]
final readonly class EventWorkflowNotification
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const LLL = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:';

    /**
     * Labels every notification template needs, resolved in PHP and assigned as
     * variables. The templates must not use f:translate: they are rendered from
     * the DataHandler flow (including CLI/scheduler) where no TSFE exists.
     */
    private const SHARED_EMAIL_LABELS = [
        'eventUidLabel' => 'email.eventUid',
        'eventTitleLabel' => 'email.eventTitle',
        'openBackendLabel' => 'email.openBackend',
    ];

    /** @var array<string, array<string, string>> template => (assignName => LLL key) */
    private const TEMPLATE_EMAIL_LABELS = [
        'EventLiveNotification' => [
            'subjectLabel' => 'email.live.subject',
            'titleLabel' => 'email.live.title',
            'introLabel' => 'email.live.intro',
            'changedFieldsLabel' => 'email.live.changedFields',
        ],
        'EventReviewNotification' => [
            'subjectLabel' => 'email.review.subject',
            'titleLabel' => 'email.review.title',
            'introLabel' => 'email.review.intro',
        ],
        'EventRejectedNotification' => [
            'subjectLabel' => 'email.rejected.subject',
            'titleLabel' => 'email.rejected.title',
            'introLabel' => 'email.rejected.intro',
        ],
    ];

    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private NotificationMailService $mailService,
        private UriBuilder $backendUriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    public function __invoke(EventChangedEvent $event): void
    {
        if ($event->changeType !== ChangeType::UPDATED) {
            return;
        }
        $eventRecord = $this->recipientResolver->getEventRecord($event->uid);
        if ($eventRecord === null) {
            return;
        }

        if (array_key_exists('status', $event->changedFields)) {
            $newStatus = (int)($event->changedFields['status']['new'] ?? EventStatus::DRAFT->value);

            if ($newStatus === EventStatus::REVIEW->value) {
                $recipients = $this->recipientResolver->getBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_REVIEW);
                $this->sendNotification('EventReviewNotification', $recipients, $event->uid, $eventRecord);
                return;
            }

            if ($newStatus === EventStatus::REJECTED->value) {
                $owner = $this->recipientResolver->getOwnerRecipient((int)($eventRecord['owner'] ?? 0));
                $this->sendNotification('EventRejectedNotification', $owner === null ? [] : [$owner], $event->uid, $eventRecord);
                return;
            }
        }

        if ($this->isLiveEventUpdate($event->changedFields, $eventRecord)) {
            $recipients = $this->recipientResolver->getBackendRecipients($event->uid, NotificationRecipientResolver::PREFERENCE_LIVE);
            $changedFieldLabels = $this->resolveChangedFieldLabels(array_keys($event->changedFields));
            $this->sendNotification('EventLiveNotification', $recipients, $event->uid, $eventRecord, $changedFieldLabels);
        }
    }

    /**
     * @param array<int, array{email: string, name: string}> $recipients
     * @param array<string, mixed> $eventRecord
     * @param string[] $changedFieldLabels
     */
    private function sendNotification(
        string $template,
        array $recipients,
        int $uid,
        array $eventRecord,
        array $changedFieldLabels = []
    ): void {
        if ($recipients === []) {
            return;
        }

        $assignments = [
            'eventUid' => $uid,
            'eventTitle' => $eventRecord['title'] ?? '',
            'eventEditUrl' => $this->buildAbsoluteEditUrl($uid),
            'changedFieldLabels' => $changedFieldLabels,
            ...$this->resolveEmailLabels($template),
        ];

        $this->mailService->sendToRecipients($template, $recipients, $assignments);
    }

    /**
     * Resolves the localized template labels in PHP (CLI-safe, no TSFE required)
     * so the Fluid templates can render them as plain variables.
     *
     * @return array<string, string>
     */
    private function resolveEmailLabels(string $template): array
    {
        $labels = [];
        $keys = array_merge(self::SHARED_EMAIL_LABELS, self::TEMPLATE_EMAIL_LABELS[$template] ?? []);
        foreach ($keys as $assignName => $key) {
            $labels[$assignName] = $this->translateLabel(self::LLL . $key);
        }

        return $labels;
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

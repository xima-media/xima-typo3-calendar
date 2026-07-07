<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds and sends the calendar workflow notification emails.
 *
 * Owns everything about turning a workflow event into delivered mail: assembling
 * the template variables (edit link, localized labels, changed-field labels),
 * per-recipient delivery, and transport error handling. Every recipient receives
 * their own message and a failing send never propagates out — a broken address or
 * transport must not abort the DataHandler save that triggered the notification.
 */
final readonly class NotificationMailService
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
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private UriBuilder $backendUriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    /**
     * Assembles the template variables for one workflow notification and sends it
     * to every recipient. Does nothing when there are no recipients.
     *
     * @param array<int, array{email: string, name: string}> $recipients
     * @param string[] $changedFieldNames
     */
    public function sendNotification(
        string $template,
        array $recipients,
        array $eventRecord,
        array $changedFieldNames = []
    ): void {
        if ($recipients === []) {
            return;
        }

        $eventUid = (int)($eventRecord['uid'] ?? 0);
        $eventTitle = (string)($eventRecord['title'] ?? '');

        $assignments = [
            'eventUid' => $eventUid,
            'eventTitle' => $eventTitle,
            'eventEditUrl' => $this->buildAbsoluteEditUrl($eventUid),
            'changedFieldLabels' => $this->resolveChangedFieldLabels($changedFieldNames),
            ...$this->resolveEmailLabels($template),
        ];

        $this->sendToRecipients($template, $recipients, $assignments);
    }

    /**
     * @param array<int, array{email: string, name: string}> $recipients
     * @param array<string, mixed> $assignments
     */
    private function sendToRecipients(string $template, array $recipients, array $assignments): void
    {
        $request = $this->getRequest();

        foreach ($recipients as $recipient) {
            try {
                $email = GeneralUtility::makeInstance(FluidEmail::class)
                    ->format(FluidEmail::FORMAT_HTML)
                    ->setTemplate($template)
                    ->assignMultiple($assignments)
                    ->addTo(new Address($recipient['email'], $recipient['name']));

                if ($request instanceof ServerRequestInterface) {
                    $email->setRequest($request);
                }

                $this->mailer->send($email);
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to send calendar workflow notification', [
                    'template' => $template,
                    'recipient' => $recipient['email'],
                    'exception' => $exception,
                ]);
            }
        }
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

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}

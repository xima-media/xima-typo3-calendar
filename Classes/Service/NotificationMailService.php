<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class NotificationMailService
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const LLL = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:';

    private const BACKEND_EDIT_PATH = '/typo3/record/edit';
    private const BACKEND_MODULE = 'calendar_events';
    private const BACKEND_MODULE_PATH = '/typo3/module/calendar/events';

    /**
     * Labels every notification template needs, resolved in PHP and assigned as
     * variables. The templates must not use f:translate: they are rendered from
     * the DataHandler flow (including CLI/scheduler) where no TSFE exists.
     */
    private const SHARED_EMAIL_LABELS = [
        'eventUidLabel' => 'email.eventUid',
        'eventTitleLabel' => 'email.eventTitle',
        'statusMessageLabel' => 'email.statusMessage',
        'changedByLabel' => 'email.changedBy',
    ];

    /**
     * Templates whose recipient is the event owner (a frontend user without backend
     * access). They must not contain a backend edit link.
     *
     * @var string[]
     */
    private const OWNER_TEMPLATES = [
        'EventRejectedNotification',
        'EventPublishedNotification',
        'EventDraftNotification',
        'EventReviewOwnerNotification',
    ];

    /** @var array<string, array<string, string>> template => (assignName => LLL key) */
    private const TEMPLATE_EMAIL_LABELS = [
        'EventLiveNotification' => [
            'subjectLabel' => 'email.live.subject',
            'titleLabel' => 'email.live.title',
            'introLabel' => 'email.live.intro',
            'changedFieldsLabel' => 'email.live.changedFields',
            'openBackendLabel' => 'email.openBackend',
        ],
        'EventReviewNotification' => [
            'subjectLabel' => 'email.review.subject',
            'titleLabel' => 'email.review.title',
            'introLabel' => 'email.review.intro',
            'openBackendLabel' => 'email.openBackend',
        ],
        'EventRejectedNotification' => [
            'subjectLabel' => 'email.rejected.subject',
            'titleLabel' => 'email.rejected.title',
            'introLabel' => 'email.rejected.intro',
        ],
        'EventPublishedNotification' => [
            'subjectLabel' => 'email.published.subject',
            'titleLabel' => 'email.published.title',
            'introLabel' => 'email.published.intro',
        ],
        'EventDraftNotification' => [
            'subjectLabel' => 'email.draft.subject',
            'titleLabel' => 'email.draft.title',
            'introLabel' => 'email.draft.intro',
        ],
        'EventReviewOwnerNotification' => [
            'subjectLabel' => 'email.reviewOwner.subject',
            'titleLabel' => 'email.reviewOwner.title',
            'introLabel' => 'email.reviewOwner.intro',
        ],
    ];

    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private UriBuilder $backendUriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
        private StatusChangeContext $statusChangeContext,
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
            'statusMessage' => trim((string)($eventRecord['status_message'] ?? '')),
            'changedFieldLabels' => $this->resolveChangedFieldLabels($changedFieldNames),
            ...$this->resolveEmailLabels($template),
        ];

        // Owner emails go to a frontend user without backend access, so they must
        // not carry a backend edit link.
        if (!in_array($template, self::OWNER_TEMPLATES, true)) {
            $assignments['eventEditUrl'] = $this->buildAbsoluteEditUrl($eventUid);
        }

        // Expose the acting backend user (set for status changes performed through
        // the backend status modal) so templates can name who made the change.
        $changedByBeUser = $this->statusChangeContext->getBackendUser();
        if ($changedByBeUser !== null) {
            $assignments['changedByBeUser'] = $changedByBeUser;
        }

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
        $systemFields = $this->getSystemControlFields();
        $labels = [];

        foreach ($changedFieldNames as $fieldName) {
            if ($fieldName === '' || $this->isSystemField($fieldName, $systemFields)) {
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

    /**
     * System/control fields (tstamp, timestamps, enable columns, translation and
     * versioning bookkeeping) are noise in the change notification and must not be
     * listed. They are resolved from the table's TCA ctrl so renamed enable columns
     * are covered too.
     *
     * @param string[] $systemFields
     */
    private function isSystemField(string $fieldName, array $systemFields): bool
    {
        return in_array($fieldName, $systemFields, true)
            || str_starts_with($fieldName, 't3ver_')
            || $fieldName === 'status_message';
    }

    /**
     * @return string[]
     */
    private function getSystemControlFields(): array
    {
        $ctrl = $GLOBALS['TCA'][self::EVENT_TABLE]['ctrl'] ?? [];

        $fields = ['uid', 'pid'];

        foreach ([
            'tstamp',
            'crdate',
            'cruser_id',
            'sortby',
            'delete',
            'languageField',
            'transOrigPointerField',
            'transOrigDiffSourceField',
            'translationSource',
            'origUid',
            'editlock',
        ] as $ctrlKey) {
            $fieldName = (string)($ctrl[$ctrlKey] ?? '');
            if ($fieldName !== '') {
                $fields[] = $fieldName;
            }
        }

        foreach (($ctrl['enablecolumns'] ?? []) as $enableColumn) {
            $enableColumn = (string)$enableColumn;
            if ($enableColumn !== '') {
                $fields[] = $enableColumn;
            }
        }

        return array_values(array_unique($fields));
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

    /**
     * Builds the absolute backend edit URL for the event.
     *
     * In backend context the backend UriBuilder is used (it produces a properly
     * tokenized route URL). The notification can, however, also be triggered from
     * the frontend (e.g. a frontend user moving an event from draft to review),
     * where the backend UriBuilder is not available and would throw. Only in that
     * frontend case do we fall back to a hand-built path + query string.
     */
    private function buildAbsoluteEditUrl(int $uid): string
    {
        if ($this->isFrontendRequest()) {
            return $this->buildFrontendFallbackEditUrl($uid);
        }

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
     * Frontend fallback: a plain absolute path + query string that does not depend
     * on the backend routing being bootstrapped.
     */
    private function buildFrontendFallbackEditUrl(int $uid): string
    {
        $query = http_build_query([
            'edit' => [
                self::EVENT_TABLE => [
                    $uid => 'edit',
                ],
            ],
            'module' => self::BACKEND_MODULE,
            'returnUrl' => self::BACKEND_MODULE_PATH,
        ]);

        return $this->getRequestHost() . self::BACKEND_EDIT_PATH . '?' . $query;
    }

    private function isFrontendRequest(): bool
    {
        $request = $this->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            return false;
        }

        return ApplicationType::fromRequest($request)->isFrontend();
    }

    private function getRequestHost(): string
    {
        $normalizedParams = $this->getRequest()?->getAttribute('normalizedParams') ?? null;
        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->getRequestHost();
        }
        return GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}

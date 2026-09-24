<?php

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\StatusChangeContext;
use Xima\XimaTypo3Calendar\Utility\RecordTypeUtility;
use Xima\XimaTypo3Recordlist\Controller\AbstractBackendController;
use Xima\XimaTypo3Recordlist\Dto\RecordSource;

/**
 * Backend "Events" module: the xima_typo3_recordlist configuration plus the AJAX endpoint that
 * writes an event's status.
 *
 * The requirement record type is only offered when features/requirementsManagement is enabled.
 */
class EventsController extends AbstractBackendController
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly StatusChangeContext $statusChangeContext,
        private readonly CalendarPermissionService $permissionService,
    ) {
    }

    /**
     * Persists a status change triggered from the record-list status modal,
     * together with the optional status message, and controls whether the event
     * owner is notified. The change runs through the DataHandler so the regular
     * workflow hooks and notification listeners fire.
     */
    public function updateStatus(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        $uid = (int)($parsedBody['uid'] ?? 0);
        $statusValue = (int)($parsedBody['status'] ?? -1);
        $message = trim((string)($parsedBody['message'] ?? ''));
        $notifyOwner = (bool)(int)($parsedBody['notifyOwner'] ?? 1);

        if ($uid <= 0 || EventStatus::tryFrom($statusValue) === null) {
            return new JsonResponse(['success' => false], 400);
        }

        if ($statusValue === EventStatus::LIVE->value && !$this->permissionService->canPublishLiveEvents()) {
            return new JsonResponse(['success' => false], 403);
        }

        $this->statusChangeContext->setNotifyOwner($notifyOwner);
        $this->statusChangeContext->setBackendUser($this->resolveActingBackendUser());

        $data = [
            self::EVENT_TABLE => [
                $uid => [
                    'status' => $statusValue,
                    'status_message' => $message,
                ],
            ],
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            return new JsonResponse(['success' => false, 'errors' => $dataHandler->errorLog], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * @return array{uid: int, name: string, email: string}|null
     */
    private function resolveActingBackendUser(): ?array
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return null;
        }

        $name = trim((string)($backendUser->user['realName'] ?? ''));
        if ($name === '') {
            $name = trim((string)($backendUser->user['username'] ?? ''));
        }

        return [
            'uid' => (int)($backendUser->user['uid'] ?? 0),
            'name' => $name,
            'email' => trim((string)($backendUser->user['email'] ?? '')),
        ];
    }

    protected function getRecordSources(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $folders = $qb->select('uid', 'title')
            ->from('pages')
            ->where($qb->expr()->eq('module', $qb->createNamedParameter('events')))
            ->executeQuery()
            ->fetchAllAssociative();

        $recordSources = [];
        foreach ($folders as $folder) {
            $recordSources[] = new RecordSource(
                pid: (int)$folder['uid'],
                includeSubpages: true,
                depth: 1
            );
        }
        return $recordSources;
    }

    public function getTableNames(): array
    {
        $tableNames = [
            'tx_ximatypo3calendar_domain_model_event',
            'tx_ximatypo3calendar_domain_model_organizer',
            'tx_ximatypo3calendar_domain_model_entry',
            'tx_ximatypo3calendar_domain_model_location',
            'tx_ximatypo3calendar_domain_model_requirement',
            'tx_ximatypo3calendar_domain_model_calendar',
        ];

        if (!$this->extensionConfiguration->get('xima_typo3_calendar', 'features/requirementsManagement')) {
            $tableNames = array_diff($tableNames, ['tx_ximatypo3calendar_domain_model_requirement']);
        }

        return $tableNames;
    }

    protected function addNewButtonToModuleTemplate(): void
    {
        if (!$this->isActionAllowedInCurrentTemplate('newRecord')) {
            return;
        }

        $accessiblePages = $this->getAccessiblePages();
        if ($accessiblePages === []) {
            return;
        }

        $tableName = $this->getTableName();
        $defVals = $this->getActiveLanguage() > 0
            ? [$tableName => ['sys_language_uid' => $this->getActiveLanguage()]]
            : [];
        if ($tableName === self::EVENT_TABLE) {
            $eventRecordType = RecordTypeUtility::getDefault(self::EVENT_TABLE);
            if ($eventRecordType !== null) {
                $defVals[$tableName]['record_type'] = $eventRecordType;
            }
        }

        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $recordLabel = $this->getLanguageService()->sL($GLOBALS['TCA'][$tableName]['ctrl']['title']);
        $newLabel = 'New ' . ucfirst($recordLabel);
        $buildHref = fn (int $pid): string => (string)$this->backendUriBuilder->buildUriFromRoute(
            'record_edit',
            [
                'edit' => [$tableName => [$pid => 'new']],
                'returnUrl' => $this->getCurrentUrl(),
                'defVals' => $defVals,
                'module' => $this->getModuleName(),
                'workspaceId' => self::WORKSPACE_ID,
            ]
        );

        if (count($accessiblePages) === 1) {
            $buttonBar->addButton(
                $buttonBar->makeLinkButton()
                    ->setHref($buildHref((int)$accessiblePages[0]['uid']))
                    ->setClasses('new-record-in-page')
                    ->setTitle($newLabel)
                    ->setShowLabelText(true)
                    ->setIcon($this->iconFactory->getIcon('actions-add', IconSize::SMALL))
            );
            return;
        }

        $pages = [];
        foreach ($accessiblePages as $page) {
            $pages[] = [
                'href' => $buildHref((int)$page['uid']),
                'title' => $this->getPageDisplayTitle($page),
            ];
        }

        $buttonBar->addButton(
            $buttonBar->makeLinkButton()
                ->setHref('#')
                ->setClasses('new-record-trigger')
                ->setTitle($newLabel)
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-add', IconSize::SMALL))
                ->setDataAttributes(['pages' => (string)json_encode($pages)])
        );
    }

    protected function modifyPaginatedRecords(): void
    {
        parent::modifyPaginatedRecords();

        if (in_array($this->getTableName(), ['tx_ximatypo3calendar_domain_model_event', 'tx_ximatypo3calendar_domain_model_entry'])) {
            foreach ($this->records as &$record) {
                foreach ($record as $key => &$value) {
                    if (str_starts_with($key, '_')) {
                        if (!is_array($value) && !is_object($value)) {
                            continue;
                        }

                        foreach ($value as $table => &$relatedRecords) {
                            if (!is_array($relatedRecords) && !is_object($relatedRecords)) {
                                continue;
                            }

                            foreach ($relatedRecords as &$relatedRecord) {
                                $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? null;
                                if ($labelField) {
                                    $relatedRecord['label'] = BackendUtility::getProcessedValue(
                                        table: $table,
                                        col: $labelField,
                                        value: $relatedRecord['label'],
                                        uid: $relatedRecord['label'],
                                        pid: $this->getRecordPid()
                                    );
                                }
                            }
                            unset($relatedRecord);
                        }
                        unset($relatedRecords);
                    }
                }
                unset($value);
            }
            unset($record);
        }
    }

    protected function modifyTableConfiguration(): void
    {
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['showIconColumn'] = false;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['status']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['status']['partial'] = 'EventStatus';
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['title']['defaultPosition'] = 2;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['language']['defaultPosition'] = 3;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['categories']['defaultPosition'] = 4;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['organizer']['defaultPosition'] = 5;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['publish_to_website']['defaultPosition'] = 6;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['publish_date']['defaultPosition'] = 7;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['appointments']['defaultPosition'] = 8;

        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['title']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['event']['defaultPosition'] = 2;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['start_date']['defaultPosition'] = 3;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['type']['defaultPosition'] = 4;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['location']['defaultPosition'] = 5;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['canceled']['defaultPosition'] = 6;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['speakers']['defaultPosition'] = 7;

        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_organizer']['columns']['title']['defaultPosition'] = 1;

        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_location']['columns']['name']['defaultPosition'] = 1;

        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_requirement']['columns']['title']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_requirement']['columns']['assignee']['defaultPosition'] = 2;
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Serializer\VkurkoCalendarSerializer;
use Xima\XimaTypo3Calendar\Service\CalendarPageConfigurationService;
use Xima\XimaTypo3Calendar\Service\CalendarPendingCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\CalendarStoragePidResolver;
use Xima\XimaTypo3Calendar\Utility\CalendarFeedRequestUtility;
use Xima\XimaTypo3Calendar\Utility\RecordTypeUtility;

class CalendarController extends ActionController
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    public function __construct(
        protected IconFactory $iconFactory,
        protected PageRenderer $pageRenderer,
        protected UriBuilder $backendUriBuilder,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected EntryRepository $entryRepository,
        protected CalendarPermissionService $permissionService,
        protected CalendarPageConfigurationService $pageConfigurationService,
        protected CalendarStoragePidResolver $storagePidResolver,
        protected CalendarSelectionService $calendarSelectionService,
        protected CalendarPendingCreationService $pendingCreationService,
    ) {
    }

    public function processRequest(RequestInterface $request): ResponseInterface
    {
        $this->cleanupPendingCreation($request);
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $ajaxUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_events');
        $createEventUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_create_event');
        $cleanupEventUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_cleanup_event');
        $appointmentPid = $this->storagePidResolver->resolveStoragePid();
        $calendars = $this->calendarSelectionService->getAvailableCalendars();
        $eventRecordType = RecordTypeUtility::getDefault(self::EVENT_TABLE);
        $canCreateEvent = $appointmentPid > 0
            && $this->permissionService->canCreateEventAtPid($appointmentPid);
        $canCreateAppointment = $appointmentPid > 0
            && $this->permissionService->canCreateAppointmentAtPid($appointmentPid);

        $this->addNewRecordButtons(
            $moduleTemplate,
            $request,
            $appointmentPid,
            $eventRecordType,
            $canCreateEvent,
            $canCreateAppointment,
        );

        $this->pageRenderer->loadJavaScriptModule('@xima/xima-typo3-calendar/calendar.js');

        $newEventConfiguration = $this->pageConfigurationService->getNewEventConfiguration($request);
        $calendarConfig = json_encode([
            'ajaxUrl' => $ajaxUrl,
            'createEventUrl' => $createEventUrl,
            'cleanupEventUrl' => $cleanupEventUrl,
            'canCreateEvent' => $canCreateEvent,
            'canCreateAppointment' => $canCreateAppointment,
            'calendars' => $calendars,
            ...$newEventConfiguration,
            'labels' => [
                'title' => $this->getLanguageLabel('newEventType.title', 'Create new record'),
                'event' => $this->getLanguageLabel('newEventType.event', 'Event'),
                'appointment' => $this->getLanguageLabel('newEventType.appointment', 'Event Appointment'),
                'calendar' => $this->getLanguageLabel('newEventType.calendar', 'Calendar'),
                'selectCalendar' => $this->getLanguageLabel('newEventType.selectCalendar', 'Select a calendar'),
                'start' => $this->getLanguageLabel('newEventType.start', 'Start'),
                'end' => $this->getLanguageLabel('newEventType.end', 'End'),
                'allDay' => $this->getLanguageLabel('newEventType.allDay', 'All-day'),
                'create' => $this->getLanguageLabel('newEventType.create', 'Create'),
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $moduleTemplate->assignMultiple([
            'calendarConfig' => $calendarConfig,
            'enableEventPreview' => $this->pageConfigurationService->isEventPreviewEnabled($request),
        ]);

        return $moduleTemplate->renderResponse('Backend/Calendar');
    }

    private function cleanupPendingCreation(RequestInterface $request): void
    {
        $params = $request->getQueryParams();
        $pid = $this->storagePidResolver->resolveStoragePid();

        $eventUid = (int)($params['ximaCalendarPendingEvent'] ?? 0);
        if ($eventUid > 0) {
            $this->pendingCreationService->cleanupEvent($eventUid, $pid);
        }

        $entryUid = (int)($params['ximaCalendarPendingEntry'] ?? 0);
        if ($entryUid > 0) {
            $this->pendingCreationService->cleanupEntry($entryUid, $pid);
        }
    }

    public function eventsAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $startDatetime = CalendarFeedRequestUtility::getStartTimestamp($params);
        $endDatetime = CalendarFeedRequestUtility::getEndTimestamp($params);
        $calendarUids = CalendarFeedRequestUtility::getCalendarUids($params);

        $rows = $this->entryRepository->getBackendCalendarEntries($startDatetime, $endDatetime, $calendarUids);

        $events = VkurkoCalendarSerializer::serializeBackendEntries($rows);

        return new JsonResponse($events);
    }

    private function addNewRecordButtons(
        ModuleTemplate $moduleTemplate,
        RequestInterface $request,
        int $pid,
        ?string $eventRecordType,
        bool $canCreateEvent,
        bool $canCreateAppointment,
    ): void {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $returnUrl = (string)$request->getUri();

        if ($canCreateEvent) {
            $eventUrl = $this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [self::EVENT_TABLE => [$pid => 'new']],
                'defVals' => $eventRecordType === null ? [] : [
                    self::EVENT_TABLE => ['record_type' => $eventRecordType],
                ],
                'returnUrl' => $returnUrl,
            ]);
            $this->addNewRecordButton(
                $buttonBar,
                (string)$eventUrl,
                $this->getLanguageLabel('newEvent', 'New Event'),
            );
        }

        if ($canCreateAppointment) {
            $appointmentUrl = $this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [self::ENTRY_TABLE => [$pid => 'new']],
                'defVals' => [self::ENTRY_TABLE => ['record_type' => 'event-appointment']],
                'returnUrl' => $returnUrl,
            ]);
            $this->addNewRecordButton(
                $buttonBar,
                (string)$appointmentUrl,
                $this->getLanguageLabel('newEventAppointment', 'New Event Appointment'),
            );
        }
    }

    private function addNewRecordButton(ButtonBar $buttonBar, string $url, string $title): void
    {
        $button = $buttonBar->makeLinkButton()
            ->setHref($url)
            ->setTitle($title)
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_RIGHT, 1);
    }

    private function getLanguageLabel(string $key, string $fallback): string
    {
        $label = $GLOBALS['LANG']->sL(
            'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf:' . $key,
        );

        return $label === '' || str_starts_with($label, 'LLL:') ? $fallback : $label;
    }
}

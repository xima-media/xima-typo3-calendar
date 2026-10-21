<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Serializer\VkurkoCalendarSerializer;
use Xima\XimaTypo3Calendar\Service\CalendarFilterService;
use Xima\XimaTypo3Calendar\Service\CalendarPageConfigurationService;
use Xima\XimaTypo3Calendar\Service\CalendarPendingCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\CalendarRecordButtonService;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\CalendarStoragePidResolver;
use Xima\XimaTypo3Calendar\Utility\CalendarFeedRequestUtility;
use Xima\XimaTypo3Calendar\Utility\RecordTypeUtility;

class CalendarController extends ActionController
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';

    public function __construct(
        protected PageRenderer $pageRenderer,
        protected UriBuilder $backendUriBuilder,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected EntryRepository $entryRepository,
        protected CalendarPermissionService $permissionService,
        protected CalendarPageConfigurationService $pageConfigurationService,
        protected CalendarStoragePidResolver $storagePidResolver,
        protected CalendarSelectionService $calendarSelectionService,
        protected CalendarPendingCreationService $pendingCreationService,
        protected CalendarFilterService $filterService,
        protected CalendarRecordButtonService $recordButtonService,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function processRequest(RequestInterface $request): ResponseInterface
    {
        $this->cleanupPendingCreation($request);
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);

        $ajaxUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_events');
        $filterOptionsUrl = (string)$this->backendUriBuilder->buildUriFromRoute(
            'ajax_xima_calendar_filter_options',
            ['id' => $pageId],
        );
        $filterStateUrl = (string)$this->backendUriBuilder->buildUriFromRoute(
            'ajax_xima_calendar_filter_state',
            ['id' => $pageId],
        );
        $viewStateUrl = (string)$this->backendUriBuilder->buildUriFromRoute(
            'ajax_xima_calendar_view_state',
            ['id' => $pageId],
        );
        $createEventUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_create_event');
        $cleanupEventUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_cleanup_event');
        $locale = $GLOBALS['LANG']->getLocale()?->getName() ?? 'en';
        $appointmentPid = $this->storagePidResolver->resolveStoragePid();
        $calendars = $this->calendarSelectionService->getAvailableCalendars();
        $eventRecordType = RecordTypeUtility::getDefault(self::EVENT_TABLE);
        $canCreateEvent = $appointmentPid > 0
            && $this->permissionService->canCreateEventAtPid($appointmentPid);
        $canCreateAppointment = $appointmentPid > 0
            && $this->permissionService->canCreateAppointmentAtPid($appointmentPid);

        $this->recordButtonService->addButtons(
            $moduleTemplate,
            $request,
            $appointmentPid,
            $eventRecordType,
            $canCreateEvent,
            $canCreateAppointment,
            $this->getLanguageLabel('newEvent', 'New Event'),
            $this->getLanguageLabel('newEventAppointment', 'New Event Appointment'),
        );

        $this->pageRenderer->loadJavaScriptModule('@xima/xima-typo3-calendar/calendar.js');
        $this->pageRenderer->addInlineSetting(
            'ximaCalendar',
            'filterOptionsUrl',
            $filterOptionsUrl,
        );
        $this->pageRenderer->addInlineSetting(
            'ximaCalendar',
            'filterStateUrl',
            $filterStateUrl,
        );
        $filterColors = (object)$this->pageConfigurationService->getFilterColors($request);
        $eventAppearance = $this->pageConfigurationService
            ->getEventAppearanceConfiguration($request);
        $this->pageRenderer->addInlineSetting(
            'ximaCalendar',
            'filterColors',
            $filterColors,
        );
        $this->pageRenderer->addInlineSetting(
            'ximaCalendar',
            'locale',
            $locale,
        );
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf',
            'filter',
        );

        $newEventConfiguration = $this->pageConfigurationService->getNewEventConfiguration($request);
        $recordTypes = $this->getCreationRecordTypes(self::EVENT_TABLE, 'event');
        $calendarConfig = json_encode([
            'ajaxUrl' => $ajaxUrl,
            'createEventUrl' => $createEventUrl,
            'cleanupEventUrl' => $cleanupEventUrl,
            'viewStateUrl' => $viewStateUrl,
            'initialView' => $this->filterService->getView(),
            'locale' => $locale,
            'canCreateEvent' => $canCreateEvent,
            'canCreateAppointment' => $canCreateAppointment,
            'calendars' => $calendars,
            'filterColors' => $filterColors,
            'eventAppearance' => $eventAppearance,
            'recordTypes' => $recordTypes,
            'tooltipOrder' => $this->pageConfigurationService->getTooltipOrder($request),
            ...$newEventConfiguration,
            'labels' => [
                'title' => $this->getLanguageLabel('newEventType.title', 'Create new record'),
                'event' => $this->getLanguageLabel('newEventType.event', 'Event'),
                'appointment' => $this->getLanguageLabel('newEventType.appointment', 'Event Appointment'),
                'recordType' => $this->getLanguageLabel('newEventType.recordType', 'Record type'),
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
        $activeFilters = CalendarFeedRequestUtility::getTreeFilters($params);
        $rows = $this->entryRepository->getBackendCalendarEntries(
            $startDatetime,
            $endDatetime,
            $calendarUids,
            ['filters' => $activeFilters],
        );

        $events = VkurkoCalendarSerializer::serializeBackendEntries($rows, 0, $this->eventDispatcher);

        return new JsonResponse($events);
    }

    private function getLanguageLabel(string $key, string $fallback): string
    {
        $label = $GLOBALS['LANG']->sL(
            'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf:' . $key,
        );

        return $label === '' || str_starts_with($label, 'LLL:') ? $fallback : $label;
    }

    /** @return list<array{value: string, label: string}> */
    private function getCreationRecordTypes(string $table, string $defaultValue): array
    {
        return array_map(
            fn (array $item): array => [
                'value' => $item['value'],
                'label' => $this->resolveLanguageLabel($item['label'], $item['value']),
            ],
            RecordTypeUtility::getAdditionalItems($table, $defaultValue),
        );
    }

    private function resolveLanguageLabel(string $label, string $fallback): string
    {
        if (!str_starts_with($label, 'LLL:')) {
            return $label !== '' ? $label : $fallback;
        }

        $resolved = $GLOBALS['LANG']->sL($label);
        return $resolved === '' || str_starts_with($resolved, 'LLL:') ? $fallback : $resolved;
    }
}

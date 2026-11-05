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
use Xima\XimaTypo3Calendar\Service\CalendarRecordButtonService;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\EventRecordTypeService;
use Xima\XimaTypo3Calendar\Utility\CalendarFeedRequestUtility;

class CalendarController extends ActionController
{
    public function __construct(
        protected PageRenderer $pageRenderer,
        protected UriBuilder $backendUriBuilder,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected EntryRepository $entryRepository,
        protected CalendarPageConfigurationService $pageConfigurationService,
        protected CalendarSelectionService $calendarSelectionService,
        protected EventRecordTypeService $recordTypeService,
        protected CalendarFilterService $filterService,
        protected CalendarRecordButtonService $recordButtonService,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function processRequest(RequestInterface $request): ResponseInterface
    {
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
        $newRecordUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_new_record');
        $locale = $GLOBALS['LANG']->getLocale()?->getName() ?? 'en';
        $calendars = $this->calendarSelectionService->getAvailableCalendars();
        $canCreateAppointment = $calendars !== [];
        $canCreateEvent = in_array(true, array_column($calendars, 'canCreateEvent'), true);
        // Record types can differ per storage page (TSconfig), so each calendar brings its own.
        $calendarOptions = array_map(
            fn (array $calendar): array => [
                'uid' => $calendar['uid'],
                'title' => $calendar['title'],
                'canCreateEvent' => $calendar['canCreateEvent'],
                'recordTypes' => $calendar['canCreateEvent']
                    ? array_map(
                        static fn (array $recordType): array => ['value' => $recordType['value'], 'label' => $recordType['label']],
                        $this->recordTypeService->getRecordTypes($request, $calendar['pid']),
                    )
                    : [],
            ],
            $calendars,
        );

        $this->recordButtonService->addButtons(
            $moduleTemplate,
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
        $this->pageRenderer->addInlineSetting(
            'ximaCalendar',
            'firstDay',
            $newEventConfiguration['firstDay'],
        );
        $calendarConfig = json_encode([
            'ajaxUrl' => $ajaxUrl,
            'newRecordUrl' => $newRecordUrl,
            'viewStateUrl' => $viewStateUrl,
            'initialView' => $this->filterService->getView(),
            'locale' => $locale,
            'canCreateEvent' => $canCreateEvent,
            'canCreateAppointment' => $canCreateAppointment,
            'calendars' => $calendarOptions,
            'lastCalendarUid' => $this->calendarSelectionService->getLastUsedCalendarUid($calendars),
            'filterColors' => $filterColors,
            'eventAppearance' => $eventAppearance,
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
            filters: ['filters' => $activeFilters],
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
}

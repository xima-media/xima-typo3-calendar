import {createCalendar, DayGrid, destroyCalendar, Interaction, List, TimeGrid} from '@event-calendar/core';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DocumentService from '@typo3/core/document-service.js';
import Notification from '@typo3/backend/notification.js';
import {createCalendarCreationController} from './Event/calendar-event-creation';
import {createCalendarDetailsController} from './Event/calendar-details';
import {createCalendarInteractionController} from './Interaction/calendar-interaction';
import {createCalendarListScrollController} from './Interaction/calendar-list-scroll-controller';
import {readCalendarConfig, type Typo3TopWindow} from './Configuration/calendar-runtime-config';
import type {CalendarFilterConfiguration, CalendarFilterSelection} from './Filter/calendar-filter-state';
import {createCalendarFilterSync} from './Filter/calendar-filter-sync';
import {createCalendarMiniCalendarSync} from './Filter/Services/calendar-mini-calendar-sync';
import {miniCalendarEvents} from './Filter/Services/calendar-mini-calendar-events';
import {createCalendarEventSource} from './Data/calendar-event-source';

import type {CalendarEventData} from './Rendering/calendar-event-data';
import {createCalendarEventRenderer} from './Rendering/calendar-event-renderer';
import {createCalendarEventTooltip} from './Rendering/calendar-event-tooltip';
import {isMultiDayCalendarEvent} from './Rendering/calendar-list-layout';

type EventCalendarTheme = Record<string, string | string[]>;
type EventCalendarButtonText = Record<string, string>;
type CalendarFilterWindow = Window & {
    ximaCalendarFilterState?: CalendarFilterSelection;
    ximaCalendarFilterConfiguration?: CalendarFilterConfiguration;
};

const calendarCleanups = new WeakMap<HTMLElement, () => Promise<void>>();

DocumentService.ready().then(async () => {
    const container = document.getElementById('xima-calendar-mount');
    if (!container) {
        return;
    }

    await calendarCleanups.get(container)?.();

    const calendarConfig = readCalendarConfig(container);
    if (!calendarConfig) {
        Notification.error(
            'Configuration error',
            'The calendar configuration is invalid.',
        );
        return;
    }

    const enableDragNewEvent = calendarConfig.enableDragNewEvent;
    const enableClickNewEvent = calendarConfig.enableClickNewEvent;
    const timeSlotDuration = `00:${String(calendarConfig.timeSlotMinutes).padStart(2, '0')}:00`;
    const typo3Top = window.top as unknown as Typo3TopWindow;
    const filterWindow = window.top as unknown as CalendarFilterWindow;
    const initialFilters: CalendarFilterSelection = filterWindow.ximaCalendarFilterState ?? {
        filters: {},
    };
    const initialFilterConfiguration: CalendarFilterConfiguration =
        filterWindow.ximaCalendarFilterConfiguration ?? {};

    let currentEvents: CalendarEventData[] = [];
    let refetchEvents = (): void => undefined;
    const filterSync = createCalendarFilterSync({
        filterWindow,
        initialSelection: initialFilters,
        initialConfiguration: initialFilterConfiguration,
        refetchEvents: () => refetchEvents(),
    });

    const eventRenderer = createCalendarEventRenderer({
        locale: calendarConfig.locale,
        filterColors: calendarConfig.filterColors,
        appearanceSource: calendarConfig.eventAppearance.appearanceSource,
        colorSource: calendarConfig.eventAppearance.colorSource,
        iconSource: calendarConfig.eventAppearance.iconSource,
        getFilterConfiguration: filterSync.getFilterConfiguration,
    });
    const eventTooltip = createCalendarEventTooltip({
        locale: calendarConfig.locale,
        getFilterConfiguration: filterSync.getFilterConfiguration,
        tooltipOrder: calendarConfig.tooltipOrder,
    });
    const detailsController = createCalendarDetailsController(container, typo3Top);
    const listScrollController = createCalendarListScrollController(
        container,
        eventRenderer,
        (event, element) => detailsController.eventClick({
            event: {
                id: event.id,
                extendedProps: event.extendedProps ?? {},
            },
            el: element,
        }),
        (event, element) => eventTooltip.attach(event, element),
    );

    const creationController = createCalendarCreationController(container, typo3Top, calendarConfig);
    let currentView = calendarConfig.initialView;
    const persistCalendarView = (view: string): void => {
        if (view === currentView) {
            return;
        }

        currentView = view as typeof currentView;
        void new AjaxRequest(calendarConfig.viewStateUrl)
            .post({view})
            .then(response => response.resolve())
            .catch(error => console.error('Calendar view could not be saved', error));
    };

    const ec = createCalendar(
        container,
        [DayGrid, TimeGrid, List, Interaction],
        {
            locale: calendarConfig.locale,
            noEventsContent: calendarConfig.locale.startsWith('de')
                ? 'Noch keine Events für diesen Monat'
                : 'No events for this month yet',
            eventGap: 3,
            height: '100%',
            nowIndicator: true,
            selectable: enableDragNewEvent,
            dayMaxEvents: true,
            moreLinkContent: ({num}: {num: number}) => `+${num} ${calendarConfig.locale.startsWith('de') ? 'weitere' : 'more'}`,
            view: calendarConfig.initialView,
            firstDay: 1,
            views: {
                timeGridWeek: {
            scrollTime: calendarConfig.scrollStartTime,
                    slotMinTime: '00:00:00',
                    slotMaxTime: '24:00:00',
                    slotDuration: timeSlotDuration,
                    slotLabelInterval: '01:00:00',
                    snapDuration: timeSlotDuration,
                    slotEventOverlap: false,
                },
            },
            theme: (theme: EventCalendarTheme) => ({
                ...theme,
                button: 'btn btn-default',
                buttonGroup: 'btn-group',
                active: 'active',
            }),
            buttonText: (buttonText: EventCalendarButtonText) => ({
                ...buttonText,
                dayGridMonth: calendarConfig.locale.startsWith('de') ? 'Monat' : 'Month',
                timeGridWeek: calendarConfig.locale.startsWith('de') ? 'Woche' : 'Week',
                listMonth: calendarConfig.locale.startsWith('de') ? 'Liste' : 'List',
                today: calendarConfig.locale.startsWith('de') ? 'Heute' : 'Today',
            }),
            headerToolbar: {
                start: 'prev next today',
                center: 'title',
                end: 'dayGridMonth,timeGridWeek,listMonth',
            },
            datesSet: ({view}: {view: {type: string; currentStart: Date}}) => {
                detailsController.datesSet();
                persistCalendarView(view.type);
                listScrollController.viewChanged(view);
                filterWindow.dispatchEvent(new CustomEvent(miniCalendarEvents.mainDateChange, {
                    detail: {
                        date: view.currentStart.toISOString(),
                        viewType: view.type,
                    },
                }));
            },
            select: enableDragNewEvent ? creationController.select : undefined,
            dateClick: enableClickNewEvent ? creationController.dateClick : undefined,
            eventSources: [createCalendarEventSource({
                ajaxUrl: calendarConfig.ajaxUrl,
                getActiveFilters: filterSync.getActiveFilters,
                renderer: eventRenderer,
                onEventsLoaded: (events) => {
                    currentEvents = events;
                    listScrollController.eventsLoaded(events);
                },
            })],
            eventClassNames: ({event}: {event: CalendarEventData}) => [
                ...eventRenderer.getClassNames(event),
                ...(isMultiDayCalendarEvent(event)
                    ? ['xima-calendar-list__multi-day-source']
                    : []),
            ],
            eventDidMount: ({event, el}: {
                event: CalendarEventData;
                el: HTMLElement;
            }) => {
                if (event.id !== undefined) {
                    el.dataset.ximaEventId = String(event.id);
                }
                eventRenderer.didMount(event, el, currentEvents);
                eventTooltip.attach(event, el);
            },
            eventClick: detailsController.eventClick,
        },
    );

    refetchEvents = () => ec.refetchEvents();
    const miniCalendarSync = createCalendarMiniCalendarSync(ec, filterWindow);
    filterSync.start();

    const interactionController = createCalendarInteractionController(container, ec, creationController, {
        enableDragNewEvent,
    });
    creationController.setClearCalendarSelection(() => ec.unselect());

    let destroyed = false;
    const cleanupPendingCreation = (): void => {
        void creationController.cleanupPendingCreation().then((cleanedUp) => {
            if (cleanedUp && !destroyed) {
                ec.refetchEvents();
            }
        });
    };

    cleanupPendingCreation();
    window.addEventListener('pageshow', cleanupPendingCreation);
    window.addEventListener('popstate', cleanupPendingCreation);
    filterWindow.document.addEventListener('typo3-module-loaded', cleanupPendingCreation, true);

    const cleanup = async (): Promise<void> => {
        if (destroyed) {
            return;
        }

        destroyed = true;
        window.removeEventListener('pageshow', cleanupPendingCreation);
        window.removeEventListener('popstate', cleanupPendingCreation);
        filterWindow.document.removeEventListener('typo3-module-loaded', cleanupPendingCreation, true);
        miniCalendarSync.destroy();
        filterSync.destroy();
        interactionController.destroy();
        eventTooltip.destroy();
        listScrollController.destroy();
        calendarCleanups.delete(container);
        await destroyCalendar(ec);
    };

    calendarCleanups.set(container, cleanup);
});

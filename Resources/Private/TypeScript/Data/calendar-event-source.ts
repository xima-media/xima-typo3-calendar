import type {CalendarFilterSelection} from '../Filter/calendar-filter-state';
import type {CalendarEventData} from '../Rendering/calendar-event-data';
import type {CalendarEventRenderer} from '../Rendering/calendar-event-renderer';

type CalendarEventFetchInfo = {
    startStr: string;
    endStr: string;
};

type CalendarEventSourceConfiguration = {
    ajaxUrl: string;
    getActiveFilters: () => CalendarFilterSelection;
    renderer: CalendarEventRenderer;
    onEventsLoaded: (events: CalendarEventData[]) => void;
};

export const createCalendarEventSource = (
    configuration: CalendarEventSourceConfiguration,
) => ({
    events: async ({startStr, endStr}: CalendarEventFetchInfo): Promise<CalendarEventData[]> => {
        const url = new URL(
            configuration.ajaxUrl,
            document.location.origin,
        );

        url.searchParams.set('start', startStr);
        url.searchParams.set('end', endStr);
        url.searchParams.set(
            'filters',
            JSON.stringify(configuration.getActiveFilters().filters),
        );

        const response = await fetch(
            url,
            {credentials: 'same-origin'},
        );

        if (!response.ok) {
            throw new Error('Calendar events could not be loaded');
        }

        const events = await response.json() as CalendarEventData[];
        const preparedEvents = configuration.renderer.prepareEvents(events);

        configuration.onEventsLoaded(preparedEvents);

        return preparedEvents;
    },
});

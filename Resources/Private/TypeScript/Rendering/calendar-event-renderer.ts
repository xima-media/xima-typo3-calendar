import type {CalendarFilterConfiguration} from '../Filter/calendar-filter-state';
import {getAppearanceClass} from './calendar-event-appearance';
import {
    applyEventColorsToEvents,
} from './calendar-event-colors';
import {
    type CalendarEventData,
    getFilterValue,
} from './calendar-event-data';
import {getEventIcon} from './calendar-event-icons';

export type CalendarEventRendererConfiguration = {
    filterColors: Record<string, Record<string, string>>;
    appearanceSource: string;
    colorSource: string;
    iconSource: string;
    getFilterConfiguration: () => CalendarFilterConfiguration;
};

export type CalendarEventRenderer = {
    prepareEvents(events: CalendarEventData[]): CalendarEventData[];
    getClassNames(event: CalendarEventData): string[];
    didMount(
        event: CalendarEventData,
        element: HTMLElement,
        events: CalendarEventData[],
    ): void;
};

const isCanceledEvent = (event: CalendarEventData): boolean => {
    const canceled = event.extendedProps?.appointmentCanceled;

    return canceled === true
        || canceled === 1
        || canceled === '1';
};

const getCanceledMarker = (
    configuration: CalendarFilterConfiguration,
): string | null => {
    for (const filter of Object.values(configuration)) {
        const item = filter.items.find(item => (
            String(item.value) === 'canceled'
            || String(item.value) === 'cancelled'
        ));

        if (item?.marker) {
            return item.marker;
        }
    }

    return null;
};

export const createCalendarEventRenderer = (
    configuration: CalendarEventRendererConfiguration,
): CalendarEventRenderer => ({
    prepareEvents(events): CalendarEventData[] {
        return applyEventColorsToEvents(
            events,
            configuration.filterColors,
            configuration.colorSource,
        );
    },

    getClassNames(event): string[] {
        const colorValue = getFilterValue(
            event,
            configuration.colorSource,
        );
        const appearanceValue = getFilterValue(
            event,
            configuration.appearanceSource,
        );

        const appearanceClass = appearanceValue === undefined
            ? ''
            : getAppearanceClass(
                configuration.getFilterConfiguration(),
                configuration.appearanceSource,
                appearanceValue,
            );

        return [
            ...(colorValue !== undefined
                ? ['xima-calendar-event--color-coded']
                : []),
            ...(isCanceledEvent(event)
                ? ['xima-calendar-event--canceled']
                : []),
            ...(appearanceClass
                ? [appearanceClass]
                : []),
        ];
    },

    didMount(event, element): void {
        const filterConfiguration = configuration.getFilterConfiguration();

        const eventBody = element.querySelector<HTMLElement>(
            '.ec-event-body',
        );

        if (!eventBody) {
            return;
        }

        const canceledMarker = getCanceledMarker(filterConfiguration);

        if (canceledMarker && isCanceledEvent(event)) {
            const titleElement = eventBody.querySelector<HTMLElement>(
                '.ec-event-title',
            );

            if (titleElement) {
                const previousMarker = titleElement.dataset.ximaCanceledMarker;
                const currentTitle = titleElement.textContent ?? '';
                const previousPrefix = previousMarker ? `${previousMarker} ` : '';
                const title = previousPrefix && currentTitle.startsWith(previousPrefix)
                    ? currentTitle.slice(previousPrefix.length)
                    : currentTitle;

                titleElement.textContent = `${canceledMarker} ${title}`;
                titleElement.dataset.ximaCanceledMarker = canceledMarker;
            }
        }

        const iconIdentifier = getEventIcon(
            event,
            filterConfiguration,
            configuration.iconSource,
        );

        if (
            !iconIdentifier
            || eventBody.querySelector('[data-xima-event-icon]')
        ) {
            return;
        }

        const icon = document.createElement('typo3-backend-icon');
        icon.setAttribute('identifier', iconIdentifier);
        icon.setAttribute('size', 'small');
        icon.dataset.ximaEventIcon = 'true';
        icon.classList.add('xima-calendar-event__icon');
        eventBody.prepend(icon);
    }
});

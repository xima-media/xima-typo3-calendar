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
    locale: string;
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

const eventTime = (
    value: string | Date | undefined,
): number | null => {
    if (!value) {
        return null;
    }

    const timestamp = value instanceof Date
        ? value.getTime()
        : Date.parse(value);

    return Number.isFinite(timestamp) ? timestamp : null;
};

const getConcurrentEventCount = (
    event: CalendarEventData,
    events: CalendarEventData[],
): number => {
    const start = eventTime(event.start);
    const end = eventTime(event.end);

    if (event.allDay || start === null || end === null) {
        return 0;
    }

    return events.filter((other) => {
        if (
            other === event
            || other.allDay
            || (
                other.id !== undefined
                && event.id !== undefined
                && other.id === event.id
            )
        ) {
            return false;
        }

        const otherStart = eventTime(other.start);
        const otherEnd = eventTime(other.end);

        return otherStart !== null
            && otherEnd !== null
            && start < otherEnd
            && end > otherStart;
    }).length;
};

const getCanceledMarker = (locale: string): string => (
    locale.startsWith('de') ? '[A]' : '[C]'
);

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

    didMount(event, element, events): void {
        const filterConfiguration = configuration.getFilterConfiguration();

        const eventBody = element.querySelector<HTMLElement>(
            '.ec-event-body',
        );

        if (!eventBody) {
            return;
        }

        const concurrentEventCount = getConcurrentEventCount(event, events);
        if (concurrentEventCount >= 1) {
            element.classList.add('xima-calendar-event--overlap-2');
        }
        if (isCanceledEvent(event) && concurrentEventCount >= 1) {
            const titleElement = eventBody.querySelector<HTMLElement>(
                '.ec-event-title',
            );

            if (titleElement) {
                titleElement.textContent = `${getCanceledMarker(configuration.locale)} ${
                    titleElement.textContent ?? ''
                }`;
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

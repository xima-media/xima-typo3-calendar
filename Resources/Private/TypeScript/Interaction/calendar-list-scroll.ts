import type {CalendarEventData} from '../Rendering/calendar-event-data';
import {isMultiDayCalendarEvent} from '../Rendering/calendar-list-layout';

type CalendarListScrollConfiguration = {
    container: HTMLElement;
    viewStart: Date;
    events: CalendarEventData[];
    today?: Date;
};

const startOfDay = (date: Date): Date => {
    const result = new Date(date);
    result.setHours(0, 0, 0, 0);
    return result;
};

const getEventStart = (event: CalendarEventData): Date | null => {
    if (!event.start) {
        return null;
    }

    const date = new Date(event.start);
    return Number.isNaN(date.getTime()) ? null : date;
};

const isSameDay = (first: Date, second: Date): boolean => (
    first.getFullYear() === second.getFullYear()
    && first.getMonth() === second.getMonth()
    && first.getDate() === second.getDate()
);

const isSameMonth = (date: Date, month: Date): boolean => (
    date.getFullYear() === month.getFullYear()
    && date.getMonth() === month.getMonth()
);

const toDateAttribute = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

export const scrollCalendarListToRelevantDate = (
    configuration: CalendarListScrollConfiguration,
): void => {
    const list = configuration.container.querySelector<HTMLElement>('.ec-list');
    const scrollContainer = configuration.container.querySelector<HTMLElement>('.ec-main');

    if (!list || !scrollContainer) {
        return;
    }

    const monthStart = startOfDay(configuration.viewStart);
    const today = startOfDay(configuration.today ?? new Date());
    const monthEvents = configuration.events
        .filter(event => !isMultiDayCalendarEvent(event))
        .map(event => ({event, start: getEventStart(event)}))
        .filter(({start}) => start !== null && isSameMonth(start, monthStart))
        .sort((first, second) => first.start!.getTime() - second.start!.getTime());

    const todayEvent = monthEvents.find(({start}) => isSameDay(start!, today));
    const nextEvent = monthEvents.find(({start}) => start! >= today);
    const targetDate = todayEvent?.start ?? nextEvent?.start ?? monthStart;
    const target = list.querySelector<HTMLElement>(
        `time[datetime="${toDateAttribute(targetDate!)}"]`,
    )?.closest<HTMLElement>('.ec-day');

    if (!target) {
        return;
    }

    scrollContainer.scrollTo({
        top: Math.max(0, target.offsetTop - 8),
        behavior: 'auto',
    });
};

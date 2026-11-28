import type {CalendarEventData} from '../Rendering/calendar-event-data';
import {isMultiDayCalendarEvent} from '../Rendering/calendar-list-layout';
import {formatLocalCalendarDate} from '../Filter/Services/calendar-date-utils';

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
        `time[datetime="${formatLocalCalendarDate(targetDate!)}"]`,
    )?.closest<HTMLElement>('.ec-day');

    if (!target) {
        return;
    }

    const firstAppointment = target.querySelector<HTMLElement>(
        '.ec-event:not(.xima-calendar-list__multi-day-source)',
    );
    // The day heading is sticky and would otherwise cover the first appointment.
    const stickyDayHeadHeight = firstAppointment
        ? target.querySelector<HTMLElement>('.ec-day-head')?.offsetHeight ?? 0
        : 0;
    const scrollTarget = firstAppointment ?? target;
    const targetTop = scrollTarget.getBoundingClientRect().top
        - scrollContainer.getBoundingClientRect().top
        + scrollContainer.scrollTop;

    scrollContainer.scrollTo({
        top: Math.max(0, targetTop - stickyDayHeadHeight),
        behavior: 'auto',
    });
};

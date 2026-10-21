import type {CalendarEventData} from './calendar-event-data';

export type CalendarListLane = {
    event: CalendarEventData;
    column: number;
    rowStart: number;
    rowSpan: number;
};

export type CalendarListLayout = {
    lanes: CalendarListLane[];
    columnCount: number;
    dayCount: number;
};

type EventInterval = {
    event: CalendarEventData;
    start: number;
    end: number;
};

const startOfDay = (date: Date): Date => {
    const result = new Date(date);
    result.setHours(0, 0, 0, 0);
    return result;
};

const getDate = (value: string | Date | undefined): Date | null => {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const getDayIndex = (date: Date, monthStart: Date): number => (
    Math.floor((startOfDay(date).getTime() - monthStart.getTime()) / 86_400_000)
);

export const isMultiDayCalendarEvent = (event: CalendarEventData): boolean => {
    const eventStart = getDate(event.start);
    const eventEnd = getDate(event.end);

    if (!eventStart || !eventEnd) {
        return false;
    }

    const startDay = startOfDay(eventStart).getTime();
    const endDay = startOfDay(eventEnd).getTime();
    const dayDifference = Math.floor((endDay - startDay) / 86_400_000);

    return event.allDay ? dayDifference > 1 : dayDifference > 0;
};

const getEventInterval = (
    event: CalendarEventData,
    monthStart: Date,
    monthEnd: Date,
): EventInterval | null => {
    const eventStart = getDate(event.start);
    const eventEnd = getDate(event.end);

    if (!eventStart || !eventEnd) {
        return null;
    }

    const startDay = getDayIndex(eventStart, monthStart);
    const endDay = getDayIndex(eventEnd, monthStart);

    if (!isMultiDayCalendarEvent(event)) {
        return null;
    }

    const start = Math.max(0, startDay);
    const end = Math.min(
        endDay + (event.allDay ? 0 : 1),
        getDayIndex(monthEnd, monthStart),
    );

    if (end <= start) {
        return null;
    }

    return {event, start, end};
};

export const createCalendarListLayout = (
    events: CalendarEventData[],
    monthStartValue: Date,
    monthEndValue: Date,
): CalendarListLayout => {
    const monthStart = startOfDay(monthStartValue);
    const monthEnd = startOfDay(monthEndValue);
    const dayCount = Math.max(0, getDayIndex(monthEnd, monthStart));
    const intervals = events
        .map(event => getEventInterval(event, monthStart, monthEnd))
        .filter((interval): interval is EventInterval => interval !== null)
        .sort((first, second) => first.start - second.start || second.end - first.end);
    const columnEnds: number[] = [];
    const lanes: CalendarListLane[] = [];

    for (const interval of intervals) {
        let column = columnEnds.findIndex(end => end <= interval.start);

        if (column === -1) {
            column = columnEnds.length;
        }

        columnEnds[column] = interval.end;
        lanes.push({
            event: interval.event,
            column,
            rowStart: interval.start + 1,
            rowSpan: interval.end - interval.start,
        });
    }

    return {
        lanes,
        columnCount: columnEnds.length,
        dayCount,
    };
};

export type CalendarSelection = {
    start: Date;
    end: Date;
    allDay: boolean;
};

export type CalendarDateClick = {
    date: Date;
    allDay: boolean;
};

export type CalendarSelectionDefaults = {
    startTime: string;
    endTime: string;
    allDay: boolean;
};

const setTime = (date: Date, value: string): void => {
    const match = value.match(/^(\d{1,2}):(\d{2})$/);
    if (!match) {
        return;
    }

    date.setHours(Number(match[1]), Number(match[2]), 0, 0);
};

export const prepareCalendarSelection = (
    selection: CalendarSelection,
    defaults: CalendarSelectionDefaults,
    forceAllDay = false,
): CalendarSelection => {
    let start = new Date(selection.start);
    let end = new Date(selection.end);
    const allDay = forceAllDay || (selection.allDay && defaults.allDay);

    if (selection.allDay) {
        if (allDay) {
            start.setHours(0, 0, 0, 0);
            end = new Date(end);
            end.setHours(0, 0, 0, 0);
        } else {
            setTime(start, defaults.startTime);
            end = new Date(end);
            end.setDate(end.getDate() - 1);
            setTime(end, defaults.endTime);
            if (end <= start) {
                end.setDate(end.getDate() + 1);
            }
        }
    }

    return {start, end, allDay};
};

export const getSelectedDayCount = (selection: CalendarSelection): number => {
    const start = new Date(selection.start);
    const end = new Date(selection.end);
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);

    return Math.round((end.getTime() - start.getTime()) / 86400000);
};

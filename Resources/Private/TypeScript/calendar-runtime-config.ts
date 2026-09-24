export type CalendarModalLabels = {
    title: string;
    event: string;
    appointment: string;
    calendar: string;
    selectCalendar: string;
    start: string;
    end: string;
    allDay: string;
    create: string;
};

export type CalendarOption = {
    uid: number;
    title: string;
};

export type Typo3TopWindow = Window & {
    TYPO3: {
        settings: { FormEngine: { moduleUrl: string } };
        ModuleMenu: { App: { getCurrentModule: () => string } };
    };
};

export type CalendarConfig = {
    ajaxUrl: string;
    createEventUrl: string;
    cleanupEventUrl: string;
    canCreateEvent: boolean;
    canCreateAppointment: boolean;
    enableDragNewEvent: boolean;
    enableClickNewEvent: boolean;
    defaultStartTime: string;
    defaultEndTime: string;
    defaultAllDay: boolean;
    calendars: CalendarOption[];
    labels: CalendarModalLabels;
};

const isString = (value: unknown): value is string => typeof value === 'string';
const isBoolean = (value: unknown): value is boolean => typeof value === 'boolean';
const isNumber = (value: unknown): value is number => typeof value === 'number' && Number.isInteger(value);
const hasValues = (
    record: Record<string, unknown>,
    keys: readonly string[],
    predicate: (value: unknown) => boolean,
): boolean => keys.every(key => predicate(record[key]));

const isCalendarModalLabels = (value: unknown): value is CalendarModalLabels => {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const labels = value as Record<string, unknown>;
    return hasValues(
        labels,
        ['title', 'event', 'appointment', 'calendar', 'selectCalendar', 'start', 'end', 'allDay', 'create'],
        isString,
    );
};

const isCalendarOptions = (value: unknown): value is CalendarOption[] => (
    Array.isArray(value)
    && value.every(option => (
        Boolean(option)
        && typeof option === 'object'
        && isNumber((option as Record<string, unknown>).uid)
        && isString((option as Record<string, unknown>).title)
    ))
);

const isCalendarConfig = (value: unknown): value is CalendarConfig => {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const config = value as Record<string, unknown>;
    return hasValues(config, ['ajaxUrl', 'createEventUrl', 'cleanupEventUrl'], isString)
        && hasValues(
            config,
            ['canCreateEvent', 'canCreateAppointment', 'enableDragNewEvent', 'enableClickNewEvent', 'defaultAllDay'],
            isBoolean,
        )
        && hasValues(config, ['defaultStartTime', 'defaultEndTime'], isString)
        && isCalendarOptions(config.calendars)
        && isCalendarModalLabels(config.labels);
};

export function readCalendarConfig(container: HTMLElement): CalendarConfig | null {
    let rawConfig: unknown;
    try {
        rawConfig = JSON.parse(container.dataset.calendarConfig ?? 'null') as unknown;
    } catch {
        return null;
    }

    return isCalendarConfig(rawConfig) ? rawConfig : null;
}

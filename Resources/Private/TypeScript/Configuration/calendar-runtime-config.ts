export type CalendarModalLabels = {
    title: string;
    event: string;
    appointment: string;
    recordType: string;
    calendar: string;
    selectCalendar: string;
    start: string;
    end: string;
    allDay: string;
    create: string;
};

export type CalendarRecordTypeOption = {
    value: string;
    label: string;
};

export type CalendarOption = {
    uid: number;
    title: string;
};

export type EventAppearanceConfiguration = {
  iconSource: string; appearanceSource: string; colorSource: string;
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
    viewStateUrl: string;
    initialView: 'dayGridMonth' | 'timeGridWeek' | 'listMonth';
    locale: string;
    canCreateEvent: boolean;
    canCreateAppointment: boolean;
    enableDragNewEvent: boolean;
    enableClickNewEvent: boolean;
    defaultStartTime: string;
    defaultEndTime: string;
    defaultAllDay: boolean;
    defaultRecordType: string | null;
    timeSlotMinutes: number;
    scrollStartTime: string;
    calendars: CalendarOption[];
    recordTypes: CalendarRecordTypeOption[];
    tooltipOrder: string[];
  filterColors: Record<string, Record<string, string>>; eventAppearance: EventAppearanceConfiguration;
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
        ['title', 'event', 'appointment', 'recordType', 'calendar', 'selectCalendar', 'start', 'end', 'allDay', 'create'],
        isString,
    );
};

const isRecordTypeOptions = (value: unknown): value is CalendarRecordTypeOption[] => (
    Array.isArray(value)
    && value.every(option => (
        Boolean(option)
        && typeof option === 'object'
        && isString((option as Record<string, unknown>).value)
        && isString((option as Record<string, unknown>).label)
    ))
);

const isCalendarOptions = (value: unknown): value is CalendarOption[] => (
    Array.isArray(value)
    && value.every(option => (
        Boolean(option)
        && typeof option === 'object'
        && isNumber((option as Record<string, unknown>).uid)
        && isString((option as Record<string, unknown>).title)
    ))
);

const isFilterColors = (value: unknown): value is Record<string, Record<string, string>> => (
    Boolean(value)
    && typeof value === 'object'
    && !Array.isArray(value)
    && Object.values(value as Record<string, unknown>).every(scope => (
        Boolean(scope)
        && typeof scope === 'object'
        && !Array.isArray(scope)
        && Object.values(scope as Record<string, unknown>).every(isString)
    ))
);

const isEventAppearanceConfiguration = (
  value: unknown,
): value is EventAppearanceConfiguration => (
  Boolean(value)
  && typeof value === 'object'
  && !Array.isArray(value)
  && hasValues(
    value as Record<string, unknown>,
    ['iconSource', 'appearanceSource', 'colorSource'],
    isString,
  )
);
const isStringArray = (value: unknown): value is string[] => (
    Array.isArray(value) && value.every(isString)
);
const isCalendarConfig = (value: unknown): value is CalendarConfig => {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const config = value as Record<string, unknown>;
    return hasValues(config, ['ajaxUrl', 'createEventUrl', 'cleanupEventUrl', 'viewStateUrl', 'initialView', 'locale'], isString)
        && ['dayGridMonth', 'timeGridWeek', 'listMonth'].includes(config.initialView as string)
        && hasValues(
            config,
            ['canCreateEvent', 'canCreateAppointment', 'enableDragNewEvent', 'enableClickNewEvent', 'defaultAllDay'],
            isBoolean,
        )
        && hasValues(config, ['defaultStartTime', 'defaultEndTime'], isString)
        && (config.defaultRecordType === null || isString(config.defaultRecordType))
        && hasValues(config, ['timeSlotMinutes'], isNumber)
        && hasValues(config, ['scrollStartTime'], isString)
        && isCalendarOptions(config.calendars)
        && isRecordTypeOptions(config.recordTypes)
        && isStringArray(config.tooltipOrder)
        && isFilterColors(config.filterColors)
        && isEventAppearanceConfiguration(config.eventAppearance)
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

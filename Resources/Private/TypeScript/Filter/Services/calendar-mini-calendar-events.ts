export const miniCalendarEvents = {
    mainDateChange: 'xima-calendar-main-date-change',
    navigate: 'xima-calendar-mini-calendar-navigate',
    dateChange: 'xima-calendar-mini-calendar-date-change',
    ready: 'xima-calendar-mini-calendar-ready',
} as const;

export type MiniCalendarNavigationDetail = {
    direction?: 'prev' | 'next';
    date?: string;
};

export type MiniCalendarDateDetail = {
    date?: string;
};

export type MainCalendarDateDetail = {
    date?: string;
    viewType?: string;
};

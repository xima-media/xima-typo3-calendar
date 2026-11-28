import type {Calendar} from '@event-calendar/core';
import {parseCalendarDate} from './calendar-date-utils';
import {
    miniCalendarEvents,
    type MainCalendarDateDetail,
    type MiniCalendarDateDetail,
    type MiniCalendarNavigationDetail,
} from './calendar-mini-calendar-events';

type CalendarMiniCalendarSyncOptions = {
    scrollListToDate?: (date: Date) => void;
};

export function createCalendarMiniCalendarSync(
    calendar: Calendar,
    filterWindow: Window,
    options: CalendarMiniCalendarSyncOptions = {},
): {publish: () => void; destroy: () => void} {
    const publishMainCalendarDate = (): void => {
        const view = calendar.getView();
        const configuredDate = calendar.getOption('date');
        const navigationDate = configuredDate instanceof Date
            ? configuredDate
            : typeof configuredDate === 'string'
                ? parseCalendarDate(configuredDate)
                : view.currentStart;
        const date = Number.isNaN(navigationDate.getTime())
            ? view.currentStart
            : navigationDate;

        filterWindow.dispatchEvent(new CustomEvent<MainCalendarDateDetail>(miniCalendarEvents.mainDateChange, {
            detail: {
                date: date.toISOString(),
                viewType: view.type,
            },
        }));
    };

    const handleMiniCalendarNavigation = (event: Event): void => {
        const detail = (event as CustomEvent<MiniCalendarNavigationDetail>).detail;
        const direction = detail?.direction;
        if (direction !== 'prev' && direction !== 'next') {
            return;
        }

        const requestedMonth = detail.date ? parseCalendarDate(detail.date) : null;
        const currentStart = calendar.getView().currentStart;
        const targetMonth = requestedMonth && !Number.isNaN(requestedMonth.getTime())
            ? requestedMonth
            : new Date(
                currentStart.getFullYear(),
                currentStart.getMonth() + (direction === 'prev' ? -1 : 1),
                1,
            );
        calendar.gotoDate(targetMonth);
        publishMainCalendarDate();
    };

    const handleMiniCalendarDateChange = (event: Event): void => {
        const date = (event as CustomEvent<MiniCalendarDateDetail>).detail?.date;
        if (!date) {
            return;
        }

        const parsedDate = parseCalendarDate(date);
        if (!Number.isNaN(parsedDate.getTime())) {
            calendar.gotoDate(parsedDate);
            // A date inside the current range triggers no datesSet.
            publishMainCalendarDate();
            if (calendar.getView().type.startsWith('list')) {
                options.scrollListToDate?.(parsedDate);
            }
        }
    };

    filterWindow.addEventListener(
        miniCalendarEvents.navigate,
        handleMiniCalendarNavigation,
    );
    filterWindow.addEventListener(
        miniCalendarEvents.dateChange,
        handleMiniCalendarDateChange,
    );
    filterWindow.addEventListener(
        miniCalendarEvents.ready,
        publishMainCalendarDate,
    );
    publishMainCalendarDate();

    return {
        publish: publishMainCalendarDate,
        destroy: (): void => {
            filterWindow.removeEventListener(
                miniCalendarEvents.navigate,
                handleMiniCalendarNavigation,
            );
            filterWindow.removeEventListener(
                miniCalendarEvents.dateChange,
                handleMiniCalendarDateChange,
            );
            filterWindow.removeEventListener(
                miniCalendarEvents.ready,
                publishMainCalendarDate,
            );
        },
    };
}

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
): {destroy: () => void} {
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

        if (detail.date) {
            const targetMonth = parseCalendarDate(detail.date);
            if (!Number.isNaN(targetMonth.getTime())) {
                calendar.gotoDate(targetMonth);
                return;
            }
        }

        const currentStart = calendar.getView().currentStart;
        const monthDelta = direction === 'prev' ? -1 : 1;
        calendar.gotoDate(new Date(
            currentStart.getFullYear(),
            currentStart.getMonth() + monthDelta,
            1,
        ));
    };

    const handleMiniCalendarDateChange = (event: Event): void => {
        const date = (event as CustomEvent<MiniCalendarDateDetail>).detail?.date;
        if (!date) {
            return;
        }

        const parsedDate = parseCalendarDate(date);
        if (!Number.isNaN(parsedDate.getTime())) {
            if (calendar.getView().type.startsWith('list')) {
                calendar.gotoDate(parsedDate);
                options.scrollListToDate?.(parsedDate);
                return;
            }

            calendar.setOption('view', 'timeGridWeek');
            calendar.gotoDate(parsedDate);
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

import type {Calendar} from '@event-calendar/core';
import {parseCalendarDate} from './calendar-date-utils';
import {
    miniCalendarEvents,
    type MainCalendarDateDetail,
    type MiniCalendarDateDetail,
    type MiniCalendarNavigationDetail,
} from './calendar-mini-calendar-events';

export function createCalendarMiniCalendarSync(
    calendar: Calendar,
    filterWindow: Window,
): {destroy: () => void} {
    const publishMainCalendarDate = (): void => {
        const view = calendar.getView();
        filterWindow.dispatchEvent(new CustomEvent<MainCalendarDateDetail>(miniCalendarEvents.mainDateChange, {
            detail: {
                date: view.currentStart.toISOString(),
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

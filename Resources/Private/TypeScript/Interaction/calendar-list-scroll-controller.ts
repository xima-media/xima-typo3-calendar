import type {CalendarEventData} from '../Rendering/calendar-event-data';
import {
    clearCalendarListMultiDayEvents,
    renderCalendarListMultiDayEvents,
} from '../Rendering/calendar-list-multi-day';
import type {CalendarEventRenderer} from '../Rendering/calendar-event-renderer';
import {scrollCalendarListToRelevantDate} from './calendar-list-scroll';

type CalendarListView = {
    type: string;
    currentStart: Date;
};

export type CalendarListScrollController = {
    viewChanged(view: CalendarListView): void;
    eventsLoaded(events: CalendarEventData[]): void;
    scrollToDate(date: Date): void;
    destroy(): void;
};

export const createCalendarListScrollController = (
    container: HTMLElement,
    eventRenderer: CalendarEventRenderer,
    onEventClick: (event: CalendarEventData, element: HTMLElement) => void,
    onEventTooltip: (event: CalendarEventData, element: HTMLElement) => void,
): CalendarListScrollController => {
    let currentEvents: CalendarEventData[] = [];
    let currentViewStart = new Date();
    let currentViewType = '';
    let requestedDate: Date | null = null;
    let scrollFrame: number | null = null;
    let resizeObserver: ResizeObserver | null = null;

    const observeListSize = (): void => {
        resizeObserver?.disconnect();
        resizeObserver = null;

        const scrollContainer = container.querySelector<HTMLElement>('.ec-main');
        if (!scrollContainer) {
            return;
        }

        resizeObserver = new ResizeObserver(() => {
            if (currentViewType.startsWith('list')) {
                scheduleScroll();
            }
        });
        resizeObserver.observe(scrollContainer);
    };

    const scheduleScroll = (): void => {
        if (scrollFrame !== null) {
            window.cancelAnimationFrame(scrollFrame);
        }

        scrollFrame = window.requestAnimationFrame(() => {
            scrollFrame = window.requestAnimationFrame(() => {
                scrollFrame = null;

                if (!currentViewType.startsWith('list')) {
                    return;
                }

                renderCalendarListMultiDayEvents(
                    container,
                    currentEvents,
                    currentViewStart,
                    document.documentElement.lang || 'en',
                    eventRenderer,
                    onEventClick,
                    onEventTooltip,
                );
                const didScroll = scrollCalendarListToRelevantDate({
                    container,
                    viewStart: currentViewStart,
                    events: currentEvents,
                    targetDate: requestedDate ?? undefined,
                });
                if (didScroll) {
                    requestedDate = null;
                }
            });
        });
    };

    return {
        viewChanged(view): void {
            currentViewType = view.type;
            currentViewStart = view.currentStart;

            if (view.type.startsWith('list')) {
                observeListSize();
                scheduleScroll();
            } else {
                requestedDate = null;
                resizeObserver?.disconnect();
                resizeObserver = null;
                if (scrollFrame !== null) {
                    window.cancelAnimationFrame(scrollFrame);
                    scrollFrame = null;
                }
                clearCalendarListMultiDayEvents(container);
            }
        },

        eventsLoaded(events): void {
            currentEvents = events;
            scheduleScroll();
        },

        scrollToDate(date): void {
            requestedDate = new Date(date);
            if (currentViewType.startsWith('list')) {
                scheduleScroll();
            }
        },

        destroy(): void {
            resizeObserver?.disconnect();
            if (scrollFrame !== null) {
                window.cancelAnimationFrame(scrollFrame);
                scrollFrame = null;
            }
        },
    };
};

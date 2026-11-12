import type {CalendarEventData} from '../Rendering/calendar-event-data';
import {
    clearCalendarListMultiDayEvents,
    renderCalendarListMultiDayEvents,
} from '../Rendering/calendar-list-multi-day';
import type {CalendarEventRenderer} from '../Rendering/calendar-event-renderer';
import {scrollCalendarListToRelevantDate} from './calendar-list-scroll';
import {createCalendarListHoverSync} from './calendar-list-hover-sync';

type CalendarListView = {
    type: string;
    currentStart: Date;
};

export type CalendarListScrollController = {
    viewChanged(view: CalendarListView): void;
    eventsLoaded(events: CalendarEventData[]): void;
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
    let scrollFrame: number | null = null;
    let resizeObserver: ResizeObserver | null = null;
    const hoverSync = createCalendarListHoverSync(container);

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
                scrollCalendarListToRelevantDate({
                    container,
                    viewStart: currentViewStart,
                    events: currentEvents,
                });
            });
        });
    };

    return {
        viewChanged(view): void {
            currentViewType = view.type;
            currentViewStart = view.currentStart;
            hoverSync.viewChanged(view.type);

            if (view.type.startsWith('list')) {
                observeListSize();
                scheduleScroll();
            } else {
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

        destroy(): void {
            resizeObserver?.disconnect();
            hoverSync.destroy();
            if (scrollFrame !== null) {
                window.cancelAnimationFrame(scrollFrame);
                scrollFrame = null;
            }
        },
    };
};

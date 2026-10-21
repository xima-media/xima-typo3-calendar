import {contentToText, type CalendarEventData} from './calendar-event-data';
import {createCalendarListLayout} from './calendar-list-layout';
import type {CalendarEventRenderer} from './calendar-event-renderer';

const startOfDay = (date: Date): Date => {
    const result = new Date(date);
    result.setHours(0, 0, 0, 0);
    return result;
};

const getMonthEnd = (monthStart: Date): Date => (
    new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 1)
);

export const clearCalendarListMultiDayEvents = (container: HTMLElement): void => {
    container.querySelectorAll('.xima-calendar-list__multi-day').forEach((panel) => {
        panel.remove();
    });
    container.querySelectorAll('.xima-calendar-list-main--has-sidebar').forEach((list) => {
        list.classList.remove('xima-calendar-list-main--has-sidebar');
    });
};

export const renderCalendarListMultiDayEvents = (
    container: HTMLElement,
    events: CalendarEventData[],
    monthStartValue: Date,
    locale: string,
    eventRenderer: CalendarEventRenderer,
    onEventClick: (event: CalendarEventData, element: HTMLElement) => void,
    onEventTooltip: (event: CalendarEventData, element: HTMLElement) => void,
): void => {
    const list = container.querySelector<HTMLElement>('.ec-list');
    const scrollContainer = container.querySelector<HTMLElement>('.ec-main');
    if (!list || !scrollContainer) {
        return;
    }

    clearCalendarListMultiDayEvents(container);

    const monthStart = startOfDay(monthStartValue);
    const layout = createCalendarListLayout(
        events,
        monthStart,
        getMonthEnd(monthStart),
    );

    list.classList.add('xima-calendar-list-main--has-sidebar');

    const panel = document.createElement('aside');
    panel.className = 'xima-calendar-list__multi-day';
    const columnCount = Math.max(1, layout.columnCount);
    if (columnCount >= 4) {
        panel.classList.add('xima-calendar-list__multi-day--columns-4');
    }
    if (columnCount >= 5) {
        panel.classList.add('xima-calendar-list__multi-day--columns-5');
    }
    panel.style.insetBlockStart = `${scrollContainer.offsetTop}px`;
    panel.style.blockSize = `${scrollContainer.clientHeight}px`;
    panel.style.setProperty('--xima-list-column-count', String(columnCount));
    panel.style.setProperty('--xima-list-day-count', String(layout.dayCount));

    const heading = document.createElement('h3');
    heading.className = 'xima-calendar-list__multi-day-title';
    heading.textContent = locale.startsWith('de')
        ? 'Mehrtägige Termine'
        : 'Multi-day events';
    panel.append(heading);

    const grid = document.createElement('div');
    grid.className = 'xima-calendar-list__multi-day-grid';
    const today = startOfDay(new Date());

    for (let day = 0; day < layout.dayCount; day++) {
        const row = document.createElement('div');
        row.className = 'xima-calendar-list__multi-day-grid-row';
        row.style.gridColumn = '1 / -1';
        row.style.gridRow = String(day + 1);

        const label = document.createElement('span');
        label.className = 'xima-calendar-list__multi-day-day';
        const date = new Date(monthStart);
        date.setDate(date.getDate() + day);
        if (date.getTime() === today.getTime()) {
            row.classList.add('xima-calendar-list__multi-day-grid-row--today');
        }
        label.textContent = String(date.getDate());
        row.append(label);

        grid.append(row);
    }

    for (const lane of layout.lanes) {
        const eventElement = document.createElement('article');
        eventElement.className = [
            'ec-event',
            'xima-calendar-list__multi-day-event',
            ...eventRenderer.getClassNames(lane.event),
        ].join(' ');
        eventElement.setAttribute('role', 'button');
        eventElement.tabIndex = 0;
        // Column 1 is reserved for the day labels. Event lanes start in column 2.
        eventElement.style.gridColumn = String(lane.column + 2);
        eventElement.style.gridRow = `${lane.rowStart} / span ${lane.rowSpan}`;

        const eventStyles = lane.event.styles ?? lane.event.style ?? [];
        eventElement.style.cssText += Array.isArray(eventStyles)
            ? eventStyles.join(';')
            : eventStyles;

        if (lane.event.backgroundColor) {
            eventElement.style.backgroundColor = lane.event.backgroundColor;
        }
        if (lane.event.textColor) {
            eventElement.style.color = lane.event.textColor;
        }

        const eventBody = document.createElement('div');
        eventBody.className = 'ec-event-body';

        const title = document.createElement('span');
        title.className = 'ec-event-title';
        title.textContent = contentToText(lane.event.title);
        eventBody.append(title);
        eventElement.append(eventBody);
        grid.append(eventElement);

        eventRenderer.didMount(lane.event, eventElement, events);
        onEventTooltip(lane.event, eventElement);
        eventElement.addEventListener('click', () => {
            onEventClick(lane.event, eventElement);
        });
        eventElement.addEventListener('keydown', (keyboardEvent) => {
            if (keyboardEvent.key !== 'Enter' && keyboardEvent.key !== ' ') {
                return;
            }

            keyboardEvent.preventDefault();
            onEventClick(lane.event, eventElement);
        });
    }

    panel.append(grid);
    list.append(panel);
};

import {contentToText, type CalendarEventData} from './calendar-event-data';
import type {CalendarFilterConfiguration} from '../Filter/calendar-filter-state';

type CalendarEventTooltip = {
    attach(event: CalendarEventData, element: HTMLElement): void;
    destroy(): void;
};

type CalendarEventTooltipConfiguration = {
    locale: string;
    getFilterConfiguration: () => CalendarFilterConfiguration;
    tooltipOrder: string[];
};

const HOVER_DELAY = 500;

const getExtendedProperty = (
    event: CalendarEventData,
    identifier: string,
): unknown => event.extendedProps?.[identifier];

const getFilterLabel = (
    configuration: CalendarFilterConfiguration,
    identifier: string,
    value: unknown,
): string | null => {
    if (value === undefined || value === null) {
        return null;
    }

    const filter = configuration[identifier];
    const item = filter?.items.find(item => String(item.value) === String(value));

    return item?.label ?? null;
};

const getFilterValue = (
    event: CalendarEventData,
    identifier: string,
): unknown => {
    const extendedProps = event.extendedProps ?? {};
    const filterValues = extendedProps.filterValues;

    if (identifier === 'categories' && extendedProps.eventCategoryTitle) {
        return extendedProps.eventCategoryTitle;
    }

    if (identifier === 'recordTypes' && extendedProps.recordType) {
        return extendedProps.recordType;
    }

    if (identifier === 'statuses') {
        if (extendedProps.appointmentCanceled === true) {
            return 'canceled';
        }

        return extendedProps.eventStatus
            ?? (filterValues && typeof filterValues === 'object'
                ? (filterValues as Record<string, unknown>)[identifier]
                : undefined);
    }

    if (filterValues && typeof filterValues === 'object') {
        const value = (filterValues as Record<string, unknown>)[identifier];
        if (value !== undefined && value !== null) {
            return value;
        }
    }

    return extendedProps[identifier];
};

const formatFilterValue = (
    configuration: CalendarFilterConfiguration,
    identifier: string,
    value: unknown,
): string | null => {
    if (Array.isArray(value)) {
        const labels = value
            .map(item => getFilterLabel(configuration, identifier, item) ?? String(item))
            .filter(Boolean);

        return labels.length > 0 ? labels.join(', ') : null;
    }

    if (value === undefined || value === null || value === '') {
        return null;
    }

    const label = getFilterLabel(configuration, identifier, value);
    if (label) {
        return label;
    }

    return typeof value === 'string' && value.trim() !== ''
        ? value
        : String(value);
};

const getDate = (value: string | Date | undefined): Date | null => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? new Date(value) : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const isSameDay = (first: Date, second: Date): boolean => (
    first.getFullYear() === second.getFullYear()
    && first.getMonth() === second.getMonth()
    && first.getDate() === second.getDate()
);

const getDateRange = (
    event: CalendarEventData,
    locale: string,
): string | null => {
    const start = getDate(event.start);
    const end = getDate(event.end);
    if (!start) {
        return null;
    }

    const dateFormatter = new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const timeFormatter = new Intl.DateTimeFormat(locale, {
        hour: '2-digit',
        minute: '2-digit',
    });
    const allDayLabel = locale.startsWith('de') ? 'Ganztägig' : 'All-day';
    const rangeLabel = locale.startsWith('de') ? 'bis' : 'to';

    if (!end || event.allDay) {
        const lastDay = end ? new Date(end) : start;
        if (event.allDay && end) {
            lastDay.setDate(lastDay.getDate() - 1);
        }

        return isSameDay(start, lastDay)
            ? `${dateFormatter.format(start)}\n${allDayLabel}`
            : `${dateFormatter.format(start)} ${rangeLabel} ${dateFormatter.format(lastDay)}`;
    }

    if (isSameDay(start, end)) {
        return `${dateFormatter.format(start)}\n${timeFormatter.format(start)} ${rangeLabel} ${timeFormatter.format(end)}`;
    }

    return `${dateFormatter.format(start)}, ${timeFormatter.format(start)} ${rangeLabel} ${dateFormatter.format(end)}, ${timeFormatter.format(end)}`;
};

const appendRow = (
    container: HTMLElement,
    label: string,
    value: string,
): void => {
    const row = document.createElement('div');
    row.className = 'xima-calendar-event-tooltip__row';

    const labelElement = document.createElement('span');
    labelElement.className = 'xima-calendar-event-tooltip__label';
    labelElement.textContent = label;
    row.append(labelElement);

    const valueElement = document.createElement('span');
    valueElement.className = 'xima-calendar-event-tooltip__value';
    valueElement.textContent = value;
    row.append(valueElement);

    container.append(row);
};

const appendTimeRow = (container: HTMLElement, value: string): void => {
    const row = document.createElement('div');
    row.className = 'xima-calendar-event-tooltip__row xima-calendar-event-tooltip__row--time';

    const icon = document.createElement('span');
    icon.className = 'xima-calendar-event-tooltip__clock';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8.5"></circle><path d="M12 7v5l3 2"></path></svg>';
    row.append(icon);

    const valueElement = document.createElement('span');
    valueElement.className = 'xima-calendar-event-tooltip__value';
    valueElement.textContent = value;
    row.append(valueElement);

    container.append(row);
};

export const createCalendarEventTooltip = (
    configuration: CalendarEventTooltipConfiguration,
): CalendarEventTooltip => {
    let tooltip: HTMLElement | null = null;
    let activeElement: HTMLElement | null = null;
    let hoverTimer: number | null = null;
    const cleanups = new Map<HTMLElement, () => void>();

    const clearHoverTimer = (): void => {
        if (hoverTimer === null) {
            return;
        }

        window.clearTimeout(hoverTimer);
        hoverTimer = null;
    };

    const hide = (): void => {
        clearHoverTimer();
        activeElement = null;
        tooltip?.remove();
        tooltip = null;
    };

    const show = (event: CalendarEventData, element: HTMLElement): void => {
        hide();
        activeElement = element;

        const eventTitle = contentToText(event.title).trim();
        const dateRange = getDateRange(event, configuration.locale);
        if (!eventTitle && !dateRange) {
            return;
        }

        tooltip = document.createElement('div');
        tooltip.className = 'xima-calendar-event-tooltip';
        tooltip.setAttribute('role', 'tooltip');

        if (eventTitle) {
            const title = document.createElement('div');
            title.className = 'xima-calendar-event-tooltip__title';
            title.textContent = eventTitle;
            tooltip.append(title);
        }

        if (dateRange) {
            appendTimeRow(tooltip, dateRange);
        }

        const filterConfiguration = configuration.getFilterConfiguration();
        const filterIdentifiers = [
            ...configuration.tooltipOrder,
            ...Object.keys(filterConfiguration).filter(
                identifier => !configuration.tooltipOrder.includes(identifier),
            ),
        ];

        filterIdentifiers.forEach(identifier => {
            const filter = filterConfiguration[identifier];
            const value = getFilterValue(event, identifier);
            const formattedValue = formatFilterValue(filterConfiguration, identifier, value);
            const isDefaultEventType = identifier === 'recordTypes'
                && typeof value === 'string'
                && value.trim().toLowerCase() === 'event';

            if (!filter || !formattedValue || isDefaultEventType) {
                return;
            }

            appendRow(tooltip, `${filter.title}:`, formattedValue);
        });

        const location = getExtendedProperty(event, 'appointmentLocation');
        if (
            configuration.tooltipOrder.includes('location')
            && typeof location === 'string'
            && location.trim() !== ''
        ) {
            appendRow(
                tooltip,
                configuration.locale.startsWith('de') ? 'Ort:' : 'Location:',
                location,
            );
        }

        document.body.append(tooltip);

        const eventBounds = element.getBoundingClientRect();
        const tooltipBounds = tooltip.getBoundingClientRect();
        const spacing = 8;
        const left = Math.min(
            Math.max(spacing, eventBounds.left),
            window.innerWidth - tooltipBounds.width - spacing,
        );
        const top = eventBounds.bottom + spacing + tooltipBounds.height <= window.innerHeight
            ? eventBounds.bottom + spacing
            : eventBounds.top - tooltipBounds.height - spacing;

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${Math.max(spacing, top)}px`;
    };

    const attach = (event: CalendarEventData, element: HTMLElement): void => {
        const onMouseEnter = (): void => {
            clearHoverTimer();
            hoverTimer = window.setTimeout(() => show(event, element), HOVER_DELAY);
        };
        const onMouseLeave = (): void => {
            if (activeElement === element) {
                hide();
                return;
            }

            clearHoverTimer();
        };

        element.addEventListener('mouseenter', onMouseEnter);
        element.addEventListener('mouseleave', onMouseLeave);
        cleanups.set(element, () => {
            element.removeEventListener('mouseenter', onMouseEnter);
            element.removeEventListener('mouseleave', onMouseLeave);
        });
    };

    return {
        attach,
        destroy(): void {
            hide();
            cleanups.forEach(cleanup => cleanup());
            cleanups.clear();
        },
    };
};

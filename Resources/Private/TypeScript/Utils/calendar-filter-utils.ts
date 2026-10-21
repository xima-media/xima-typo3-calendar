import type {
    CalendarFilterConfiguration,
    CalendarFilterItem,
} from '../Filter/calendar-filter-state';

export function findFilterItem(
    configuration: CalendarFilterConfiguration,
    identifier: string,
    value: string | number,
): CalendarFilterItem | null {
    const filter = configuration[identifier];

    if (!filter) {
        return null;
    }

    return filter.items.find(
        (item) => String(item.value) === String(value),
    ) ?? null;
}

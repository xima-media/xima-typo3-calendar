import type {CalendarFilterConfiguration} from '../Filter/calendar-filter-state';
import {findFilterItem} from '../Utils/calendar-filter-utils';

export function getAppearanceClass(
    configuration: CalendarFilterConfiguration,
    source: string,
    value: string | number,
): string {
    const item = findFilterItem(configuration, source, value);

    if (!item) {
        return '';
    }

    const appearance = item.appearance || 'default';
    return `xima-calendar-event--${appearance}`;
}

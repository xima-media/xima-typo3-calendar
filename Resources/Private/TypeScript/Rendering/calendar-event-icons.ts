import type {CalendarFilterConfiguration} from '../Filter/calendar-filter-state';
import {findFilterItem} from '../Utils/calendar-filter-utils';
import {
    type CalendarEventData,
    getFilterValue,
} from './calendar-event-data';

export const getEventIcon = (
    event: CalendarEventData,
    filterConfiguration: CalendarFilterConfiguration,
    iconSource: string,
): string | null => {
    const value = getFilterValue(event, iconSource);

    if (value === undefined) {
        return null;
    }

    return findFilterItem(
        filterConfiguration,
        iconSource,
        value,
    )?.icon ?? null;
};

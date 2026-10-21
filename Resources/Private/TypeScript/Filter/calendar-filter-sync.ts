import type {
    CalendarFilterConfiguration,
    CalendarFilterSelection,
} from './calendar-filter-state';

type CalendarFilterSyncConfiguration = {
    filterWindow: Window;
    initialSelection: CalendarFilterSelection;
    initialConfiguration: CalendarFilterConfiguration;
    refetchEvents: () => void;
};

export type CalendarFilterSync = {
    getActiveFilters: () => CalendarFilterSelection;
    getFilterConfiguration: () => CalendarFilterConfiguration;
    start: () => void;
    destroy: () => void;
};

export const createCalendarFilterSync = (
    configuration: CalendarFilterSyncConfiguration,
): CalendarFilterSync => {
    let activeFilters = configuration.initialSelection;
    let filterConfiguration = configuration.initialConfiguration;

    const handleFilterConfigurationLoaded = (event: Event): void => {
        const configurationEvent =
            event as CustomEvent<CalendarFilterConfiguration>;

        filterConfiguration = configurationEvent.detail;
        configuration.refetchEvents();
    };

    const handleFilterChanged = (event: Event): void => {
        const filterEvent =
            event as CustomEvent<CalendarFilterSelection>;

        if (!filterEvent.detail) {
            return;
        }

        activeFilters = filterEvent.detail;
        configuration.refetchEvents();
    };

    const start = (): void => {
        configuration.filterWindow.addEventListener(
            'xima-calendar-filter-configuration-loaded',
            handleFilterConfigurationLoaded,
            {once: true},
        );
        configuration.filterWindow.addEventListener(
            'xima-calendar-filter-changed',
            handleFilterChanged,
        );
    };

    const destroy = (): void => {
        configuration.filterWindow.removeEventListener(
            'xima-calendar-filter-configuration-loaded',
            handleFilterConfigurationLoaded,
        );
        configuration.filterWindow.removeEventListener(
            'xima-calendar-filter-changed',
            handleFilterChanged,
        );
    };

    return {
        getActiveFilters: () => activeFilters,
        getFilterConfiguration: () => filterConfiguration,
        start,
        destroy,
    };
};

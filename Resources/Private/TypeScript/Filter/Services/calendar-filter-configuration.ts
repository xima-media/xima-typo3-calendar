import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import {
  CalendarFilterResponse,
  CalendarFilterState,
} from '../calendar-filter-state';

export async function requestCalendarFilterConfiguration(
  url: string,
): Promise<unknown> {
  const response = await new AjaxRequest(url).get();

  return response.resolve();
}

export function isCalendarFilterResponse(
  value: unknown,
): value is CalendarFilterResponse {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const response = value as Record<string, unknown>;
  const filters = response.filters;
  const filterColors = response.filterColors;
  const state = response.state;
  const labels = response.labels;

  if (
    response.success !== true
    || !filters
    || typeof filters !== 'object'
    || !filterColors
    || typeof filterColors !== 'object'
    || !state
    || typeof state !== 'object'
    || !labels
    || typeof labels !== 'object'
  ) {
    return false;
  }

  const filterState = state as CalendarFilterState;

  return Boolean(filters)
    && typeof filters === 'object'
    && Boolean(filterColors)
    && typeof filterColors === 'object'
    && Boolean(filterState.checkboxLists)
    && typeof filterState.checkboxLists === 'object'
    && Boolean(filterState.trees)
    && typeof filterState.trees === 'object'
    && Boolean(filterState.sections)
    && typeof filterState.sections === 'object';
}

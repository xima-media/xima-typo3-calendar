import type {CheckboxListChangePayload} from '../Components/calendar-filter-checkbox-list';
import type {CalendarFilterSelection, CalendarFilterState} from '../calendar-filter-state';
import type {TreeStateChangePayload} from '../Components/calendar-filter-tree';

export function updateCheckboxListState(
  state: CalendarFilterState,
  change: CheckboxListChangePayload,
): CalendarFilterState {
  const {identifier, value, selected} = change;

  return {
    ...state,
    checkboxLists: {
      ...state.checkboxLists,
      [identifier]: selected
        ? [
          ...new Set([
            ...(state.checkboxLists[identifier] ?? []),
            value,
          ]),
        ]
        : (state.checkboxLists[identifier] ?? [])
          .filter((item) => item !== value),
    },
  };
}

export function updateTreeState(
  state: CalendarFilterState,
  change: TreeStateChangePayload,
): CalendarFilterState {
  return {
    ...state,
    trees: {
      ...state.trees,
      [change.identifier]: {
        activeItems: [...change.activeItems],
        expandedItems: {...change.expandedItems},
      },
    },
  };
}

export function createFilterSelection(
  state: CalendarFilterState,
): CalendarFilterSelection {
  const filters = Object.fromEntries(
    Object.entries(state.trees).map(([identifier, tree]) => [
      identifier,
      [...tree.activeItems],
    ]),
  );

  Object.entries(state.checkboxLists).forEach(([identifier, values]) => {
    filters[identifier] = [...values];
  });

  return {filters};
}

export type CalendarFilterItem = {
  value: string | number;
  label: string;
  appearance?: string;
  icon?: string;
  marker?: string;
};

export type TreeFilterItem = CalendarFilterItem & {
  parentUid: string | number | null;
};

export type TreeCalendarFilter = {
  type: 'tree';
  identifier: string;
  title: string;
  supportsColorCoding: boolean;
  items: TreeFilterItem[];
};

export type CheckboxCalendarFilter = {
  type: 'checkbox-list';
  identifier: string;
  title: string;
  items: CalendarFilterItem[];
};

export type CalendarFilter = TreeCalendarFilter | CheckboxCalendarFilter;

export type CalendarFilterConfiguration = {
  [identifier: string]: CalendarFilter;
};

export type CalendarFilterState = {
  checkboxLists: Record<string, Array<string | number>>;
  trees: Record<string, CalendarFilterTreeState>;
  sections: Record<string, boolean>;
};

export type CalendarFilterTreeState = {
  activeItems: Array<string | number>;
  expandedItems: Record<string, boolean>;
};

export type CalendarFilterSelection = {
  filters: Record<string, Array<string | number>>;
};

export type CalendarFilterResponse = {
  success: boolean;
  filters: CalendarFilterConfiguration;
  filterColors: Record<string, Record<string, string>>;
  state: CalendarFilterState;
  labels: CalendarFilterActionLabels;
};

export type CalendarFilterActionLabels = {
  moreActions: string;
  selectAllChildren: string;
  deselectAllChildren: string;
};

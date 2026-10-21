export type CalendarEventContent = string | {html: string} | {domNodes: Node[]};

export const contentToText = (content: CalendarEventContent | null | undefined): string => {
  if (typeof content === 'string') {
    return content;
  }
  if (content && 'html' in content) {
    return content.html;
  }
  if (content && 'domNodes' in content) {
    return content.domNodes.map((node) => node.textContent ?? '').join('');
  }
  return '';
};

export type CalendarEventData = {
  id?: string | number;
  title?: CalendarEventContent;
  start?: string | Date;
  end?: string | Date;
  allDay?: boolean;
  extendedProps?: Record<string, unknown>;
  backgroundColor?: string;
  textColor?: string;
  style?: string | string[];
  styles?: string | string[];
};

export type FilterValue = string | number;

export const getFilterValue = (
  event: CalendarEventData,
  identifier: string,
): FilterValue | undefined => {
  const filterValues = event.extendedProps?.filterValues;

  if (!filterValues || typeof filterValues !== 'object') {
    return undefined;
  }

  const value = (filterValues as Record<string, unknown>)[identifier];

  return typeof value === 'string' || typeof value === 'number'
    ? value
    : undefined;
};

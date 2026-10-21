import {
  type CalendarEventData,
  getFilterValue,
} from './calendar-event-data';
import {
  type ColorOverrides,
  getColor,
  getTextColor,
} from '../Utils/color-utils';

export const applyEventColorsToEvents = (
  events: CalendarEventData[],
  filterColors: ColorOverrides,
  colorSource: string,
): CalendarEventData[] => events.map((event) => {
  const value = getFilterValue(event, colorSource);

  if (value === undefined) {
    return event;
  }

  const eventColor = getColor(colorSource, value, filterColors);
  const eventTextColor = getTextColor(colorSource, value, filterColors);
  const existingStyles = event.styles ?? event.style ?? [];
  const styles = Array.isArray(existingStyles)
    ? existingStyles
    : [existingStyles];

  return {
    ...event,
    backgroundColor: eventColor,
    textColor: eventTextColor,
    styles: [
      ...styles,
      `--xima-event-color:${eventColor}`,
      `--xima-event-text-color:${eventTextColor}`,
    ],
  };
});

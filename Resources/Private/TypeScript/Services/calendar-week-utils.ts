export type CalendarDayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6;

const firstDayByName: Record<string, CalendarDayOfWeek> = {
    sunday: 0,
    monday: 1,
    tuesday: 2,
    wednesday: 3,
    thursday: 4,
    friday: 5,
    saturday: 6,
};

export const getFirstDayIndex = (firstDay: string): CalendarDayOfWeek => (
    firstDayByName[firstDay.trim().toLowerCase()] ?? firstDayByName.monday
);

export const getWeekdayOffset = (
    date: Date,
    firstDay: CalendarDayOfWeek,
): number => (date.getDay() - firstDay + 7) % 7;

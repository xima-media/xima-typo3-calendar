export function formatLocalCalendarDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function parseCalendarDate(value: string): Date {
    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (dateOnly) {
        return new Date(
            Number(dateOnly[1]),
            Number(dateOnly[2]) - 1,
            Number(dateOnly[3]),
        );
    }

    return new Date(value);
}

export type CalendarApi = {
    getView: () => { currentStart?: Date };
    setOption: (name: string, value: unknown) => CalendarApi;
    unselect: () => CalendarApi;
};

export function createCalendarSelectionNavigation(
    container: HTMLElement,
    calendar: CalendarApi,
    enableDragNewEvent: boolean,
): (event: PointerEvent) => void {
    let navigationLocked = false;

    return (event: PointerEvent): void => {
        if (!enableDragNewEvent || event.buttons === 0) {
            navigationLocked = false;
            return;
        }

        const calendarElement = container.querySelector<HTMLElement>('.ec.ec-selecting');
        const body = calendarElement?.querySelector<HTMLElement>('.ec-body');
        if (!calendarElement || !body) {
            navigationLocked = false;
            return;
        }

        const bodyRect = body.getBoundingClientRect();
        const dayRects = Array.from(calendarElement.querySelectorAll<HTMLElement>('.ec-body .ec-day'))
            .map(day => day.getBoundingClientRect());
        if (dayRects.length === 0) {
            navigationLocked = false;
            return;
        }

        let passedRightEdge = false;
        let passedLeftEdge = false;
        if (calendarElement.classList.contains('ec-time-grid')) {
            const pointerInBody = event.clientY >= bodyRect.top && event.clientY <= bodyRect.bottom;
            passedRightEdge = event.clientX >= bodyRect.right && pointerInBody;
            passedLeftEdge = event.clientX <= bodyRect.left && pointerInBody;
        } else {
            const rows = new Map<number, DOMRect[]>();
            dayRects.forEach(rect => {
                const row = Math.round(rect.top);
                rows.set(row, [...(rows.get(row) ?? []), rect]);
            });
            const rowBounds = Array.from(rows.entries()).sort(([first], [second]) => first - second);
            const firstRow = rowBounds[0][1];
            const lastRow = rowBounds[rowBounds.length - 1][1];
            const pointerInFirstRow = event.clientY >= Math.min(...firstRow.map(rect => rect.top))
                && event.clientY <= Math.max(...firstRow.map(rect => rect.bottom));
            const pointerInLastRow = event.clientY >= Math.min(...lastRow.map(rect => rect.top))
                && event.clientY <= Math.max(...lastRow.map(rect => rect.bottom));
            passedRightEdge = event.clientX >= bodyRect.right && pointerInLastRow;
            passedLeftEdge = event.clientX <= bodyRect.left && pointerInFirstRow;
        }

        if (!passedRightEdge && !passedLeftEdge) {
            navigationLocked = false;
            return;
        }
        if (navigationLocked) {
            return;
        }

        const currentStart = calendar.getView().currentStart;
        if (!currentStart) {
            return;
        }

        const nextDate = new Date(currentStart);
        if (calendarElement.classList.contains('ec-time-grid')) {
            nextDate.setDate(nextDate.getDate() + (passedRightEdge ? 7 : -7));
        } else {
            nextDate.setMonth(nextDate.getMonth() + (passedRightEdge ? 1 : -1));
        }
        navigationLocked = true;
        calendar.setOption('date', nextDate);
    };
}

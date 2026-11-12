const hoveredRowClass = 'xima-calendar-list__multi-day-grid-row--hovered';

const getDayElement = (target: EventTarget | null): HTMLElement | null => {
    if (!(target instanceof HTMLElement)) {
        return null;
    }

    return target.closest<HTMLElement>('.ec-day');
};

const getDateKey = (day: HTMLElement): string | null => (
    day.querySelector<HTMLElement>('time[datetime]')?.getAttribute('datetime')?.slice(0, 10) ?? null
);

export type CalendarListHoverSync = {
    viewChanged(viewType: string): void;
    destroy(): void;
};

export const createCalendarListHoverSync = (container: HTMLElement): CalendarListHoverSync => {
    let currentRow: HTMLElement | null = null;
    let enabled = false;

    const clearHoveredRow = (): void => {
        currentRow?.classList.remove(hoveredRowClass);
        currentRow = null;
    };

    const updateHoveredRow = (target: EventTarget | null): void => {
        const day = getDayElement(target);
        const dateKey = day ? getDateKey(day) : null;
        const nextRow = dateKey
            ? Array.from(container.querySelectorAll<HTMLElement>('[data-xima-calendar-date]'))
                .find(row => row.dataset.ximaCalendarDate === dateKey) ?? null
            : null;

        if (nextRow === currentRow) {
            return;
        }

        clearHoveredRow();
        currentRow = nextRow;
        currentRow?.classList.add(hoveredRowClass);
    };

    const handlePointerOver = (event: PointerEvent): void => {
        if (enabled) {
            updateHoveredRow(event.target);
        }
    };

    const handlePointerOut = (event: PointerEvent): void => {
        if (!enabled) {
            return;
        }

        const day = getDayElement(event.target);
        const relatedDay = getDayElement(event.relatedTarget);
        if (day && day === relatedDay) {
            return;
        }

        if (!relatedDay) {
            clearHoveredRow();
            return;
        }

        updateHoveredRow(relatedDay);
    };

    container.addEventListener('pointerover', handlePointerOver);
    container.addEventListener('pointerout', handlePointerOut);

    return {
        viewChanged(viewType): void {
            enabled = viewType.startsWith('list');
            if (!enabled) {
                clearHoveredRow();
            }
        },

        destroy(): void {
            container.removeEventListener('pointerover', handlePointerOver);
            container.removeEventListener('pointerout', handlePointerOut);
            clearHoveredRow();
        },
    };
};

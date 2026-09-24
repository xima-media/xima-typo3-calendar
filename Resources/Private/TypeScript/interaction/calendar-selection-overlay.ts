export function createCalendarSelectionOverlay(container: HTMLElement): {
    clear: () => void;
    hidePreview: () => void;
    resetPreview: () => void;
    scheduleUpdate: () => void;
    destroy: () => void;
} {
    let selectionOverlayFrame: number | undefined;

    const clear = (): void => {
        document.querySelectorAll('.xima-calendar-selection-overlay').forEach(overlay => overlay.remove());
    };

    const hidePreview = (): void => {
        container.classList.add('xima-calendar-selection-cancelled');
        container.querySelectorAll<HTMLElement>('.ec-event.ec-preview, .ec-events.ec-preview').forEach(preview => preview.remove());
        clear();
    };

    const resetPreview = (): void => {
        container.classList.remove('xima-calendar-selection-cancelled');
    };

    const append = (left: number, top: number, right: number, bottom: number): void => {
        const overlay = document.createElement('div');
        overlay.className = 'xima-calendar-selection-overlay';
        overlay.style.left = `${left}px`;
        overlay.style.top = `${top}px`;
        overlay.style.width = `${right - left}px`;
        overlay.style.height = `${bottom - top}px`;
        document.body.appendChild(overlay);
    };

    const update = (): void => {
        clear();
        const calendarElement = container.querySelector<HTMLElement>('.ec.ec-selecting');
        if (!calendarElement) {
            return;
        }

        if (calendarElement.classList.contains('ec-time-grid')) {
            const previews = Array.from(calendarElement.querySelectorAll<HTMLElement>('.ec-body .ec-event.ec-preview'));
            const bodyRect = calendarElement.querySelector<HTMLElement>('.ec-body')?.getBoundingClientRect();
            if (previews.length === 0 || !bodyRect) {
                return;
            }

            const previewRects = previews.map(preview => preview.getBoundingClientRect());
            const selectedDays = Array.from(calendarElement.querySelectorAll<HTMLElement>('.ec-body .ec-day'))
                .filter(day => {
                    const dayRect = day.getBoundingClientRect();
                    return previewRects.some(previewRect => dayRect.right > previewRect.left && dayRect.left < previewRect.right);
                });
            previews.forEach((preview, index) => {
                const previewRect = previewRects[index];
                const day = selectedDays.find(candidate => {
                    const dayRect = candidate.getBoundingClientRect();
                    return dayRect.right > previewRect.left && dayRect.left < previewRect.right;
                });
                if (!day) {
                    return;
                }

                const dayRect = day.getBoundingClientRect();
                const left = Math.max(dayRect.left, bodyRect.left);
                const right = Math.min(dayRect.right, bodyRect.right);
                const top = Math.max(previewRect.top, bodyRect.top);
                const bottom = Math.min(previewRect.bottom, bodyRect.bottom);
                if (right > left && bottom > top) {
                    append(left, top, right, bottom);
                }
            });
            return;
        }

        if (!calendarElement.classList.contains('ec-day-grid')) {
            return;
        }

        const previews = Array.from(calendarElement.querySelectorAll<HTMLElement>('.ec-events.ec-preview > .ec-event'));
        if (previews.length === 0) {
            return;
        }
        const previewRects = previews.map(preview => preview.getBoundingClientRect());
        const rows = new Map<number, DOMRect[]>();
        Array.from(calendarElement.querySelectorAll<HTMLElement>('.ec-body .ec-day'))
            .filter(day => {
                const dayRect = day.getBoundingClientRect();
                return previewRects.some(previewRect =>
                    dayRect.right > previewRect.left && dayRect.left < previewRect.right
                    && dayRect.bottom > previewRect.top && dayRect.top < previewRect.bottom,
                );
            })
            .forEach(day => {
                const rect = day.getBoundingClientRect();
                const row = Math.round(rect.top);
                rows.set(row, [...(rows.get(row) ?? []), rect]);
            });

        rows.forEach(rects => append(
            Math.min(...rects.map(rect => rect.left)),
            Math.min(...rects.map(rect => rect.top)),
            Math.max(...rects.map(rect => rect.right)),
            Math.max(...rects.map(rect => rect.bottom)),
        ));
    };

    const scheduleUpdate = (): void => {
        if (selectionOverlayFrame !== undefined) {
            return;
        }
        selectionOverlayFrame = requestAnimationFrame(() => {
            selectionOverlayFrame = undefined;
            update();
        });
    };

    return {
        clear,
        hidePreview,
        resetPreview,
        scheduleUpdate,
        destroy: (): void => {
            if (selectionOverlayFrame !== undefined) {
                cancelAnimationFrame(selectionOverlayFrame);
            }
            clear();
        },
    };
}

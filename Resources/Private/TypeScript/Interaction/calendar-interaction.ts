import type {Calendar} from '@event-calendar/core';
import {createCalendarSelectionNavigation} from './calendar-selection-navigation';
import {createCalendarSelectionOverlay} from './calendar-selection-overlay';

type CalendarCreationController = {
    cancelSelection: () => void;
};

type CalendarInteractionOptions = {
    enableDragNewEvent: boolean;
};

export function createCalendarInteractionController(
    container: HTMLElement,
    calendar: Calendar,
    creationController: CalendarCreationController,
    options: CalendarInteractionOptions,
): {destroy: () => void} {
    const overlay = createCalendarSelectionOverlay(container);
    const navigateSelection = createCalendarSelectionNavigation(
        container,
        calendar,
        options.enableDragNewEvent,
    );

    const calendarObserver = new MutationObserver(overlay.scheduleUpdate);
    const onPointerUp = (): void => {
        window.setTimeout(() => {
            overlay.clear();
            overlay.resetPreview();
        }, 0);
    };
    const onPointerCancel = (): void => {
        overlay.clear();
        overlay.resetPreview();
    };
    const cancelSelectionOnEscape = (event: KeyboardEvent): void => {
        if (event.key !== 'Escape' || !container.querySelector('.ec.ec-selecting')) {
            return;
        }

        creationController.cancelSelection();
        overlay.hidePreview();
        calendar.unselect();
        window.dispatchEvent(new PointerEvent('pointercancel', {isPrimary: true}));
        event.preventDefault();
        event.stopPropagation();
    };

    calendarObserver.observe(container, {childList: true, subtree: true});
    container.addEventListener('pointermove', overlay.scheduleUpdate);
    document.addEventListener('pointermove', navigateSelection);
    container.addEventListener('pointerup', onPointerUp);
    container.addEventListener('pointercancel', onPointerCancel);
    document.addEventListener('keydown', cancelSelectionOnEscape, true);

    return {
        destroy: (): void => {
            calendarObserver.disconnect();
            overlay.destroy();
            container.removeEventListener('pointermove', overlay.scheduleUpdate);
            document.removeEventListener('pointermove', navigateSelection);
            container.removeEventListener('pointerup', onPointerUp);
            container.removeEventListener('pointercancel', onPointerCancel);
            document.removeEventListener('keydown', cancelSelectionOnEscape, true);
        },
    };
}

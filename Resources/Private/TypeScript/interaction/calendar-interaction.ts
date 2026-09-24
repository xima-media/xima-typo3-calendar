import {createCalendarSelectionNavigation, type CalendarApi} from './calendar-selection-navigation';
import {createCalendarSelectionOverlay} from './calendar-selection-overlay';

type CalendarCreationController = {
    cancelSelection: () => void;
};

type CalendarInteractionOptions = {
    firstDay: number;
    enableDragNewEvent: boolean;
};

export function createCalendarInteractionController(
    container: HTMLElement,
    calendar: CalendarApi,
    creationController: CalendarCreationController,
    options: CalendarInteractionOptions,
): {destroy: () => void} {
    const overlay = createCalendarSelectionOverlay(container);
    const navigateSelection = createCalendarSelectionNavigation(
        container,
        calendar,
        options.enableDragNewEvent,
    );

    const highlightCurrentWeekday = (): void => {
        const today = new Date();
        const currentWeekday = (today.getDay() - options.firstDay + 7) % 7;
        container.querySelectorAll<HTMLElement>('.ec-header .ec-days .ec-day').forEach((header, index) => {
            header.classList.toggle('active', index === currentWeekday);
        });
    };

    const calendarObserver = new MutationObserver(() => {
        highlightCurrentWeekday();
        overlay.scheduleUpdate();
    });
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
    requestAnimationFrame(highlightCurrentWeekday);
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

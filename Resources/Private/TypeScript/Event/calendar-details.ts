import Viewport from '@typo3/backend/viewport.js';
import type {Typo3TopWindow} from '../Configuration/calendar-runtime-config';

type EventCalendarEventClickInfo = {
    event: {
        id?: string | number;
        extendedProps: Record<string, unknown>;
    };
    el: HTMLElement;
};

type CalendarDetailsController = {
    eventClick: (info: EventCalendarEventClickInfo) => void;
    datesSet: () => void;
};

export function createCalendarDetailsController(
    container: HTMLElement,
    typo3Top: Typo3TopWindow,
): CalendarDetailsController {
    const openEventEditor = (info: EventCalendarEventClickInfo): void => {
        const eventUid = info.event.extendedProps.eventUid;
        if (typeof eventUid !== 'number' && typeof eventUid !== 'string') {
            return;
        }

        const table = 'tx_ximatypo3calendar_domain_model_event';
        const returnUrl = document.location.pathname + document.location.search;

        Viewport.ContentContainer.setUrl(
            typo3Top.TYPO3.settings.FormEngine.moduleUrl
            + '&edit[' + table + '][' + eventUid + ']=edit'
            + '&module=' + encodeURIComponent(typo3Top.TYPO3.ModuleMenu.App.getCurrentModule())
            + '&returnUrl=' + returnUrl,
        );
    };

    return {
        eventClick: openEventEditor,
        datesSet: (): void => undefined,
    };
}

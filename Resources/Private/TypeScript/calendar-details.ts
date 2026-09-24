import Viewport from '@typo3/backend/viewport.js';
import type {Typo3TopWindow} from './calendar-runtime-config';

type EventCalendarEventClickInfo = {
    event: {
        id?: string | number;
        title?: string | null;
        extendedProps: Record<string, unknown>;
    };
    el: HTMLElement;
    view?: {
        type?: string;
    };
};

type CalendarDetailsController = {
    eventClick: (info: EventCalendarEventClickInfo) => void;
    datesSet: () => void;
};

export function createCalendarDetailsController(
    container: HTMLElement,
    typo3Top: Typo3TopWindow,
): CalendarDetailsController {
    const content = container.closest('.xima-calendar-content');
    const detailPanel = content?.querySelector<HTMLElement>('.xima-calendar-event-detail');
    const detailTitle = detailPanel?.querySelector<HTMLElement>('.xima-calendar-event-detail__title');
    let inlineDetail: HTMLElement | null = null;
    let inlineDetailEventId: string | number | null = null;

    const clearInlineDetail = (): void => {
        inlineDetail?.remove();
        inlineDetail = null;
        inlineDetailEventId = null;
    };

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
        datesSet: clearInlineDetail,
        eventClick: (info: EventCalendarEventClickInfo): void => {
            const eventId = info.event.id ?? null;

            if (inlineDetail && inlineDetailEventId === eventId) {
                clearInlineDetail();
                return;
            }

            clearInlineDetail();

            if (info.view?.type?.startsWith('list')) {
                inlineDetail = document.createElement('div');
                inlineDetail.className = 'xima-calendar-inline-event-detail';
                inlineDetail.textContent = info.event.title ?? '';
                info.el.insertAdjacentElement('afterend', inlineDetail);
                inlineDetailEventId = eventId;
                return;
            }

            if (detailTitle && detailPanel?.offsetParent !== null && window.innerHeight >= 930) {
                detailTitle.textContent = info.event.title ?? '';
                return;
            }

            openEventEditor(info);
        },
    };
}

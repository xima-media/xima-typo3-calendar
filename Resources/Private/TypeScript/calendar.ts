import Calendar from '@event-calendar/core';
import DayGrid from '@event-calendar/day-grid';
import TimeGrid from '@event-calendar/time-grid';
import List from '@event-calendar/list';
import '@event-calendar/core/index.css';
import Viewport from "@typo3/backend/viewport.js";
import DocumentService from "@typo3/core/document-service.js";

DocumentService.ready().then(() => {
    const container = document.getElementById('xima-calendar-mount');
    if (!container) {
        return;
    }

    const ajaxUrl = container.dataset.ajaxUrl ?? '';
    const locale = container.dataset.locale || undefined;
    const firstDay = Number.parseInt(container.dataset.firstDay ?? '', 10);

    const labels: Record<string, string> = (window as any).TYPO3?.lang ?? (top as any)?.TYPO3?.lang ?? {};
    const label = (key: string, fallback: string): string => labels['calendar.button.' + key] ?? fallback;

    type ButtonText = Record<string, string>;
    const navigationLabels = (unit: 'month' | 'week') => ({
        buttonText: (text: ButtonText): ButtonText => ({
            ...text,
            prev: label('prev.' + unit, text.prev),
            next: label('next.' + unit, text.next),
        }),
    });

    const getSelectedUids = (): number[] =>
        Array.from(document.querySelectorAll<HTMLInputElement>('.xima-cal-filter__checkbox:checked'))
            .map(cb => parseInt(cb.value, 10));

    const ec = new Calendar({
        target: container,
        props: {
            plugins: [DayGrid, TimeGrid, List],
            options: {
                view: 'dayGridMonth',
                locale,
                firstDay: Number.isNaN(firstDay) ? 0 : firstDay,
                buttonText: (text: ButtonText): ButtonText => ({
                    ...text,
                    today: label('today', text.today),
                    dayGridMonth: label('dayGridMonth', text.dayGridMonth),
                    timeGridWeek: label('timeGridWeek', text.timeGridWeek),
                    listMonth: label('listMonth', text.listMonth),
                }),
                views: {
                    dayGridMonth: navigationLabels('month'),
                    timeGridWeek: navigationLabels('week'),
                    listMonth: navigationLabels('month'),
                },
                headerToolbar: {
                    start: 'prev,next today',
                    center: 'title',
                    end: 'dayGridMonth,timeGridWeek,listMonth',
                },
                eventSources: [
                    {
                        url: ajaxUrl
                    },
                ],
              eventClick: (info) => {
                  const eventUid = info.event.extendedProps.eventUid;
                  if (eventUid) {

                    const overrideVals = '';
                    const table = 'tx_ximatypo3calendar_domain_model_event';
                    const returnUrl = document.location.pathname + document.location.search;

                    Viewport.ContentContainer.setUrl(
                      top.TYPO3.settings.FormEngine.moduleUrl
                      + '&edit[' + table + '][' + eventUid + ']=edit'
                      + overrideVals
                      + '&module=' + encodeURIComponent(top.TYPO3.ModuleMenu.App.getCurrentModule())
                      + '&returnUrl=' + returnUrl,
                    );
                  }
              }
            },
        },
    });

    document.querySelectorAll<HTMLInputElement>('.xima-cal-filter__checkbox').forEach(cb => {
        cb.addEventListener('change', () => ec.refetchEvents());
    });
});

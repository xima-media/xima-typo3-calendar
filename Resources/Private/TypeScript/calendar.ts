import Calendar from '@event-calendar/core';
import DayGrid from '@event-calendar/day-grid';
import TimeGrid from '@event-calendar/time-grid';
import List from '@event-calendar/list';
import '@event-calendar/core/index.css';
import Viewport from "@typo3/backend/viewport.js";

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('xima-calendar-mount');
    if (!container) {
        return;
    }

    const ajaxUrl = container.dataset.ajaxUrl ?? '';

    const getSelectedUids = (): number[] =>
        Array.from(document.querySelectorAll<HTMLInputElement>('.xima-cal-filter__checkbox:checked'))
            .map(cb => parseInt(cb.value, 10));

    const ec = new Calendar({
        target: container,
        props: {
            plugins: [DayGrid, TimeGrid, List],
            options: {
                view: 'dayGridMonth',
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

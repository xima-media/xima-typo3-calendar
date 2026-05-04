import Calendar from '@event-calendar/core';
import DayGrid from '@event-calendar/day-grid';
import TimeGrid from '@event-calendar/time-grid';
import List from '@event-calendar/list';
import '@event-calendar/core/index.css';

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
            },
        },
    });

    document.querySelectorAll<HTMLInputElement>('.xima-cal-filter__checkbox').forEach(cb => {
        cb.addEventListener('change', () => ec.refetchEvents());
    });
});

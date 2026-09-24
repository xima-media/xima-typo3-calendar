import Calendar from '@event-calendar/core';
import DayGrid from '@event-calendar/day-grid';
import TimeGrid from '@event-calendar/time-grid';
import List from '@event-calendar/list';
import DocumentService from '@typo3/core/document-service.js';
import {createCalendarDetailsController} from './calendar-details';

type EventCalendarTheme = Record<string, string | string[]>;
type EventCalendarButtonText = Record<string, string>;
type Typo3TopWindow = Window & {
    TYPO3: {
        settings: {
            FormEngine: {
                moduleUrl: string;
            };
        };
        ModuleMenu: {
            App: {
                getCurrentModule: () => string;
            };
        };
    };
};

DocumentService.ready().then(() => {
    const container = document.getElementById('xima-calendar-mount');
    if (!container) {
        return;
    }

    const ajaxUrl = container.dataset.ajaxUrl ?? '';
    const calendarOptions = {
        firstDay: 0,
    };

    const typo3Top = window.top as unknown as Typo3TopWindow;
    const detailsController = createCalendarDetailsController(container, typo3Top);

    const ec = new Calendar({
        target: container,
        props: {
            plugins: [DayGrid, TimeGrid, List],
            options: {
                ...calendarOptions,
                height: '100%',
                scrollTime: '08:00:00',
                dayMaxEvents: true,
                moreLinkContent: ({num}: {num: number}) => `+${num} weitere`,
                view: 'dayGridMonth',
                theme: (theme: EventCalendarTheme) => ({
                    ...theme,
                    button: 'btn btn-default',
                    buttonGroup: 'btn-group',
                    active: 'active',
                }),
                buttonText: (buttonText: EventCalendarButtonText) => ({
                    ...buttonText,
                    dayGridMonth: 'Month',
                    timeGridWeek: 'Week',
                    listMonth: 'List',
                    today: 'Today',
                }),
                headerToolbar: {
                    start: 'prev next today',
                    center: 'title',
                    end: 'dayGridMonth,timeGridWeek,listMonth',
                },
                datesSet: detailsController.datesSet,
                eventSources: [
                    {
                        url: ajaxUrl,
                    },
                ],
                eventClick: detailsController.eventClick,
            },
        },
    });

    const highlightCurrentWeekday = (): void => {
        const today = new Date();
        const currentWeekday = (today.getDay() - calendarOptions.firstDay + 7) % 7;
        const weekdayHeaders = container.querySelectorAll<HTMLElement>('.ec-header .ec-days .ec-day');

        weekdayHeaders.forEach((header, index) => {
            header.classList.toggle('active', index === currentWeekday);
        });
    };

    const calendarObserver = new MutationObserver(highlightCurrentWeekday);
    calendarObserver.observe(container, {childList: true, subtree: true});
    requestAnimationFrame(highlightCurrentWeekday);

    document.querySelectorAll<HTMLInputElement>('.xima-cal-filter__checkbox').forEach(cb => {
        cb.addEventListener('change', () => ec.refetchEvents());
    });
});

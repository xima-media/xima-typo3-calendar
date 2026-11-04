import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Viewport from '@typo3/backend/viewport.js';
import {
    chooseCalendarCreationType,
    type CalendarCreationType,
    type CalendarCreationValues,
} from './calendar-creation-modal';
import type {CalendarConfig} from '../Configuration/calendar-runtime-config';
import {
    getSelectedDayCount,
    prepareCalendarSelection,
    type CalendarDateClick,
    type CalendarSelection,
} from '../Interaction/calendar-selection';
import {getCalendarLanguageLabel} from '../Services/calendar-language';
type NewRecordResponse = { success: boolean; url?: string };

export function createCalendarCreationController(
    container: HTMLElement,
    calendarConfig: CalendarConfig,
): {
    select: (selection: CalendarSelection) => void;
    dateClick: (click: CalendarDateClick) => void;
    openForType: (type: CalendarCreationType) => void;
    cancelSelection: () => void;
    setClearCalendarSelection: (clearSelection: () => void) => void;
} {
    let selectionCancelled = false;
    let clearCalendarSelection: (() => void) | undefined;
    const canCreate = (type: CalendarCreationValues['type']): boolean => (
        Boolean(calendarConfig.newRecordUrl)
        && (type === 'event'
            ? calendarConfig.canCreateEvent && calendarConfig.canCreateAppointment
            : calendarConfig.canCreateAppointment)
    );

    const isMonthView = (): boolean => container.querySelector('.ec-day-grid') !== null;
    const modalLabels = calendarConfig.labels;

    const showCreationError = (): void => {
        Notification.error(
            getCalendarLanguageLabel('creation.error.title'),
            getCalendarLanguageLabel('creation.error.message'),
        );
    };

    // The server only returns the URL of the native new-record form. Nothing is stored
    // until the editor saves that form, so closing it leaves nothing to clean up.
    const openNewRecordForm = async (selection: CalendarCreationValues): Promise<void> => {
        if (!canCreate(selection.type)) {
            return;
        }

        const response = await new AjaxRequest(calendarConfig.newRecordUrl).post({
            start: Math.floor(selection.start.getTime() / 1000),
            end: Math.floor(selection.end.getTime() / 1000),
            allDay: selection.allDay ? 1 : 0,
            type: selection.type,
            ...(selection.recordType === null ? {} : {recordType: selection.recordType}),
            ...(selection.calendarUid === null ? {} : {calendarUid: selection.calendarUid}),
        });
        const result = await response.resolve() as NewRecordResponse;
        if (!result.success || !result.url) {
            throw new Error('The new-record form could not be opened');
        }

        Viewport.ContentContainer.setUrl(result.url);
    };

    const openCreationDialog = (
        start: Date,
        end: Date,
        allDay: boolean,
        initialType: CalendarCreationType = 'event-appointment',
    ): void => {
        const types = (['event', 'event-appointment'] as const).filter(canCreate);
        if (types.length === 0) {
            return;
        }
        container.classList.add('xima-calendar-selection-dialog-open');
        void chooseCalendarCreationType({
            labels: modalLabels,
            types,
            initialType: types.includes(initialType) ? initialType : types[0],
            calendars: calendarConfig.calendars,
            initialCalendarUid: calendarConfig.lastCalendarUid,
            initialStart: start,
            initialEnd: end,
            initialAllDay: allDay,
            defaultRecordType: calendarConfig.defaultRecordType,
        })
            .then((creation) => {
                if (creation !== null) {
                    return openNewRecordForm(creation);
                }
            })
            .catch(showCreationError)
            .finally(() => {
                clearCalendarSelection?.();
                container.classList.remove('xima-calendar-selection-dialog-open');
            });
    };

    const cancelSelection = (): void => {
        selectionCancelled = true;
    };

    const prepareSelection = (selection: CalendarSelection, forceAllDay = false): CalendarSelection => (
        prepareCalendarSelection(selection, {
            startTime: calendarConfig.defaultStartTime,
            endTime: calendarConfig.defaultEndTime,
            allDay: calendarConfig.defaultAllDay,
        }, forceAllDay)
    );

    return {
        select: (selection: CalendarSelection): void => {
            if (selectionCancelled) {
                selectionCancelled = false;
                return;
            }
            const forceAllDay = isMonthView() && selection.allDay && getSelectedDayCount(selection) >= 2;
            const preparedSelection = prepareSelection(selection, forceAllDay);
            openCreationDialog(preparedSelection.start, preparedSelection.end, preparedSelection.allDay);
        },
        dateClick: (click: CalendarDateClick): void => {
            const start = new Date(click.date);
            const end = new Date(start.getTime() + (click.allDay ? 86400000 : 1800000));
            const preparedSelection = prepareSelection({start, end, allDay: click.allDay});
            openCreationDialog(preparedSelection.start, preparedSelection.end, preparedSelection.allDay);
        },
        openForType: (type: CalendarCreationType): void => {
            const start = new Date();
            start.setHours(0, 0, 0, 0);
            const end = new Date(start.getTime() + 86400000);
            const preparedSelection = prepareSelection({start, end, allDay: true});
            openCreationDialog(preparedSelection.start, preparedSelection.end, preparedSelection.allDay, type);
        },
        cancelSelection,
        setClearCalendarSelection: (clearSelection: () => void): void => {
            clearCalendarSelection = clearSelection;
        },
    };
}

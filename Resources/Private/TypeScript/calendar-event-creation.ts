import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import Viewport from '@typo3/backend/viewport.js';
import {chooseCalendarCreationType, type CalendarCreationValues} from './calendar-creation-modal';
import type {CalendarConfig, Typo3TopWindow} from './calendar-runtime-config';
import {
    getSelectedDayCount,
    prepareCalendarSelection,
    type CalendarDateClick,
    type CalendarSelection,
} from './interaction/calendar-selection';
type CreateEventResponse = { success: boolean; eventUid?: number; entryUid?: number };

const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
const PENDING_EVENT_STORAGE_KEY = 'xima_calendar_pending_event';
const PENDING_ENTRY_STORAGE_KEY = 'xima_calendar_pending_entry';
const PENDING_EVENT_QUERY_PARAM = 'ximaCalendarPendingEvent';
const PENDING_ENTRY_QUERY_PARAM = 'ximaCalendarPendingEntry';

export function createCalendarCreationController(
    container: HTMLElement,
    typo3Top: Typo3TopWindow,
    calendarConfig: CalendarConfig,
): {
    select: (selection: CalendarSelection) => void;
    dateClick: (click: CalendarDateClick) => void;
    cleanupPendingCreation: () => Promise<boolean>;
    cancelSelection: () => void;
    setClearCalendarSelection: (clearSelection: () => void) => void;
} {
    let selectionCancelled = false;
    let clearCalendarSelection: (() => void) | undefined;
    const canCreate = (type: CalendarCreationValues['type']): boolean => (
        Boolean(calendarConfig.createEventUrl)
        && (type === 'event'
            ? calendarConfig.canCreateEvent && calendarConfig.canCreateAppointment
            : calendarConfig.canCreateAppointment)
    );

    const isMonthView = (): boolean => container.querySelector('.ec-day-grid') !== null;
    const modalLabels = calendarConfig.labels;

    const openRecordForm = (table: string, uid: number): void => {
        const params = new URLSearchParams();
        params.set(`edit[${table}][${uid}]`, 'edit');
        params.set('module', typo3Top.TYPO3.ModuleMenu.App.getCurrentModule());
        const returnUrl = new URL(document.location.href);
        returnUrl.searchParams.delete(PENDING_EVENT_QUERY_PARAM);
        returnUrl.searchParams.delete(PENDING_ENTRY_QUERY_PARAM);
        returnUrl.searchParams.set(
            table === EVENT_TABLE ? PENDING_EVENT_QUERY_PARAM : PENDING_ENTRY_QUERY_PARAM,
            String(uid),
        );
        params.set('returnUrl', returnUrl.pathname + returnUrl.search);

        const moduleUrl = typo3Top.TYPO3.settings.FormEngine.moduleUrl;
        Viewport.ContentContainer.setUrl(`${moduleUrl}&${params.toString()}`);
    };

    const showCreationError = (): void => {
        Notification.error(
            'Error',
            'The event could not be created. Please try again.',
        );
    };

    const createEvent = async (selection: CalendarCreationValues): Promise<void> => {
        if (!canCreate(selection.type)) {
            return;
        }

        const response = await new AjaxRequest(calendarConfig.createEventUrl).post({
            start: Math.floor(selection.start.getTime() / 1000),
            end: Math.floor(selection.end.getTime() / 1000),
            allDay: selection.allDay ? 1 : 0,
            type: selection.type,
            ...(selection.calendarUid === null ? {} : {calendarUid: selection.calendarUid}),
        });
        const result = await response.resolve() as CreateEventResponse;

        if (!result.success) {
            throw new Error('Event creation failed');
        }

        if (selection.type === 'event-appointment' && result.entryUid) {
            sessionStorage.setItem(PENDING_ENTRY_STORAGE_KEY, String(result.entryUid));
            openRecordForm('tx_ximatypo3calendar_domain_model_entry', result.entryUid);
        } else if (result.eventUid) {
            sessionStorage.setItem(PENDING_EVENT_STORAGE_KEY, String(result.eventUid));
            openRecordForm(EVENT_TABLE, result.eventUid);
        } else {
            throw new Error('Creation response is incomplete');
        }
    };

    const openCreationDialog = (
        start: Date,
        end: Date,
        allDay: boolean,
    ): void => {
        container.classList.add('xima-calendar-selection-dialog-open');
        void chooseCalendarCreationType(modalLabels, calendarConfig.calendars, start, end, allDay)
            .then((creation) => {
                if (creation !== null) {
                    return createEvent(creation);
                }
            })
            .catch(showCreationError)
            .finally(() => {
                clearCalendarSelection?.();
                container.classList.remove('xima-calendar-selection-dialog-open');
            });
    };

    const cleanupRecord = async (
        parameter: string,
        storageKey: string,
        queryParameter: string,
    ): Promise<boolean> => {
        const cleanupUrl = calendarConfig.cleanupEventUrl;
        const uid = sessionStorage.getItem(storageKey)
            ?? new URLSearchParams(document.location.search).get(queryParameter);
        if (!uid || !cleanupUrl) {
            return false;
        }

        try {
            const url = new URL(cleanupUrl, document.location.origin);
            url.searchParams.set(parameter, uid);
            const response = await new AjaxRequest(url).get();
            const result = await response.resolve() as { success?: boolean };
            if (result.success) {
                sessionStorage.removeItem(storageKey);
                const currentUrl = new URL(document.location.href);
                if (currentUrl.searchParams.get(queryParameter) === uid) {
                    currentUrl.searchParams.delete(queryParameter);
                    window.history.replaceState({}, '', currentUrl);
                }
                return true;
            }
        } catch {
        }

        return false;
    };

    const cleanupPendingCreation = async (): Promise<boolean> => {
        const [eventCleanedUp, entryCleanedUp] = await Promise.all([
            cleanupRecord('eventUid', PENDING_EVENT_STORAGE_KEY, PENDING_EVENT_QUERY_PARAM),
            cleanupRecord('entryUid', PENDING_ENTRY_STORAGE_KEY, PENDING_ENTRY_QUERY_PARAM),
        ]);

        return eventCleanedUp || entryCleanedUp;
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
        cleanupPendingCreation,
        cancelSelection,
        setClearCalendarSelection: (clearSelection: () => void): void => {
            clearCalendarSelection = clearSelection;
        },
    };
}

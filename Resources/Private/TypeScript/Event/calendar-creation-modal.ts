import Modal from '@typo3/backend/modal.js';
import {html} from 'lit';
import type {
    CalendarModalLabels,
    CalendarOption,
    CalendarRecordTypeOption,
} from '../Configuration/calendar-runtime-config';
import {
    initEventSearchField,
    renderEventSearchField,
    type EventSearchField,
    type EventSearchLabels,
} from './calendar-event-search-field';

export type CalendarCreationType = 'event' | 'event-appointment';

export type CalendarCreationValues = {
    type: CalendarCreationType;
    start: Date;
    end: Date;
    allDay: boolean;
    calendarUid: number | null;
    recordType: string | null;
    eventUid: number | null;
};

const formatDateTimeLocal = (date: Date): string => {
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
        + `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const getInitialEnd = (date: Date, allDay: boolean): Date => {
    if (!allDay) {
        return date;
    }

    const end = new Date(date);
    end.setDate(end.getDate() - 1);
    end.setHours(23, 59, 0, 0);
    return end;
};

export type CalendarCreationModalOptions = {
    labels: CalendarModalLabels;
    types: CalendarCreationType[];
    initialType: CalendarCreationType;
    calendars: CalendarOption[];
    initialCalendarUid: number | null;
    initialStart: Date;
    initialEnd: Date;
    initialAllDay: boolean;
    defaultRecordType: string | null;
    eventSearchUrl: string;
    locale: string;
};

export function chooseCalendarCreationType({
    labels,
    types,
    initialType,
    calendars,
    initialCalendarUid,
    initialStart,
    initialEnd,
    initialAllDay,
    defaultRecordType,
    eventSearchUrl,
    locale,
}: CalendarCreationModalOptions): Promise<CalendarCreationValues | null> {
    return new Promise((resolve) => {
        // With a single calendar there is nothing to choose, so the select is not rendered.
        const selectedCalendarUid = calendars.length === 1 ? calendars[0].uid : initialCalendarUid;
        // Record types come from the TSconfig of each calendar's storage page.
        const getRecordTypes = (calendarUid: number | null): CalendarRecordTypeOption[] => (
            calendars.find(calendar => calendar.uid === calendarUid)?.recordTypes ?? []
        );
        const renderRecordTypeOptions = (select: HTMLSelectElement, calendarUid: number | null): void => {
            const recordTypes = getRecordTypes(calendarUid);
            const selected = recordTypes.some(option => option.value === select.value)
                ? select.value
                : recordTypes.find(option => option.value === defaultRecordType)?.value ?? recordTypes[0]?.value ?? '';
            select.replaceChildren(...recordTypes.map(option => new Option(option.label, option.value, false, option.value === selected)));
        };
        const renderRecordTypeField = (): unknown => html`
                <div
                    class="form-group xima-calendar-creation-form__record-type"
                    style="grid-area:1/1"
                    data-record-type-field
                >
                    <label class="form-label" for="xima-calendar-creation-record-type">${labels.recordType}</label>
                    <select id="xima-calendar-creation-record-type" class="form-select"></select>
                </div>
            `;
        const eventSearchLabels: EventSearchLabels = {
            label: labels.eventSearch,
            placeholder: labels.eventSearchPlaceholder,
            appointments: labels.eventSearchAppointments,
            noResults: labels.eventSearchNoResults,
            clear: labels.eventSearchClear,
        };
        let eventSearchField: EventSearchField | null = null;
        const modal = Modal.advanced({
            title: labels.title,
            content: html`
                <form class="xima-calendar-creation-form">
                    <div class="form-group" style="margin-bottom:2.5rem">
                        <label class="form-label" for="xima-calendar-creation-type">${labels.title}</label>
                        <select id="xima-calendar-creation-type" class="form-select">
                            ${types.map(type => html`
                                <option value=${type} ?selected=${type === initialType}>
                                    ${type === 'event' ? labels.event : labels.appointment}
                                </option>
                            `)}
                        </select>
                    </div>
                    <!-- The record type belongs to events, the event to appointments: both share one slot. -->
                    <div style="display:grid">
                        ${renderRecordTypeField()}
                        ${eventSearchUrl === '' ? '' : html`<div style="grid-area:1/1" data-event-field>${renderEventSearchField(eventSearchLabels)}</div>`}
                    </div>
                    ${calendars.length > 1 ? html`
                        <div class="form-group">
                            <label class="form-label" for="xima-calendar-creation-calendar">${labels.calendar}</label>
                            <select id="xima-calendar-creation-calendar" class="form-select" required>
                                <option value="">${labels.selectCalendar}</option>
                                ${calendars.map(calendar => html`
                                    <option value=${calendar.uid} ?selected=${calendar.uid === selectedCalendarUid}>${calendar.title}</option>
                                `)}
                            </select>
                        </div>
                    ` : ''}
                    <hr class="xima-calendar-creation-form__separator" style="margin:0 0 1rem">
                    <div class="xima-calendar-creation-form__fields" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1rem;align-items:end">
                        <div class="form-group">
                            <label class="form-label" for="xima-calendar-creation-start">${labels.start}</label>
                            <input id="xima-calendar-creation-start" class="form-control" type="datetime-local" value=${formatDateTimeLocal(initialStart)}>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="xima-calendar-creation-end">${labels.end}</label>
                            <input id="xima-calendar-creation-end" class="form-control" type="datetime-local" value=${formatDateTimeLocal(getInitialEnd(initialEnd, initialAllDay))}>
                        </div>
                    </div>
                    <div class="form-check xima-calendar-creation-form__all-day">
                        <input id="xima-calendar-creation-all-day" class="form-check-input" type="checkbox" ?checked=${initialAllDay}>
                        <label class="form-check-label" for="xima-calendar-creation-all-day">${labels.allDay}</label>
                    </div>
                </form>
            `,
            size: 'default',
            additionalCssClasses: ['xima-calendar-creation-modal'],
            buttons: [
                {
                    text: labels.create,
                    btnClass: 'btn-primary',
                    name: 'create',
                },
            ],
        });

        modal.addEventListener('typo3-modal-shown', () => {
            const modalContent = modal.querySelector<HTMLElement>('.modal-content');
            if (modalContent) {
                modalContent.style.setProperty('height', 'auto', 'important');
                modalContent.style.setProperty('max-height', 'none', 'important');
            }
            const form = modal.querySelector<HTMLFormElement>('.xima-calendar-creation-form');
            if (!form) {
                return;
            }
            const allDayInput = form.querySelector<HTMLInputElement>('#xima-calendar-creation-all-day');
            const startInput = form.querySelector<HTMLInputElement>('#xima-calendar-creation-start');
            const endInput = form.querySelector<HTMLInputElement>('#xima-calendar-creation-end');
            const typeInput = form.querySelector<HTMLSelectElement>('#xima-calendar-creation-type');
            const recordTypeField = form.querySelector<HTMLElement>('[data-record-type-field]');
            const eventField = form.querySelector<HTMLElement>('[data-event-field]');
            eventSearchField = eventSearchUrl === ''
                ? null
                : initEventSearchField(form, {url: eventSearchUrl, locale, labels: eventSearchLabels});
            const recordTypeInput = form.querySelector<HTMLSelectElement>('#xima-calendar-creation-record-type');
            const calendarInput = form.querySelector<HTMLSelectElement>('#xima-calendar-creation-calendar');
            const currentCalendarUid = (): number | null => (calendarInput ? Number(calendarInput.value) || null : selectedCalendarUid);
            const updateRecordTypeField = (): void => {
                // Events need table rights the user may only have on some calendar pages.
                const isEvent = typeInput?.value === 'event';
                calendarInput?.querySelectorAll<HTMLOptionElement>('option[value]:not([value=""])').forEach((option: HTMLOptionElement) => {
                    option.disabled = isEvent && !calendars.find(calendar => calendar.uid === Number(option.value))?.canCreateEvent;
                });
                if (calendarInput?.selectedOptions[0]?.disabled) {
                    calendarInput.value = '';
                }
                if (recordTypeInput) {
                    renderRecordTypeOptions(recordTypeInput, currentCalendarUid());
                }
                // A single record type needs no choice.
                const visible = isEvent && (recordTypeInput?.options.length ?? 0) > 1;
                if (recordTypeField) {
                    recordTypeField.style.visibility = visible ? 'visible' : 'hidden';
                    recordTypeField.setAttribute('aria-hidden', String(!visible));
                }
                if (recordTypeInput) {
                    recordTypeInput.tabIndex = visible ? 0 : -1;
                }
                if (eventField) {
                    eventField.style.visibility = isEvent ? 'hidden' : 'visible';
                    eventField.setAttribute('aria-hidden', String(isEvent));
                }
            };
            const updateDateFields = (): void => {
                const disabled = allDayInput?.checked ?? false;
                if (startInput) {
                    startInput.readOnly = disabled;
                    startInput.classList.toggle('xima-calendar-creation-form__date-disabled', disabled);
                    startInput.setAttribute('aria-disabled', String(disabled));
                }
                if (endInput) {
                    endInput.readOnly = disabled;
                    endInput.classList.toggle('xima-calendar-creation-form__date-disabled', disabled);
                    endInput.setAttribute('aria-disabled', String(disabled));
                }
            };
            const enableDateFields = (): void => {
                if (allDayInput?.checked) {
                    allDayInput.checked = false;
                    updateDateFields();
                }
            };
            startInput?.addEventListener('click', enableDateFields);
            endInput?.addEventListener('click', enableDateFields);
            allDayInput?.addEventListener('change', updateDateFields);
            typeInput?.addEventListener('change', updateRecordTypeField);
            calendarInput?.addEventListener('change', updateRecordTypeField);
            updateDateFields();
            updateRecordTypeField();
            if (eventSearchField && typeInput?.value === 'event-appointment') {
                eventSearchField.focus();
            } else {
                typeInput?.focus();
            }
        });

        modal.addEventListener('button.clicked', (event: Event) => {
            const name = (event.target as HTMLElement).getAttribute('name');
            if (name !== 'create') {
                return;
            }

            const type = modal.querySelector<HTMLSelectElement>('#xima-calendar-creation-type')?.value;
            const recordType = type === 'event'
                ? modal.querySelector<HTMLSelectElement>('#xima-calendar-creation-record-type')?.value || null
                : null;
            const calendarSelect = modal.querySelector<HTMLSelectElement>('#xima-calendar-creation-calendar');
            const startValue = modal.querySelector<HTMLInputElement>('#xima-calendar-creation-start')?.value;
            const endValue = modal.querySelector<HTMLInputElement>('#xima-calendar-creation-end')?.value;
            const allDay = modal.querySelector<HTMLInputElement>('#xima-calendar-creation-all-day')?.checked ?? false;
            const start = startValue ? new Date(startValue) : null;
            const end = endValue ? new Date(endValue) : null;
            const calendarUid = calendarSelect ? Number(calendarSelect.value) || null : selectedCalendarUid;
            if ((type !== 'event' && type !== 'event-appointment') || !start || !end || Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
                return;
            }
            if (calendarUid === null) {
                calendarSelect?.reportValidity();
                return;
            }

            const eventUid = type === 'event-appointment' ? eventSearchField?.getEventUid() ?? null : null;

            resolve({type, start, end, allDay, calendarUid, recordType, eventUid});
            modal.hideModal();
        });
        modal.addEventListener('typo3-modal-hidden', () => {
            resolve(null);
        });
    });
}

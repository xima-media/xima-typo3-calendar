import Modal from '@typo3/backend/modal.js';
import {html} from 'lit';
import type {
    CalendarModalLabels,
    CalendarOption,
    CalendarRecordTypeOption,
} from '../Configuration/calendar-runtime-config';

export type CalendarCreationType = 'event' | 'event-appointment';

export type CalendarCreationValues = {
    type: CalendarCreationType;
    start: Date;
    end: Date;
    allDay: boolean;
    calendarUid: number | null;
    recordType: string | null;
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

export function chooseCalendarCreationType(
    labels: CalendarModalLabels,
    calendars: CalendarOption[],
    initialStart: Date,
    initialEnd: Date,
    initialAllDay: boolean,
    recordTypes: CalendarRecordTypeOption[],
    defaultRecordType: string | null,
): Promise<CalendarCreationValues | null> {
    return new Promise((resolve) => {
        const initialRecordType = defaultRecordType && recordTypes.some(option => option.value === defaultRecordType)
            ? defaultRecordType
            : recordTypes[0]?.value ?? '';
        const renderRecordTypeField = (): unknown => (recordTypes.length > 1 ? html`
                <div
                    class="form-group xima-calendar-creation-form__record-type"
                    data-record-type-field
                >
                    <label class="form-label" for="xima-calendar-creation-record-type">${labels.recordType}</label>
                    <select id="xima-calendar-creation-record-type" class="form-select">
                        ${recordTypes.map(option => html`
                            <option value=${option.value} ?selected=${option.value === initialRecordType}>
                                ${option.label}
                            </option>
                        `)}
                    </select>
                </div>
            ` : '');
        const modal = Modal.advanced({
            title: labels.title,
            content: html`
                <form class="xima-calendar-creation-form">
                    <div class="form-group" style="margin-bottom:2.5rem">
                        <label class="form-label" for="xima-calendar-creation-type">${labels.title}</label>
                        <select id="xima-calendar-creation-type" class="form-select">
                            <option value="event">${labels.event}</option>
                            <option value="event-appointment">${labels.appointment}</option>
                        </select>
                    </div>
                    ${renderRecordTypeField()}
                    ${calendars.length > 0 ? html`
                        <div class="form-group">
                            <label class="form-label" for="xima-calendar-creation-calendar">${labels.calendar}</label>
                            <select id="xima-calendar-creation-calendar" class="form-select" ?disabled=${calendars.length === 1}>
                                ${calendars.length > 1 ? html`<option value="">${labels.selectCalendar}</option>` : ''}
                                ${calendars.map(calendar => html`
                                    <option value=${calendar.uid} ?selected=${calendars.length === 1}>${calendar.title}</option>
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
            const recordTypeInput = form.querySelector<HTMLSelectElement>('#xima-calendar-creation-record-type');
            const updateRecordTypeField = (): void => {
                const visible = typeInput?.value === 'event';
                if (recordTypeField) {
                    recordTypeField.style.visibility = visible ? 'visible' : 'hidden';
                    recordTypeField.setAttribute('aria-hidden', String(!visible));
                }
                if (recordTypeInput) {
                    recordTypeInput.tabIndex = visible ? 0 : -1;
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
            updateDateFields();
            updateRecordTypeField();
            (form.elements.namedItem('xima-calendar-creation-type') as HTMLSelectElement | null)?.focus();
        });

        modal.addEventListener('button.clicked', (event: Event) => {
            const name = (event.target as HTMLElement).getAttribute('name');
            if (name !== 'create') {
                return;
            }

            const type = modal.querySelector<HTMLSelectElement>('#xima-calendar-creation-type')?.value;
            const recordType = type === 'event'
                ? modal.querySelector<HTMLSelectElement>('#xima-calendar-creation-record-type')?.value ?? null
                : null;
            const calendarValue = modal.querySelector<HTMLSelectElement>('#xima-calendar-creation-calendar')?.value;
            const startValue = modal.querySelector<HTMLInputElement>('#xima-calendar-creation-start')?.value;
            const endValue = modal.querySelector<HTMLInputElement>('#xima-calendar-creation-end')?.value;
            const allDay = modal.querySelector<HTMLInputElement>('#xima-calendar-creation-all-day')?.checked ?? false;
            const start = startValue ? new Date(startValue) : null;
            const end = endValue ? new Date(endValue) : null;
            const calendarUid = calendarValue ? Number(calendarValue) : null;
            if ((type !== 'event' && type !== 'event-appointment') || !start || !end || Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
                return;
            }
            if (calendars.length > 0 && !calendarUid) {
                return;
            }

            resolve({type, start, end, allDay, calendarUid, recordType});
            modal.hideModal();
        });
        modal.addEventListener('typo3-modal-hidden', () => {
            resolve(null);
        });
    });
}

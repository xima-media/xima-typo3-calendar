import DocumentService from '@typo3/core/document-service.js';
import Modal from '@typo3/backend/modal.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import { html } from 'lit';

/**
 * Scoped styles for the card layout. Injected as part of the modal body so the
 * rules live in the same (top) document the modal renders into.
 */
const styles = html`
  <style>
    .event-status-selection {
      display: flex;
      flex-direction: column;
      gap: .375rem;
    }
    .event-status-card {
      display: flex;
      align-items: flex-start;
      gap: .5rem;
      width: 100%;
      padding: .5rem .75rem;
      text-align: left;
      border: 1px solid var(--typo3-component-border-color, #d1d5db);
      border-radius: var(--typo3-component-border-radius, 6px);
      background: var(--typo3-component-bg, #fff);
      color: inherit;
      cursor: pointer;
      transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .event-status-card:hover {
      border-color: var(--typo3-state-primary-border-color, #0078e6);
      background: var(--typo3-component-hover-bg, rgba(0, 120, 230, .06));
    }
    .event-status-card:focus-visible {
      outline: none;
      border-color: var(--typo3-state-primary-border-color, #0078e6);
      box-shadow: 0 0 0 .2rem rgba(0, 120, 230, .25);
    }
    .event-status-card.is-active {
      border-color: var(--typo3-state-primary-border-color, #0078e6);
      box-shadow: 0 0 0 .2rem rgba(0, 120, 230, .25);
      background: var(--typo3-component-hover-bg, rgba(0, 120, 230, .06));
    }
    .event-status-card__icon {
      flex: 0 0 auto;
      display: flex;
      align-items: center;
    }
    .event-status-card__body {
      display: flex;
      flex-direction: column;
      gap: .15rem;
    }
    .event-status-card__label {
      font-weight: 600;
      font-size: .875rem;
      line-height: 1.2;
    }
    .event-status-card__description {
      font-size: .75rem;
      color: var(--typo3-text-color-variant, #6b7280);
      line-height: 1.3;
    }
  </style>`;

/**
 * Build the card list rendered inside the modal body.
 *
 * Returns a lit TemplateResult (not a DOM node): the modal renders in the top
 * document, so the custom elements must be created there — handing over a node
 * built in the current document breaks `<typo3-backend-icon>` style adoption.
 *
 * @param {Array<{value: (string|number), label: string, description?: string}>} items
 * @param {(string|number)} currentValue
 * @param {(event: MouseEvent) => void} onCardClick
 * @returns {import('lit').TemplateResult}
 */
function buildContent(items, currentValue, onCardClick) {
  return html`
    ${styles}
    <div class="event-status-selection">
      ${items.map((item) => html`
        <button
          type="button"
          class="event-status-card ${String(item.value) === String(currentValue) ? 'is-active' : ''}"
          data-value="${item.value}"
          @click=${onCardClick}>
          <span class="event-status-card__icon">
            <typo3-backend-icon identifier="status-${item.value}" size="small"></typo3-backend-icon>
          </span>
          <span class="event-status-card__body">
            <span class="event-status-card__label">${item.label}</span>
            ${item.description
              ? html`<span class="event-status-card__description">${item.description}</span>`
              : ''}
          </span>
        </button>`)}
    </div>`;
}

/**
 * Open the event status selection modal.
 *
 * Reusable from any trigger (record list status badge, edit form button, …).
 * The caller decides what happens with the chosen value via `onSave`.
 *
 * @param {{
 *   items: Array<{value: (string|number), label: string, description?: string}>,
 *   currentValue: (string|number),
 *   title?: string,
 *   onSave: (value: string) => (Promise<unknown>|unknown)
 * }} options
 * @returns {import('@typo3/backend/modal.js').ModalElement}
 */
export function openEventStatusModal(options) {
  const { items, currentValue, onSave } = options;
  const title = options.title ?? (TYPO3.lang?.['eventStatus.modal.title'] || 'Status');

  let selectedValue = String(currentValue);

  const onCardClick = (e) => {
    const card = e.currentTarget;
    selectedValue = card.dataset.value;
    card.parentElement
      .querySelectorAll('.event-status-card')
      .forEach((c) => c.classList.toggle('is-active', c === card));
  };

  return Modal.advanced({
    title,
    content: buildContent(items, currentValue, onCardClick),
    severity: SeverityEnum.notice,
    size: Modal.sizes.small,
    type: Modal.types.default,
    buttons: [
      {
        text: TYPO3.lang?.['button.cancel'] || 'Cancel',
        btnClass: 'btn-default',
        name: 'cancel',
        trigger: (e, modal) => modal.hideModal(),
      },
      {
        text: TYPO3.lang?.['button.ok'] || 'OK',
        btnClass: 'btn-primary',
        name: 'save',
        active: true,
        trigger: (e, modal) => {
          Promise.resolve(typeof onSave === 'function' ? onSave(selectedValue) : undefined)
            .then(() => modal.hideModal());
        },
      },
    ],
  });
}

/**
 * Wires the record list status badges (first column) to the status modal.
 */
class RecordlistEventStatusModal {
  constructor() {
    DocumentService.ready().then(() => this.init());
  }

  init() {
    document.querySelectorAll('span[data-column-name="status"] a.event-status-badge').forEach((badge) => {
      badge.addEventListener('click', this.onBadgeClick.bind(this));
    });
  }

  onBadgeClick(e) {
    e.preventDefault();
    const badge = e.currentTarget;

    let items = [];
    try {
      // `filter.items` is serialised as an object keyed by value, so normalise to an array.
      const parsed = JSON.parse(badge.dataset.statusItems || '[]');
      items = Array.isArray(parsed) ? parsed : Object.values(parsed);
    } catch {
      items = [];
    }
    if (items.length === 0) {
      return;
    }

    const row = badge.closest('tr[data-uid]');

    openEventStatusModal({
      items,
      currentValue: badge.dataset.value,
      onSave: (value) => this.persist(row, badge.dataset.fieldName, value),
    });
  }

  persist(row, column, value) {
    if (!row) {
      return Promise.resolve();
    }

    const payload = new FormData();
    payload.append('table', row.dataset.table);
    payload.append('uid', row.dataset.uid);
    payload.append('column', column);
    payload.append('newValue', value);

    return new AjaxRequest(TYPO3.settings.ajaxUrls.xima_recordlist_inline_edit)
      .withQueryArguments({ workspaceId: row.getAttribute('data-t3ver_wsid') })
      .post('', { body: payload })
      .then(() => {
        window.location.reload();
      })
      .catch(() => {
        Notification.error(
          TYPO3.lang?.['inlineEdit.error.title'] || 'Error',
          TYPO3.lang?.['inlineEdit.error.message'] || 'The status could not be saved.',
        );
      });
  }
}

new RecordlistEventStatusModal();

import {html, LitElement} from 'lit';
import {getCalendarLanguageLabel} from '../../Services/calendar-language';

export class CalendarFilterToolbar extends LitElement {
  public createRenderRoot(): HTMLElement {
    return this;
  }

  public render() {
    const searchLabel = getCalendarLanguageLabel('filter.search');

    return html`
      <div class="tree-toolbar">
        <div class="tree-toolbar__menu">
          <div class="tree-toolbar__search" hidden>
            <label
              for="xima-calendar-filter-search"
              class="visually-hidden"
            >
              ${searchLabel}
            </label>

            <input
              id="xima-calendar-filter-search"
              type="search"
              class="form-control form-control-sm search-input"
              placeholder=${searchLabel}
            />
          </div>
        </div>
      </div>
    `;
  }
}

customElements.define(
  'xima-calendar-filter-toolbar',
  CalendarFilterToolbar
);

import {html, LitElement} from 'lit';

type Typo3TopWindow = Window & {
  TYPO3?: {
    lang?: Record<string, string>;
    settings?: {
      ximaCalendar?: {
        locale?: string;
      };
    };
  };
};

export class CalendarFilterToolbar extends LitElement {
  public createRenderRoot(): HTMLElement {
    return this;
  }

  public render() {
    const typo3Top = window.top as unknown as Typo3TopWindow;
    const locale = typo3Top.TYPO3?.settings?.ximaCalendar?.locale
      ?? document.documentElement.lang
      ?? navigator.language;
    const searchLabel = locale?.startsWith('de')
      ? 'Suchbegriff eingeben'
      : typo3Top.TYPO3?.lang?.['filter.search'] ?? 'Enter search term';

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

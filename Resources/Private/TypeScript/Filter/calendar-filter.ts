import {html, LitElement, nothing} from 'lit';
import {type ColorOverrides, getColor} from '../Utils/color-utils';

import './Components/calendar-filter-toolbar';
import './Components/calendar-filter-tree';
import './Components/calendar-filter-checkbox-list';
import './Components/calendar-filter-mini-calendar';
import type {TreeStateChangePayload} from './Components/calendar-filter-tree';
import type {CheckboxListChangePayload} from './Components/calendar-filter-checkbox-list';
import {CalendarFilterStatePersistence} from './Services/calendar-filter-state-persistence';
import {isCalendarFilterResponse, requestCalendarFilterConfiguration,} from './Services/calendar-filter-configuration';
import {
  createFilterSelection,
  updateCheckboxListState,
  updateTreeState,
} from './Services/calendar-filter-state-manager';

import type {
  CalendarFilter,
  CalendarFilterActionLabels,
  CalendarFilterConfiguration,
  CalendarFilterSelection,
  CalendarFilterState,
  TreeCalendarFilter,
} from './calendar-filter-state';

type Typo3TopWindow = Window & {
  ximaCalendarFilterState?: CalendarFilterSelection;
  ximaCalendarFilterConfiguration?: CalendarFilterConfiguration;
  TYPO3?: {
    lang?: Record<string, string>;
    settings?: {
      ajaxUrls?: {
        xima_calendar_filter_options?: string;
        xima_calendar_filter_state?: string;
      };
      ximaCalendar?: {
        filterOptionsUrl?: string;
        filterStateUrl?: string;
        filterColors?: ColorOverrides;
        locale?: string;
      };
    };
  };
};

export const navigationComponentName =
  'xima-calendar-filter-element';

export class CalendarFilterElement extends LitElement {
  private filterConfiguration: CalendarFilterConfiguration | null = null;

  private filterColors: ColorOverrides = {};

  private filterState: CalendarFilterState | null = null;

  private filterLabels: CalendarFilterActionLabels | null = null;

  private filterLoadError = false;

  private filterStatePersistence: CalendarFilterStatePersistence | null = null;


  public connectedCallback(): void {
    super.connectedCallback();
    const typo3Top = window.top as unknown as Typo3TopWindow;
    const stateUrl = typo3Top.TYPO3?.settings?.ajaxUrls?.xima_calendar_filter_state
      ?? typo3Top.TYPO3?.settings?.ximaCalendar?.filterStateUrl;
    if (stateUrl) {
      this.filterStatePersistence = new CalendarFilterStatePersistence(stateUrl);
    }

    void this.loadFilterConfiguration();
  }

  public createRenderRoot(): HTMLElement {
    return this;
  }

  public render() {
    return html`
      <link rel="stylesheet" href="${new URL('../../Css/calendar-filter.css', import.meta.url).href}">

      <div class="xima-calendar-filter">
        <xima-calendar-filter-toolbar></xima-calendar-filter-toolbar>

        <div class="xima-calendar-filter__body">
          ${this.filterConfiguration === null
            ? nothing
            : Object.values(this.filterConfiguration).map((filter) => this.renderFilter(filter))}
        </div>

        <section
          class="xima-calendar-filter__mini-calendar"
          aria-label="Mini calendar"
        >
          <xima-calendar-filter-mini-calendar></xima-calendar-filter-mini-calendar>
        </section>
      </div>
    `;
  }

  private renderTreeFilter(tree: TreeCalendarFilter) {
    if (tree.items.length === 0) {
      return nothing;
    }

    const treeState = this.filterState?.trees[tree.identifier] ?? {
      activeItems: [],
      expandedItems: {},
    };
    const items = tree.items.map((item) => ({
      ...item,
      color: tree.supportsColorCoding
        ? getColor(tree.identifier, item.value, this.filterColors)
        : undefined,
    }));

    return html`
      <section class="xima-calendar-filter__section">
        <details
          .open=${this.filterState?.sections[tree.identifier] ?? true}
          @toggle=${(event: Event) => this.handleSectionToggle(tree.identifier, event)}
        >
          <summary>
            <span class="xima-calendar-filter__section-toggle" aria-hidden="true"></span>
            <h3>${tree.title}</h3>
          </summary>

          <xima-calendar-filter-tree
            .identifier=${tree.identifier}
            .treeTitle=${tree.title}
            .actionLabels=${this.filterLabels}
            .items=${items}
            .activeItems=${treeState.activeItems}
            .expandedItems=${treeState.expandedItems}
            @tree-state-change=${this.handleTreeStateChange}
          ></xima-calendar-filter-tree>
        </details>
      </section>
    `;
  }

  private renderFilter(filter: CalendarFilter) {
    return filter.type === 'tree'
      ? this.renderTreeFilter(filter)
      : this.renderCheckboxListFilter(filter);
  }

  private handleTreeStateChange(
    event: CustomEvent<TreeStateChangePayload>,
  ): void {
    if (this.filterState === null) {
      return;
    }

    this.updateFilterState((state) =>
      updateTreeState(state, event.detail),
    );
  }

  private renderCheckboxListFilter(filter: Extract<CalendarFilter, { type: 'checkbox-list' }>) {
    if (filter.items.length === 0) {
      return nothing;
    }

    const activeItems = this.filterState?.checkboxLists[filter.identifier] ?? [];

    return html`
      <section class="xima-calendar-filter__section">
        <details
          .open=${this.filterState?.sections[filter.identifier] ?? true}
          @toggle=${(event: Event) => this.handleSectionToggle(filter.identifier, event)}
        >
          <summary>
            <span class="xima-calendar-filter__section-toggle" aria-hidden="true"></span>
            <h3>${filter.title}</h3>
          </summary>
          <xima-calendar-filter-checkbox-list
            .identifier=${filter.identifier}
            .items=${filter.items}
            .activeItems=${activeItems}
            @checkbox-list-change=${this.handleCheckboxListChange}
          ></xima-calendar-filter-checkbox-list>
        </details>
      </section>
    `;
  }

  private async loadFilterConfiguration(): Promise<void> {
    const typo3Top = window.top as unknown as Typo3TopWindow;
    const url = typo3Top.TYPO3?.settings?.ajaxUrls?.xima_calendar_filter_options
      ?? typo3Top.TYPO3?.settings?.ximaCalendar?.filterOptionsUrl;
    if (!url) {
      console.error('Calendar filter URL is not available');
      this.filterLoadError = true;
      this.requestUpdate();
      return;
    }

    try {
      const data: unknown = await requestCalendarFilterConfiguration(url);
      if (!isCalendarFilterResponse(data)) {
        throw new Error('Filter configuration could not be loaded');
      }

      this.filterConfiguration = data.filters;
      this.filterColors = data.filterColors;
      this.filterState = data.state;
      this.filterLabels = data.labels;
      this.publishFilterConfiguration();
      this.publishFilterState();
    } catch (error) {
      console.error('Calendar filter configuration could not be loaded', error);
      this.filterLoadError = true;
    }

    this.requestUpdate();
  }

  private handleCheckboxListChange(
    event: CustomEvent<CheckboxListChangePayload>,
  ): void {
    if (this.filterState === null) {
      return;
    }
    this.updateFilterState((state) =>
      updateCheckboxListState(state, event.detail));
  }

  private handleSectionToggle(identifier: string, event: Event): void {
    const details = event.currentTarget;
    if (!(details instanceof HTMLDetailsElement) || this.filterState === null) {
      return;
    }

    this.updateFilterState((state) => ({
      ...state,
      sections: {
        ...state.sections,
        [identifier]: details.open,
      },
    }));
  }

  private publishFilterConfiguration(): void {
    if (this.filterConfiguration === null) {
      return;
    }

    const typo3Top = window.top as unknown as Typo3TopWindow;

    typo3Top.ximaCalendarFilterConfiguration = this.filterConfiguration;

    typo3Top.dispatchEvent(new CustomEvent(
      'xima-calendar-filter-configuration-loaded', {
        bubbles: true,
        composed: true,
        detail: this.filterConfiguration,
      }));
  }
  private publishFilterState(): void {
    if (this.filterState === null) {
      return;
    }

    const selection = createFilterSelection(this.filterState);
    const typo3Top = window.top as unknown as Typo3TopWindow;

    typo3Top.ximaCalendarFilterState = selection;

    typo3Top.dispatchEvent(new CustomEvent('xima-calendar-filter-changed', {
      bubbles: true,
      composed: true,
      detail: selection,
    }));
  }

  private dispatchFilterChange(): void {
    this.publishFilterState();
    this.requestUpdate();
    if (this.filterState !== null) {
      this.filterStatePersistence?.save(this.filterState);
    }
  }

  private updateFilterState(
    update: (state: CalendarFilterState) => CalendarFilterState,
  ): void {
    if (this.filterState === null) {
      return;
    }

    this.filterState = update(this.filterState);
    this.dispatchFilterChange();
  }

}

customElements.define(
  navigationComponentName,
  CalendarFilterElement
);

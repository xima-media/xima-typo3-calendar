import {html, LitElement, nothing} from 'lit';
import {
  updateDescendantSelection,
  updateTreeExpansion,
  updateTreeSelection,
  type TreeValue,
} from './tree-utils';
import type {CalendarFilterActionLabels} from '../calendar-filter-state';

export type {TreeValue} from './tree-utils';

export type TreeStateChangePayload = {
  identifier: string;
  activeItems: TreeValue[];
  expandedItems: Record<TreeValue, boolean>;
};

export type TreeCalendarFilterItem = {
  label: string;
  value: TreeValue;
  selected: boolean;
  parentUid: TreeValue | null;
  color?: string;
};

export class CalendarFilterTree extends LitElement {
  static properties = {
    identifier: {attribute: false},
    treeTitle: {attribute: false},
    actionLabels: {attribute: false},
    items: {attribute: false},
    activeItems: {attribute: false},
    expandedItems: {attribute: false},
  };

  public identifier = '';
  public treeTitle = '';
  public actionLabels: CalendarFilterActionLabels | null = null;
  public items: TreeCalendarFilterItem[] = [];
  public activeItems: TreeValue[] = [];
  public expandedItems: Record<TreeValue, boolean> = {};
  private openActionValue: TreeValue | null = null;

  public createRenderRoot(): HTMLElement {
    return this;
  }

  public render() {
    const moreActionsLabel = this.getLanguageLabel(
      'filter.moreActions',
      'More actions',
    );
    const selectAllChildrenLabel = this.getLanguageLabel(
      'filter.selectAllChildren',
      'Select all nested ###name###',
    );
    const deselectAllChildrenLabel = this.getLanguageLabel(
      'filter.deselectAllChildren',
      'Deselect all nested ###name###',
    );

    return html`
      <div class="xima-calendar-filter__tree">
        ${this.renderTreeNodes(
          null,
          moreActionsLabel,
          selectAllChildrenLabel,
          deselectAllChildrenLabel,
        )}
      </div>
    `;
  }

  private renderTreeNodes(
    parentUid: TreeValue | null,
    moreActionsLabel: string,
    selectAllChildrenLabel: string,
    deselectAllChildrenLabel: string,
  ): unknown [] {
    const items = this.items.filter(
      (item) => item.parentUid === parentUid,
    );

    return items.map((item) => {
      const hasChildren = this.items.some(
        (child) => child.parentUid === item.value
      );
      const isExpanded =
        this.expandedItems[String(item.value)] ?? false;

      const isSelected = this.activeItems.includes(item.value);

      return html`
        <div class="xima-calendar-filter__tree-item">
          <div class="xima-calendar-filter__tree-node">
            <button
              type="button"
              class=${hasChildren
                ? 'xima-calendar-filter__tree-toggle'
                : 'xima-calendar-filter__tree-toggle xima-calendar-filter__tree-toggle--empty'}
              aria-expanded=${isExpanded}
              aria-hidden=${!hasChildren}
              tabindex=${hasChildren ? '0' : '-1'}
              @click=${() => this.handleExpansionChange(
                item.value,
                !isExpanded,
              )}
            >
              <typo3-backend-icon
                identifier=${isExpanded
                  ? 'actions-chevron-down'
                  : 'actions-chevron-end'}
                size="small"
              ></typo3-backend-icon>
            </button>

            <label class="form-check">
              <input
                class="xima-calendar-filter__tree-checkbox"
                type="checkbox"
                .checked=${isSelected}
                style=${item.color
                  ? `--xima-tree-item-color: ${item.color}`
                  : ''}
                @change=${(event: Event) => this.handleSelectionChange(
                  item.value,
                  (event.target as HTMLInputElement).checked,
                )}
              />

              <span class="form-check-label">
        ${item.label}
      </span>
            </label>

            ${hasChildren
              ? html`
                <div class="xima-calendar-filter__tree-actions">
                  <button
                    type="button"
                    class="xima-calendar-filter__tree-actions-toggle"
                    aria-label=${moreActionsLabel}
                    aria-haspopup="menu"
                    aria-expanded=${this.openActionValue === item.value}
                    title=${moreActionsLabel}
                    @click=${(event: Event) => {
                      event.stopPropagation();
                      this.toggleActionMenu(item.value);
                    }}
                  >
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                  </button>

                  ${this.openActionValue === item.value
                    ? html`
                      <div
                        class="xima-calendar-filter__tree-actions-menu"
                        role="menu"
                      >
                        <button
                          type="button"
                          role="menuitem"
                          @click=${(event: Event) => {
                            event.stopPropagation();
                            this.handleDescendantSelection(item.value, true);
                          }}
                        >${selectAllChildrenLabel}</button>
                        <button
                          type="button"
                          role="menuitem"
                          @click=${(event: Event) => {
                            event.stopPropagation();
                            this.handleDescendantSelection(item.value, false);
                          }}
                        >${deselectAllChildrenLabel}</button>
                      </div>
                    `
                    : nothing}
                </div>
              `
              : nothing}
          </div>

          ${hasChildren && isExpanded
            ? html`
              <div class="xima-calendar-filter__tree-children">
                ${this.renderTreeNodes(
                  item.value,
                  moreActionsLabel,
                  selectAllChildrenLabel,
                  deselectAllChildrenLabel,
                )}
              </div>
            `
            : nothing}
        </div>
      `;
    })
  }

  private getLanguageLabel(
    key: string,
    fallback: string,
  ): string {
    const actionKey = key.replace('filter.', '') as keyof CalendarFilterActionLabels;
    const label = this.actionLabels?.[actionKey] ?? fallback;

    return label.replace('###name###', this.treeTitle);
  }

  private handleSelectionChange(
    value: TreeValue,
    selected: boolean
  ): void {
    this.activeItems = updateTreeSelection(
      this.activeItems,
      value,
      selected,
    );

    this.dispatchEvent(
      new CustomEvent<TreeStateChangePayload>('tree-state-change', {
        detail: {
          identifier: this.identifier,
          activeItems: this.activeItems,
          expandedItems: this.expandedItems,
        },
        bubbles: true,
      })
    );
  }

  private toggleActionMenu(value: TreeValue): void {
    this.openActionValue = this.openActionValue === value ? null : value;
    this.requestUpdate();
  }

  private handleDescendantSelection(
    value: TreeValue,
    selected: boolean,
  ): void {
    this.activeItems = updateDescendantSelection(
      this.activeItems,
      this.items,
      value,
      selected,
    );
    this.openActionValue = null;

    this.dispatchEvent(
      new CustomEvent<TreeStateChangePayload>('tree-state-change', {
        detail: {
          identifier: this.identifier,
          activeItems: this.activeItems,
          expandedItems: this.expandedItems,
        },
        bubbles: true,
      })
    );
  }

  private handleExpansionChange(
    value: TreeValue,
    expanded: boolean
  ): void {
    this.expandedItems = updateTreeExpansion(
      this.expandedItems,
      value,
      expanded,
    );

    this.dispatchEvent(
      new CustomEvent<TreeStateChangePayload>('tree-state-change', {
        detail: {
          identifier: this.identifier,
          activeItems: this.activeItems,
          expandedItems: this.expandedItems,
        },
        bubbles: true,
      })
    );
  }
}

customElements.define(
  'xima-calendar-filter-tree',
  CalendarFilterTree,
);

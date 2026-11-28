import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import {html, nothing, render, type TemplateResult} from 'lit';

export type EventSearchLabels = {
    label: string;
    placeholder: string;
    /** Contains %s for the date range. */
    appointments: string;
    noResults: string;
    clear: string;
};

type EventSearchResult = {
    uid: number;
    title: string;
    firstStart: number | null;
    lastEnd: number | null;
    details: string[];
};

export type EventSearchField = {
    getEventUid(): number | null;
    focus(): void;
};

const FIELD_ID = 'xima-calendar-creation-event';
const LIST_ID = `${FIELD_ID}-results`;
// Mirrors EventSearchService::MIN_SEARCH_LENGTH.
const MIN_SEARCH_LENGTH = 2;
const DEBOUNCE_MS = 250;

const isTimestamp = (value: unknown): value is number | null => value === null || Number.isInteger(value);

const isEventSearchResult = (value: unknown): value is EventSearchResult => {
    if (!value || typeof value !== 'object') {
        return false;
    }
    const result = value as Record<string, unknown>;
    return Number.isInteger(result.uid)
        && typeof result.title === 'string'
        && isTimestamp(result.firstStart)
        && isTimestamp(result.lastEnd)
        && Array.isArray(result.details)
        && result.details.every(detail => typeof detail === 'string');
};

const parseResults = (data: unknown): EventSearchResult[] => {
    const results = (data as {results?: unknown} | null)?.results;
    return Array.isArray(results) ? results.filter(isEventSearchResult) : [];
};

const createDateFormat = (locale: string): Intl.DateTimeFormat => {
    const options: Intl.DateTimeFormatOptions = {day: '2-digit', month: '2-digit', year: 'numeric'};
    try {
        return new Intl.DateTimeFormat(locale, options);
    } catch {
        return new Intl.DateTimeFormat(undefined, options);
    }
};

export function renderEventSearchField(labels: EventSearchLabels): TemplateResult {
    return html`
        <div class="form-group xima-calendar-creation-form__event">
            <label class="form-label" for=${FIELD_ID}>${labels.label}</label>
            <div data-event-search>
                <input
                    id=${FIELD_ID}
                    class="form-control"
                    type="search"
                    role="combobox"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls=${LIST_ID}
                    placeholder=${labels.placeholder}
                >
                <!-- A popover lies in the top layer: the results neither resize nor scroll the modal. -->
                <div
                    id=${LIST_ID}
                    class="list-group shadow"
                    role="listbox"
                    aria-label=${labels.label}
                    popover="manual"
                    style="position:fixed;inset:auto;margin:0;padding:0;border:0;overflow-y:auto"
                ></div>
                <p class="visually-hidden" data-event-search-status aria-live="polite"></p>
            </div>
            <!-- hidden sits on a wrapper: d-flex would override it. -->
            <div data-event-selection hidden>
                <div class="d-flex align-items-start gap-2 p-2 border rounded">
                    <div class="flex-grow-1">
                        <strong data-event-selection-title></strong>
                        <div class="form-text mt-0" data-event-selection-meta></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-default" title=${labels.clear} aria-label=${labels.clear} data-event-selection-clear>
                        <typo3-backend-icon identifier="actions-close" size="small"></typo3-backend-icon>
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Combobox following the ARIA authoring practices: focus stays in the input, the active
// option is announced through aria-activedescendant.
export function initEventSearchField(
    root: HTMLElement,
    {url, locale, labels}: {url: string; locale: string; labels: EventSearchLabels},
): EventSearchField | null {
    const input = root.querySelector<HTMLInputElement>(`#${FIELD_ID}`);
    const list = root.querySelector<HTMLElement>(`#${LIST_ID}`);
    const status = root.querySelector<HTMLElement>('[data-event-search-status]');
    const search = root.querySelector<HTMLElement>('[data-event-search]');
    const selection = root.querySelector<HTMLElement>('[data-event-selection]');
    const selectionTitle = root.querySelector<HTMLElement>('[data-event-selection-title]');
    const selectionMeta = root.querySelector<HTMLElement>('[data-event-selection-meta]');
    const clearButton = root.querySelector<HTMLButtonElement>('[data-event-selection-clear]');
    if (!input || !list || !status || !search || !selection || !selectionTitle || !selectionMeta || !clearButton) {
        return null;
    }

    const dateFormat = createDateFormat(locale);
    let results: EventSearchResult[] = [];
    let activeIndex = -1;
    let selected: EventSearchResult | null = null;
    let debounceTimer: number | undefined;
    let pendingRequest: AjaxRequest | null = null;
    let showNoResults = false;

    const describe = (result: EventSearchResult): string => {
        const parts: string[] = [];
        if (result.firstStart !== null) {
            const start = dateFormat.format(result.firstStart * 1000);
            const end = result.lastEnd === null ? start : dateFormat.format(result.lastEnd * 1000);
            parts.push(labels.appointments.replace('%s', start === end ? start : `${start} – ${end}`));
        }
        return [...parts, ...result.details].join(' · ');
    };

    const optionId = (index: number): string => `${LIST_ID}-${index}`;

    const renderOptions = (): void => {
        render(html`${showNoResults ? html`<div class="list-group-item text-body-secondary">${labels.noResults}</div>` : nothing}${results.map((result, index) => {
            const description = describe(result);
            return html`
                <div
                    id=${optionId(index)}
                    class="list-group-item list-group-item-action ${index === activeIndex ? 'active' : ''}"
                    role="option"
                    aria-selected=${index === activeIndex ? 'true' : 'false'}
                    style="cursor:pointer"
                    @mousedown=${(event: MouseEvent) => event.preventDefault()}
                    @click=${() => choose(result)}
                >
                    <div>${result.title}</div>
                    ${description === '' ? nothing : html`<small class=${index === activeIndex ? '' : 'text-body-secondary'}>${description}</small>`}
                </div>
            `;
        })}`, list);
        if (activeIndex >= 0) {
            input.setAttribute('aria-activedescendant', optionId(activeIndex));
            document.getElementById(optionId(activeIndex))?.scrollIntoView({block: 'nearest'});
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const setStatus = (text: string): void => {
        status.textContent = text;
    };

    const isListOpen = (): boolean => list.matches(':popover-open');

    const positionList = (): void => {
        const rect = input.getBoundingClientRect();
        const gap = 4;
        list.style.top = `${rect.bottom + gap}px`;
        list.style.left = `${rect.left}px`;
        list.style.width = `${rect.width}px`;
        list.style.maxHeight = `${Math.max(160, Math.min(384, window.innerHeight - rect.bottom - 2 * gap))}px`;
    };

    const openList = (): void => {
        positionList();
        if (!isListOpen()) {
            list.showPopover();
            // Capture phase: the modal body scrolls, not the window.
            window.addEventListener('scroll', positionList, true);
            window.addEventListener('resize', positionList);
        }
    };

    const hideList = (): void => {
        if (isListOpen()) {
            list.hidePopover();
        }
        window.removeEventListener('scroll', positionList, true);
        window.removeEventListener('resize', positionList);
    };

    // While a search is pending, the visible results still belong to the previous term.
    const setOutdated = (outdated: boolean): void => {
        list.style.opacity = outdated ? '0.5' : '';
        list.setAttribute('aria-busy', String(outdated));
    };

    const closeList = (): void => {
        setOutdated(false);
        results = [];
        activeIndex = -1;
        showNoResults = false;
        hideList();
        input.setAttribute('aria-expanded', 'false');
        renderOptions();
    };

    const showResults = (found: EventSearchResult[]): void => {
        setOutdated(false);
        results = found;
        activeIndex = found.length > 0 ? 0 : -1;
        showNoResults = found.length === 0;
        openList();
        input.setAttribute('aria-expanded', String(found.length > 0));
        setStatus(found.length === 0 ? labels.noResults : '');
        renderOptions();
    };

    const cancelSearch = (): void => {
        window.clearTimeout(debounceTimer);
        debounceTimer = undefined;
        pendingRequest?.abort();
        pendingRequest = null;
    };

    const isSearchPending = (): boolean => debounceTimer !== undefined || pendingRequest !== null;

    const runSearch = async (term: string): Promise<void> => {
        pendingRequest?.abort();
        const request = new AjaxRequest(url).withQueryArguments({q: term});
        pendingRequest = request;
        try {
            const response = await request.get();
            const data = await response.resolve();
            if (pendingRequest === request) {
                showResults(parseResults(data));
            }
        } catch {
            if (pendingRequest === request) {
                closeList();
            }
        } finally {
            if (pendingRequest === request) {
                pendingRequest = null;
            }
        }
    };

    const choose = (result: EventSearchResult): void => {
        if (isSearchPending()) {
            return;
        }
        cancelSearch();
        closeList();
        setStatus('');
        selected = result;
        selectionTitle.textContent = result.title;
        selectionMeta.textContent = describe(result);
        search.hidden = true;
        selection.hidden = false;
        clearButton.focus();
    };

    const clear = (): void => {
        selected = null;
        input.value = '';
        selection.hidden = true;
        search.hidden = false;
        input.focus();
    };

    const moveActive = (step: number): void => {
        if (results.length === 0) {
            return;
        }
        activeIndex = (activeIndex + step + results.length) % results.length;
        renderOptions();
    };

    input.addEventListener('input', () => {
        cancelSearch();
        const term = input.value.trim();
        if (term.length < MIN_SEARCH_LENGTH) {
            closeList();
            setStatus('');
            return;
        }
        setOutdated(true);
        debounceTimer = window.setTimeout(() => {
            debounceTimer = undefined;
            void runSearch(term);
        }, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', (event: KeyboardEvent) => {
        const isOpen = isListOpen();
        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp':
                if (isOpen) {
                    event.preventDefault();
                    moveActive(event.key === 'ArrowDown' ? 1 : -1);
                }
                break;
            case 'Enter':
                event.preventDefault();
                if (isOpen && activeIndex >= 0) {
                    choose(results[activeIndex]);
                }
                break;
            case 'Escape':
                // Closes the list only, the modal stays open.
                if (isOpen || input.value !== '') {
                    event.preventDefault();
                    event.stopPropagation();
                    cancelSearch();
                    closeList();
                    setStatus('');
                    input.value = '';
                }
                break;
        }
    });

    input.addEventListener('blur', () => {
        cancelSearch();
        if (isListOpen()) {
            closeList();
        }
    });

    clearButton.addEventListener('click', clear);

    return {
        getEventUid: (): number | null => selected?.uid ?? null,
        focus: (): void => (selected ? clearButton : input).focus(),
    };
}

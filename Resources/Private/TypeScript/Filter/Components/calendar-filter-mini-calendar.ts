import {html, LitElement, nothing} from 'lit';
import {formatLocalCalendarDate} from '../Services/calendar-date-utils';
import {
  miniCalendarEvents,
  type MainCalendarDateDetail,
} from '../Services/calendar-mini-calendar-events';

type Typo3TopWindow = Window & {
  TYPO3?: {
    settings?: {
      ximaCalendar?: {
        locale?: string;
      };
    };
  };
};

type MiniCalendarDay = {
  date: Date;
  day: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  isInDisplayedWeek: boolean;
};

const YEARS_BEFORE_CURRENT = 3;
const YEARS_AFTER_CURRENT = 5;

export class CalendarFilterMiniCalendar extends LitElement {
  private displayedMonth = this.startOfMonth(new Date());

  private mainViewType = '';

  private mainViewDate = this.startOfDay(new Date());

  private pendingClickedDate: Date | null = null;

  private pendingNavigationMonth: Date | null = null;

  private readonly handleMainCalendarDateChange = (event: Event): void => {
    const detail = (event as CustomEvent<MainCalendarDateDetail>).detail;
    const date = detail?.date;
    if (!date) {
      return;
    }

    const parsedDate = new Date(date);
    if (Number.isNaN(parsedDate.getTime())) {
      return;
    }

    if (this.pendingClickedDate !== null) {
      if (
        detail?.viewType !== 'timeGridWeek'
        || !this.isSameMiniWeek(parsedDate, this.pendingClickedDate)
      ) {
        this.mainViewDate = this.startOfDay(parsedDate);
        this.mainViewType = detail?.viewType ?? '';
        this.requestUpdate();
        return;
      }
    }

    if (
      this.pendingNavigationMonth !== null
      && (
        detail?.viewType === 'timeGridWeek'
          ? !this.isSameMiniWeek(parsedDate, this.pendingNavigationMonth)
          : !this.isSameMonth(parsedDate, this.pendingNavigationMonth)
      )
    ) {
      this.mainViewDate = this.startOfDay(parsedDate);
      this.mainViewType = detail?.viewType ?? '';
      this.requestUpdate();
      return;
    }

    const synchronizedDate = this.pendingClickedDate
      ?? this.pendingNavigationMonth
      ?? parsedDate;
    this.pendingClickedDate = null;
    this.pendingNavigationMonth = null;
    this.displayedMonth = this.startOfMonth(synchronizedDate);
    this.mainViewDate = this.startOfDay(synchronizedDate);
    this.mainViewType = detail?.viewType ?? '';
    this.requestUpdate();
  };

  public connectedCallback(): void {
    super.connectedCallback();
    window.top.addEventListener(
      miniCalendarEvents.mainDateChange,
      this.handleMainCalendarDateChange,
    );
    window.top.dispatchEvent(new CustomEvent(miniCalendarEvents.ready));
  }

  public disconnectedCallback(): void {
    window.top.removeEventListener(
      miniCalendarEvents.mainDateChange,
      this.handleMainCalendarDateChange,
    );
    super.disconnectedCallback();
  }

  public createRenderRoot(): HTMLElement {
    return this;
  }

  public render() {
    const locale = this.getLocale();
    const monthLabel = new Intl.DateTimeFormat(locale, {
      month: 'long',
    }).format(this.displayedMonth);

    return html`
      <div class="xima-calendar-filter__mini-calendar-header">
        <button
          type="button"
          class="xima-calendar-filter__mini-calendar-navigation"
          aria-label=${locale.startsWith('de') ? 'Vorheriger Monat' : 'Previous month'}
          @click=${() => this.changeMonth(-1)}
        >‹</button>
        <div class="xima-calendar-filter__mini-calendar-title">
          <h3>${monthLabel}</h3>
          <span class="xima-calendar-filter__mini-calendar-year-select">
            <select
              class="xima-calendar-filter__mini-calendar-year"
              name="calendar-year"
              aria-label=${locale.startsWith('de') ? 'Jahr auswählen' : 'Select year'}
              value=${String(this.displayedMonth.getFullYear())}
              @change=${(event: Event) => this.changeYear(event)}
            >
              ${this.getYearOptions().map((year) => html`
                <option
                  value=${year}
                  ?selected=${year === this.displayedMonth.getFullYear()}
                >${year}</option>
              `)}
            </select>
          </span>
        </div>
        <button
          type="button"
          class="xima-calendar-filter__mini-calendar-navigation"
          aria-label=${locale.startsWith('de') ? 'Nächster Monat' : 'Next month'}
          @click=${() => this.changeMonth(1)}
        >›</button>
      </div>

      <div class="xima-calendar-filter__mini-calendar-weekdays" aria-hidden="true">
        ${this.getWeekdays(locale).map((weekday) => html`<span>${weekday}</span>`)}
      </div>

      <div class="xima-calendar-filter__mini-calendar-grid">
        ${this.getDays().map((day) => html`
          <button
            type="button"
            class=${this.getDayClass(day)}
            aria-current=${day.isToday ? 'date' : nothing}
            aria-label=${this.getDateLabel(day.date, locale)}
            @click=${() => this.selectDate(day.date)}
          >${day.day}</button>
        `)}
      </div>
    `;
  }

  private getLocale(): string {
    const typo3Top = window.top as unknown as Typo3TopWindow;

    return typo3Top.TYPO3?.settings?.ximaCalendar?.locale
      ?? document.documentElement.lang
      ?? navigator.language;
  }

  private getWeekdays(locale: string): string[] {
    const formatter = new Intl.DateTimeFormat(locale, {weekday: 'short'});
    const monday = new Date(2024, 0, 1);

    return Array.from({length: 7}, (_, index) => (
      formatter.format(new Date(
        monday.getFullYear(),
        monday.getMonth(),
        monday.getDate() + index,
      )).charAt(0).toUpperCase()
    ));
  }

  private getYearOptions(): number[] {
    const currentYear = new Date().getFullYear();

    return Array.from(
      {length: YEARS_BEFORE_CURRENT + YEARS_AFTER_CURRENT + 1},
      (_, index) => currentYear - YEARS_BEFORE_CURRENT + index,
    );
  }

  private getDays(): MiniCalendarDay[] {
    const firstDay = this.startOfMonth(this.displayedMonth);
    const firstWeekday = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(
      firstDay.getFullYear(),
      firstDay.getMonth() + 1,
      0,
    ).getDate();
    const numberOfWeeks = Math.ceil((firstWeekday + daysInMonth) / 7);
    const today = this.startOfDay(new Date());

    return Array.from({length: numberOfWeeks * 7}, (_, index) => {
      const date = new Date(firstDay);
      date.setDate(firstDay.getDate() + index - firstWeekday);

      return {
        date,
        day: date.getDate(),
        isCurrentMonth: date.getMonth() === this.displayedMonth.getMonth(),
        isToday: this.isSameDay(date, today),
        isInDisplayedWeek: this.mainViewType === 'timeGridWeek'
          && this.isSameDay(
            this.startOfMiniWeek(date),
            this.startOfMiniWeek(this.mainViewDate),
          ),
      };
    });
  }

  private getDayClass(day: MiniCalendarDay): string {
    return [
      'xima-calendar-filter__mini-calendar-day',
      day.isCurrentMonth ? '' : 'xima-calendar-filter__mini-calendar-day--outside',
      day.isToday ? 'xima-calendar-filter__mini-calendar-day--today' : '',
      day.isInDisplayedWeek ? 'xima-calendar-filter__mini-calendar-day--current-week' : '',
    ].filter(Boolean).join(' ');
  }

  private getDateLabel(date: Date, locale: string): string {
    return new Intl.DateTimeFormat(locale, {dateStyle: 'full'}).format(date);
  }

  private changeMonth(monthDelta: number): void {
    const targetMonth = new Date(
      this.displayedMonth.getFullYear(),
      this.displayedMonth.getMonth() + monthDelta,
      1,
    );
    this.navigateToMonth(targetMonth, monthDelta);
  }

  private changeYear(event: Event): void {
    const year = Number((event.target as HTMLSelectElement).value);
    if (!Number.isInteger(year)) {
      return;
    }

    const monthDelta = year < this.displayedMonth.getFullYear() ? -1 : 1;
    this.navigateToMonth(new Date(year, this.displayedMonth.getMonth(), 1), monthDelta);
  }

  private navigateToMonth(targetMonth: Date, monthDelta: number): void {
    this.pendingNavigationMonth = targetMonth;
    this.displayedMonth = this.startOfMonth(targetMonth);
    this.mainViewDate = this.startOfDay(targetMonth);
    this.requestUpdate();
    window.top.dispatchEvent(new CustomEvent(miniCalendarEvents.navigate, {
      detail: {
        direction: monthDelta < 0 ? 'prev' : 'next',
        date: targetMonth.toISOString(),
      },
    }));
  }

  private selectDate(date: Date): void {
    const clickedDate = this.startOfDay(date);
    this.pendingClickedDate = clickedDate;
    this.displayedMonth = this.startOfMonth(date);
    this.mainViewDate = clickedDate;
    this.mainViewType = 'timeGridWeek';
    this.dispatchEvent(new CustomEvent('mini-calendar-date-change', {
      bubbles: true,
      composed: true,
      detail: {date: formatLocalCalendarDate(this.startOfDay(date))},
    }));
    window.top.dispatchEvent(new CustomEvent(miniCalendarEvents.dateChange, {
      detail: {date: formatLocalCalendarDate(this.startOfDay(date))},
    }));
    this.requestUpdate();
  }

  private startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }

  private startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  private startOfMiniWeek(date: Date): Date {
    const start = this.startOfDay(date);
    const weekday = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - weekday);
    return start;
  }

  private isSameDay(first: Date, second: Date): boolean {
    return first.getFullYear() === second.getFullYear()
      && first.getMonth() === second.getMonth()
      && first.getDate() === second.getDate();
  }

  private isSameMonth(first: Date, second: Date): boolean {
    return first.getFullYear() === second.getFullYear()
      && first.getMonth() === second.getMonth();
  }

  private isSameMiniWeek(first: Date, second: Date): boolean {
    return this.isSameDay(this.startOfMiniWeek(first), this.startOfMiniWeek(second));
  }
}

customElements.define(
  'xima-calendar-filter-mini-calendar',
  CalendarFilterMiniCalendar,
);

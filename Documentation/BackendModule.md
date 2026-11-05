# Calendar Module

The *Calendar* backend module (`calendar_calendar`) is a planning surface for appointments: a
month, week and list view on all calendars, a filter sidebar, and creation of events and
appointments straight from the calendar.

## Views

- **Month** (`dayGridMonth`), **Week** (`timeGridWeek`) and **List** (`listMonth`), rendered with
  [`@event-calendar/core` v5](https://github.com/vkurko/calendar) and fed by the
  [calendar feed](CalendarFeed.md). The module opens on today.
- The calendar fills the whole module viewport.
- Clicking an appointment opens its event in the record form.
- A tooltip shows title, date, location and the filter values of an appointment
  (see `tooltipOrder` below).
- Existing appointments can't be moved or resized by dragging; change their dates in the
  record form.

## Filter sidebar

The module has no page tree. Its navigation component is a filter sidebar with a mini calendar
and these filters:

| Identifier | Filter | Notes |
|------------|--------|-------|
| `categories` | Category tree | Only categories used by events, plus their parents. Selecting a category includes its children. Supports colour coding. |
| `statuses` | Status (*Draft*, *Review*, *Rejected*, *Live*, *Canceled*) | Drives the appearance of an appointment: hatched for draft, dashed for review, outlined for rejected, a marker for canceled. |
| `recordTypes` | Event record type | Only shown when more than one record type is available on the pages holding a calendar (resolved like in the record form). Drives the icon. |

Filtering happens on the server. The sidebar doubles as legend for colours, appearances and
icons. Further filters can be added by other extensions, see
[Extending → Calendar module filters](Extending.md#calendar-module-filters).

## Persistence

The module remembers per backend user, in the user settings (`uc`):

- the active view, the selected filter values and the collapsed/expanded sidebar sections
  (module data `calendar_calendar`),
- the calendar used for the last creation (module data `calendar_calendar_creation`).

Stored filter state is validated against the filters available to the user before it is saved,
so values of removed categories or record types are dropped.

## Creating events and appointments

Editors create records in three ways:

- click a day or a time slot,
- drag over a range of days or time slots,
- the *New Event* / *New Event Appointment* buttons in the module's doc header.

All three open a modal for the record type (*Event* or *Event Appointment*), the event record
type, the calendar and the dates. *Create* then opens the native record form with these values
as defaults. **Nothing is stored before the editor saves that form**, so closing it leaves no
record behind and no [DataHandler events](DataHandlerEvents.md) are fired.

- *Event* opens a new event with one appointment.
- *Event Appointment* opens a new appointment; the editor picks its event in the form.

The record is stored on the page of the chosen calendar. Which calendars and record types are
offered, and why an editor may see no creation at all, is described in
[Access Control → Creating records in the calendar module](AccessControl.md#creating-records-in-the-calendar-module).

## Page TSconfig

All options live under `mod.tx_ximatypo3calendar`. The module has no page tree, so they are read
from the **global** page TSconfig (page 0): set them in the `Configuration/page.tsconfig` of your
site package or extension. Values set on a page record don't apply.

The extension ships these values:

```typoscript
mod.tx_ximatypo3calendar {
    filterOrder = recordTypes, categories, statuses
    tooltipOrder = recordTypes, categories, statuses, location

    calendar.firstDay = sunday

    weekViewGrid {
        timeSlotMinutes = 15
        scrollStartTime = 07:00
    }

    eventAppearance {
        iconSource = recordTypes
        appearanceSource = statuses
        colorSource = categories
    }

    newEvent {
        interaction {
            enableDrag = 1
            enableClick = 1
        }

        defaults {
            startTime = 10:00
            endTime = 15:30
            allDay = 0
            recordType = event
        }
    }
}
```

| Option | Values | Default when unset |
|--------|--------|--------------------------------|
| `filterOrder` | Comma-separated filter identifiers. Only listed filters are shown in the sidebar, in this order. | none: a filter that isn't listed (or referenced by `eventAppearance`) is not shown |
| `tooltipOrder` | Comma-separated filter identifiers, plus `location`. Only listed entries are shown in the tooltip, in this order. | none |
| `calendar.firstDay` | English weekday name: `sunday`, `monday`, `tuesday`, `wednesday`, `thursday`, `friday` or `saturday` | `monday` |
| `weekViewGrid.timeSlotMinutes` | `15`, `30` or `60` | `15` |
| `weekViewGrid.scrollStartTime` | Initial scroll position of the week view, `HH:MM` or `HH:MM:SS` | `08:00` |
| `eventAppearance.iconSource` | Filter identifier whose item icon is shown on the appointment | `recordTypes` |
| `eventAppearance.appearanceSource` | Filter identifier whose item appearance (hatched, dashed, outlined) is applied | `statuses` |
| `eventAppearance.colorSource` | Filter identifier that colours the appointment | `categories` |
| `filterColors.<identifier>.<value>` | Fixed colour `#RRGGBB` for a numeric filter item value, e.g. a category uid | generated colour |
| `newEvent.interaction.enableClick` | `1` / `0`: create by clicking a day or time slot | `1` |
| `newEvent.interaction.enableDrag` | `1` / `0`: create by dragging over a range | `1` |
| `newEvent.defaults.startTime` | `HH:MM`, start time of a timed record created from a day selection (month view, all-day row, doc header buttons) | `09:00` |
| `newEvent.defaults.endTime` | `HH:MM`, end time on the last selected day, same cases | `09:30` |
| `newEvent.defaults.allDay` | `1` / `0`: create day selections as all-day. A selection over several days in the month view is always all-day. | `0` |
| `newEvent.defaults.recordType` | Event record type preselected in the modal, if the editor may use it | the first available type |

Filters referenced by `eventAppearance` are always shown in the sidebar and the tooltip, even
when `filterOrder` or `tooltipOrder` doesn't list them, because the appointment is rendered from
them.

Invalid times, slot sizes and colours are ignored, so the default applies. For the `1` / `0`
switches, any value other than `1` disables.

Example: a fixed colour for two categories and a 30-minute grid.

```typoscript
mod.tx_ximatypo3calendar {
    weekViewGrid.timeSlotMinutes = 30

    filterColors {
        categories {
            3 = #1976D2
            7 = #C62828
        }
    }
}
```

The record types offered for new events follow the usual
`TCEFORM.tx_ximatypo3calendar_domain_model_event.record_type` options of the **calendar's page**,
see [Access Control](AccessControl.md#creating-records-in-the-calendar-module).

## Styling the module

The module's CSS is `EXT:xima_typo3_calendar/Resources/Public/Css/calendar.css`, built from
`Resources/Private/Css/`. Event Calendar v5 uses a CSS Grid based DOM; selectors of older
versions such as `.ec-days` no longer exist. See [CONTRIBUTING](../CONTRIBUTING.md) for the
asset build.

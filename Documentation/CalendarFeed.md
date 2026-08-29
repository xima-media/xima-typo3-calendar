# Calendar Feed

The backend Calendar module renders appointments with the
[vkurko/calendar](https://github.com/vkurko/calendar) JS component, fed with JSON.

Two entry points produce that JSON, both through `Serializer\VkurkoCalendarSerializer`:

| Entry point | Route | Used by |
|-------------|-------|---------|
| `Controller\Backend\CalendarController::eventsAction` | Backend AJAX route `ajax_xima_calendar_events` | The Calendar module |
| `Middleware\CalendarApi` | Frontend, page type `1778227923` | — |

## Output format

One object per appointment row, in the vkurko/calendar event shape:

```json
[{
  "id": "entry-42",
  "title": "Kickoff (Annual Conference)",
  "start": "2026-03-01T09:00:00",
  "end": "2026-03-01T09:30:00",
  "allDay": false,
  "extendedProps": {
    "url": "https://example.org/1-annual-conference/a42",
    "calendarUid": 3, "calendarTitle": "Main",
    "recordType": "event-appointment",
    "eventUid": 1, "eventTitle": "Annual Conference",
    "eventDescription": null,
    "eventCategoryTitle": null, "eventCategoryId": null,
    "eventStatus": 2, "eventLanguage": "de", "eventOwner": null,
    "appointmentDescription": null, "appointmentLocation": null,
    "appointmentCanceled": false, "appointmentSpeakers": null
  }
}]
```

Behaviour worth knowing:

- A missing `end` is filled with `start + 30 minutes`, because vkurko/calendar requires one —
  including for all-day events.
- `title` is `"<appointment title> (<event title>)"`, falling back to the event title alone when
  the appointment has none.
- `extendedProps.url` is a frontend URI for the event detail view, built from the
  `xima_typo3_calendar.eventShowPid` site setting. It is `null` when that setting, the site, or
  the event UID is missing. Resolved sites are runtime-cached per page id.

## The `type=1778227923` middleware

`Middleware\CalendarApi` triggers on the query parameter `type`, not on a path, so it answers on
any frontend URL:

```
GET /?type=1778227923&start=2026-01-01&end=2026-12-31&calendars[]=3
```

| Parameter | Meaning |
|-----------|---------|
| `start`, `end` | Any `\DateTime`-parsable string; unbounded when omitted |
| `calendars[]` | Calendar UIDs to include |

**Treat this endpoint as backend-internal.** The page type is declared nowhere else in the
extension — no TypoScript `PAGE` object, no route enhancer — and it calls
`EntryRepository::getBackendCalendarEntries()`, the *backend* query, which does not apply the
[frontend visibility rules](AccessControl.md#query-restrictions). Verify before exposing it, and
prefer the authenticated AJAX route for backend use.

## The other machine-readable output

Appointments are also served as RFC 5545 iCalendar, for visitors rather than for the backend
module — see [Calendar Export](Ics.md). That path goes through the event detail plugin, so
unlike the middleware above it *does* apply the frontend visibility rules.

## Building your own frontend API

The extension deliberately stops at the data model and the backend workflows. It ships no
frontend API — consuming projects expose events the way that suits them. Our own preferred route
is [`xima/xima-typo3-tca-api`](https://github.com/xima-media/xima-typo3-tca-api).

When you build one, read
[Access Control → Working with the restrictions](AccessControl.md#working-with-the-restrictions)
first: both event restrictions are *enforced*, so they apply to your queries too, and
`removeAll()` does not drop them.

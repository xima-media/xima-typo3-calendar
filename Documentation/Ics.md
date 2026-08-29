# Calendar Export

Visitors can take an appointment — or a whole event — into their own calendar, either as an
RFC 5545 `.ics` download or through a deep link into Google Calendar or Outlook.

The extension owns the parts that are the same everywhere: the bytes, the URL that serves
them, and where the provider links point. Buttons, dialogs, labels and which targets to offer
belong to the project, because the same appointment is rendered by a Fluid template in one
site and by a JS component fed from an API in the next.

## The download URL

`Controller\EventController::icsAction` serves the export from the event detail plugin, so the
[frontend visibility rules](AccessControl.md) decide who gets an answer — an appointment of a
non-live event is a 404, exactly as its detail view is.

The route enhancer adds an `.ics` variant of each detail route:

```
/{event_uid}.ics                               → every appointment of the event
/{event_uid}-{event_slug}.ics                  → the same, with the cosmetic slug
/{event_uid}/a{appointment_uid}.ics            → one appointment
/{event_uid}-{event_slug}/a{appointment_uid}.ics
/a{appointment_uid}.ics                        → appointment only
```

Route enhancers are **not** shipped by the site set — sets carry TypoScript and settings, not
routing. Import them into the site's `config.yaml`:

```yaml
imports:
  - resource: "EXT:xima_typo3_calendar/Configuration/Sets/XimaTypo3Calendar/route-enhancers.yaml"
```

Without the import the export still works; its URL is just the query-parameter form.

The response carries `Content-Type: text/calendar; charset=utf-8` and a
`Content-Disposition: attachment` filename derived from the record title. The action is
registered as **non-cacheable** and leaves the plugin through a `PropagateResponseException`,
because a plugin's response body would otherwise be embedded into the detail page and its
headers dropped.

## What ends up in a VEVENT

One `VEVENT` per appointment, mapped by `Service\CalendarExportService`:

| Property | Source |
|----------|--------|
| `UID` | `entry-<uid>@<site host>` |
| `SEQUENCE`, `LAST-MODIFIED` | the later of the appointment's and the event's `tstamp` |
| `DTSTART` / `DTEND` | `start_date` / `end_date` — see below |
| `SUMMARY` | appointment title, falling back to the event title |
| `DESCRIPTION` | appointment description → event description → additional information, as plain text |
| `LOCATION` | appointment location and address, falling back to the event location |
| `URL` | the appointment's detail view |
| `STATUS` | `CANCELLED` when the appointment is canceled, else `CONFIRMED` |
| `CATEGORIES` | the event's categories |
| `ORGANIZER` | the event organizer's e-mail and name |

Three details decide whether a client accepts the file:

- **The UID is stable.** Downloading the same appointment twice produces the same UID, so a
  client updates the entry it already has instead of filing a second one. `SEQUENCE` grows with
  the record, which is what makes a client accept the update at all. Both come from
  `Serializer\IcsSerializer`, never from a random value.
- **All-day appointments end the day after.** RFC 5545 defines `DTEND` as non-inclusive, so a
  one-day appointment runs to the following midnight. `Utility\AppointmentDateUtility` owns that
  rule — and the 30-minute fallback for an appointment without an end — for every consumer,
  including the [calendar feed](CalendarFeed.md).
- **Lines are folded at 75 octets** on character boundaries, `TEXT` values escape `\ ; ,` and
  newlines, and `URL` is left alone because it is a URI value rather than `TEXT`.

Timed values are written in UTC. All-day values are dates without a timezone and are therefore
read in the installation's timezone, so a German midnight does not slip to the previous day.

## Provider deep links

`CalendarExportService::linksForAppointment()` returns a `CalendarExportLinks` object with four
absolute URLs:

| Property | Target |
|----------|--------|
| `ics` | the download above |
| `google` | `calendar.google.com` — compose a new entry |
| `outlookPersonal` | `outlook.live.com` |
| `outlookBusiness` | `outlook.office.com` |

`linksForEvent()` fills the provider links only when the event has exactly one appointment.
Google and Outlook each compose a single entry, so a multi-appointment event has no honest deep
link and the fields stay `null` rather than silently exporting the first appointment.

These links live in the extension rather than in the frontend because they consume the same
normalized dates as the ICS. Deriving the exclusive all-day end a second time in JavaScript is
precisely where the two representations of one appointment drift apart.

Their description is shortened to 1000 characters and gets the detail URL appended, because a
provider link travels in a URL.

## Using it from a template

```html
<html xmlns:calendar="http://typo3.org/ns/Xima/XimaTypo3Calendar/ViewHelpers"
      data-namespace-typo3-fluid="true">

<calendar:exportLinks appointment="{appointment}" as="export">
    <f:if condition="{export.ics}">
        <a href="{export.ics}" download>Download as .ics</a>
    </f:if>
    <f:if condition="{export.google}">
        <a href="{export.google}" target="_blank" rel="noopener noreferrer">Google Calendar</a>
    </f:if>
</calendar:exportLinks>
</html>
```

The ViewHelper takes either `appointment` or `event` and assigns the links to `as`; it renders
no markup of its own. The shipped `Event/Show.html` uses it as a starting point.

## Using it from an API

Projects that render events from their own REST layer — rather than from Fluid — call the
service directly and put the links in the payload:

```php
$links = GeneralUtility::makeInstance(CalendarExportService::class)
    ->linksForAppointment($appointment);

$payload['export'] = [
    'ics' => $links->ics,
    'google' => $links->google,
    'outlookPersonal' => $links->outlookPersonal,
    'outlookBusiness' => $links->outlookBusiness,
];
```

The frontend then needs no date arithmetic and no knowledge of RFC 5545 — an `<a href>` is the
whole integration, and it works with JavaScript disabled, in an e-mail, and behind a QR code.

## Not included

- **A subscribable feed** (`webcal://`, `X-WR-CALNAME`, `REFRESH-INTERVAL`) for a whole
  calendar. That needs a frontend-safe multi-record query first; the only multi-record endpoint
  today is the backend one behind the [calendar feed](CalendarFeed.md).
- **Recurrence** (`RRULE`). Appointments are discrete records, so there is nothing to express.
- **Invitations** (`METHOD:REQUEST`, attendees). That belongs to registration, not to export.

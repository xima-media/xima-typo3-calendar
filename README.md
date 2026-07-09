# TYPO3 Calendar Extension

Standalone calendar and event management extension for TYPO3. It provides editor-facing
backend modules, a frontend event listing and detail view, a status-based publishing workflow
with e-mail notifications, dashboard widgets, and an optional requirements-management layer —
all built on dedicated record types rather than on top of another calendar package.

## Requirements

- PHP >= 8.2
- TYPO3 >= 13.4
- `typo3/cms-dashboard` (powers the dashboard widgets)
- `xima/xima-typo3-recordlist` (powers the Events backend module)

## Installation

```bash
composer require xima/xima-typo3-calendar
```

Then, per site:

1. Add the **XIMA TYPO3 Calendar** site set to your site's `dependencies` (or import it) so the
   frontend route enhancers and TypoScript are loaded.
2. Set the **Event detail page** site setting (`xima_typo3_calendar.eventShowPid`) to the page
   that holds the *Event Detail* plugin.
3. Optionally adjust the [Extension Configuration](#extension-configuration).

## Features

- **Structured event model** — events, their appointments (dates/occurrences), organizers,
  speakers, locations, and optional requirements, each as a first-class record type.
- **Backend modules** — a record-list module for all calendar records and an interactive
  calendar overview.
- **Frontend plugins** — event list and combined event/appointment detail view, with clean
  URLs via site-set route enhancers.
- **Publishing workflow** — draft → review → live/rejected status flow with e-mail
  notifications to reviewers and owners.
- **Ownership & access control** — events track both their owning frontend user and the backend
  user who created them; automatic query restrictions scope visibility in the frontend and the
  backend.
- **Backend permissions** — per-group permissions gate publishing events and seeing other
  editors' unpublished events; users who may not publish get a read-only form on live events.
- **Dashboard widgets** — ready-to-publish events, upcoming appointments, canceled
  appointments, and soon-needed requirements.
- **Requirements management** (optional feature flag) — track resources an appointment needs
  (e.g. beamer, speaker desk) and their bookings.
- **DataHandler change events** — typed PSR-14 events dispatched on create/update/delete of
  calendar records, for downstream integrations.

## Record Types

| Record Type         | Description                                                        | Table                                                  |
|---------------------|-------------------------------------------------------------------|--------------------------------------------------------|
| Calendar            | Calendar container for entries                                    | `tx_ximatypo3calendar_domain_model_calendar`           |
| Event               | Event with appointments, categories, and publishing options       | `tx_ximatypo3calendar_domain_model_event`              |
| Event Appointment   | Individual appointment with date, location, and registration      | `tx_ximatypo3calendar_domain_model_entry`              |
| Organizer           | Event organizer                                                   | `tx_ximatypo3calendar_domain_model_organizer`          |
| Location            | Event location                                                    | `tx_ximatypo3calendar_domain_model_location`           |
| Speaker             | Speaker/presenter                                                 | `tx_ximatypo3calendar_domain_model_speaker`            |
| Requirement         | Requirement for an appointment (e.g. speaker desk, beamer)         | `tx_ximatypo3calendar_domain_model_requirement`        |
| Requirement Booking | Booking of a requirement for a given appointment                  | `tx_ximatypo3calendar_domain_model_requirementbooking` |

An **Event** has a status (`DRAFT`, `REVIEW`, `LIVE`, `REJECTED`), an owning frontend user, and a
backend owner (`owner_be_user` — the backend user who created it, set automatically on creation).
An **Event Appointment** is one dated occurrence of an event; its type can be `inPerson`,
`online`, or `hybrid`. Requirements and requirement bookings are only relevant when the
[requirements-management feature](#requirements-management) is enabled.

### Class Diagram

![Class Diagram](Documentation/Images/calendar.png)

## Backend Modules

The extension registers a **Calendar** module group with two modules:

- **Events** — a record list (built on `xima/xima-typo3-recordlist`) of every calendar record
  type, collected from all pages whose page module is set to `events`, including one level of
  subpages. Use it as the central editing surface for events, organizers, speakers, locations,
  appointments, and requirements.
- **Calendar** — an interactive month/week calendar overview of all appointments, backed by an
  AJAX feed (`ajax_xima_calendar_events`). Each entry links to its event detail view in the
  frontend.

Store calendar records in a sysfolder and set that folder's **page module** to *Events* so it
shows up in the Events module.

## Frontend Plugins

Two content-element plugins are provided:

| Plugin       | Controller action        | Purpose                                                        |
|--------------|--------------------------|----------------------------------------------------------------|
| Event List   | `EventController::list`   | Lists events.                                                  |
| Event Detail | `EventController::show`   | Renders a single event **or** a single appointment of it.      |

The detail plugin accepts either an `event` or an `appointment` argument — when only an
appointment is given, its parent event is resolved automatically. Clean URLs are produced by
the route enhancers shipped in the site set, e.g. `/{event-uid}-{event-slug}` for an event and
`/{event-uid}-{event-slug}/a{appointment-uid}` for a specific appointment.

## Configuration

### Site settings

| Setting                            | Type | Description                                                        |
|------------------------------------|------|--------------------------------------------------------------------|
| `xima_typo3_calendar.eventShowPid` | page | Page that displays the single view of an event and its appointment. |

The detail page id is used both for frontend link generation (e.g. in the calendar feed) and
for the backend **preview** buttons on event and appointment records.

### Extension Configuration

| Key                                    | Type    | Description                                                                                  |
|----------------------------------------|---------|----------------------------------------------------------------------------------------------|
| `notifications.parentCategory`         | int     | Parent category whose children are offered as the notification category filter.              |
| `restrictions.unrestrictedRecordTypes` | string  | Comma-separated `record_type` values exempt from the frontend visibility restrictions.       |
| `features.requirementsManagement`      | boolean | Enables the requirements-management layer (off by default).                                  |

### Requirements management

Disabled by default via the `ximaTypo3Calendar.requirementsManagement` feature flag. When
enabled (through the extension configuration above), appointments can declare the requirements
they need, and those requirements can be booked per appointment — surfaced in the
*soon-needed requirements* dashboard widget.

## Event Workflow & Notifications

Events move through a status workflow: **Draft → Review → Live**, with **Rejected** as an
alternative outcome of a review. Status transitions trigger e-mail notifications
(`EventWorkflowNotification` listener, templates in `Resources/Private/Templates/Email/`):

- Submitted **for review** → notifies subscribed reviewers.
- Moved **to review from the backend** (e.g. pulled back from live) → notifies the event owner
  (not when the owner submitted it themselves from the frontend).
- **Rejected**, set back to **draft**, or published (**Live**) → notifies the event owner.
- A **live event is edited** → notifies backend users subscribed to live-edit notifications.

Owner e-mails carry the optional status message and name the backend user who made the change.

Backend users opt in to notifications under **User Settings → Notifications**:

- *Notify me when new events are submitted for review*
- *Notify me when live events are edited*
- *Only notify for events in these categories* (a category-tree restriction; empty = all)

## Dashboard Widgets

Four widgets are available in the TYPO3 dashboard (widget group *Calendar*):

- **Ready to publish events** — events sitting in review, awaiting a publish decision.
- **Upcoming appointments** — the next scheduled appointments.
- **Canceled appointments** — appointments marked as canceled.
- **Soon needed requirements** — requirements due soon (requires the requirements feature).

Each widget links back into the corresponding backend module for quick action.

## Backend Permissions & Access Control

The extension registers a **Calendar** custom permission group, granted per backend group under
its *Access Lists*. Administrators implicitly have both permissions.

| Permission                | Identifier            | Grants                                                             |
|---------------------------|-----------------------|-------------------------------------------------------------------|
| Publish events (set live) | `publish_live_events` | Setting an event to the **Live** status (publishing it).          |
| See all events            | `view_all_events`     | Seeing every draft/review/rejected event, not only the own ones.  |

**Backend ownership.** Every event records the backend user that created it in `owner_be_user`
(set automatically on creation via a DataHandler hook, shown in the *Workflow* palette).

**Visibility.** The `EventRestriction`/`EntryRestriction` query restrictions also apply in the
backend: a user **without** `view_all_events` only sees live events and the events they own
(plus any [exempted record types](#exempting-record-types)). Users with the permission — and
administrators — see everything.

**Publishing.** Setting an event live requires `publish_live_events`. Without it, the **Live**
option is removed from the status field and the record-list status modal, and the status-change
endpoint refuses the transition.

**Editing live content.** A user without `publish_live_events` cannot edit a live event or an
appointment of a live event — the whole edit form is rendered read-only. Their own
draft/review/rejected events stay fully editable; to change a published event they must first
move it back to review/draft, or hand it to someone who may publish.

## Frontend Visibility Restrictions

Events and entries (appointments) are filtered automatically in the frontend through two
enforced query restrictions registered in `ext_localconf.php`
(`EventRestriction` and `EntryRestriction`). They are appended to **every** query against the
event and entry tables, so visibility rules apply consistently across Extbase
and plain database queries.

The default rules in the frontend:

- Anonymous visitors only see events with status **LIVE** (and their entries).
- A logged-in frontend user additionally sees the events they **own** (and those entries).
- CLI requests are never restricted; backend visibility follows the backend permissions above.

### Exempting record types

Other projects can register additional event record types (e.g. a `private-event` governed by
its own access rules) that should bypass these restrictions entirely. List the `record_type`
values to exempt in the extension configuration:

```
# Extension Configuration → xima_typo3_calendar
restrictions.unrestrictedRecordTypes = private-event, internal-event
```

Records whose `record_type` is in this comma-separated list stay visible regardless of their
status or owner. For entries, the exemption applies when **either** the entry's own record type
**or** its parent event's record type is listed. Leave the setting empty to keep the default
behavior.

## DataHandler Change Events

The extension dispatches custom events during DataHandler datamap and cmdmap operations.

For comprehensive documentation on event types, payloads, use cases, and listener implementation, see [`Documentation/DataHandlerEvents.md`](Documentation/DataHandlerEvents.md).

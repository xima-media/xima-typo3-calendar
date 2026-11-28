# Access Control

Two independent mechanisms:

- **Backend permissions** decide what a backend user may *do* (publish, see other editors' work).
- **Query restrictions** decide which event and appointment rows a query *returns at all*.

## Backend permissions

Registered as the custom permission group `tx_ximatypo3calendar_permissions`
(`ext_tables.php`), granted per backend group under *Access Lists*. Administrators hold both
implicitly — `BackendUserAuthentication::check()` returns `true` for them.

| Identifier            | Grants |
|-----------------------|--------|
| `publish_live_events` | Every status transition (including **Live** and **Rejected**), editing, hiding and deleting live events, editing events of other users and assigning the backend owner. |
| `view_all_events`     | Seeing every draft/review/rejected event, not only the ones the user owns. Seeing an event also allows editing it while it is not live. |

Read them through `CalendarPermissionService::canPublishLiveEvents()` /
`canViewAllEvents()` rather than checking `$GLOBALS['BE_USER']` directly. What a user may do
with a particular event is decided by `Service\EventWorkflowPolicy`, see below.

## Event workflow

`Service\EventWorkflowPolicy` is the single decision point for status changes, event content
and appointments. Every surface asks it:

| Surface | Class | Effect |
|---------|-------|--------|
| DataHandler | `Hooks\EventWorkflowGuardHook` | Drops refused records and commands from the datamap/cmdmap and logs them to the DataHandler error log (*"refused by the event workflow permissions"*); the rest of the save goes through. Covers the record form, the list module, clipboard, import and every other DataHandler caller. |
| Status modal endpoint | `Controller\Backend\EventsController::updateStatus` | Answers **403** for a refused transition, and for events that do not exist or the user cannot see, so the endpoint reveals nothing about hidden events. |
| Status modal | `ViewHelpers\Backend\EventStatusItemsViewHelper` | Offers the current status plus the allowed targets. Without any allowed target the badge is read-only and opens no modal. |
| Status select | `Backend\Tca\EventStatusItemsProcessor` | Same options as the modal. |
| Edit form | `Backend\FormDataProvider\LiveEventReadOnlyForNonPublisher` | Renders what the policy refuses read-only, see [Editing live content](#editing-live-content). |

Administrators and users with `publish_live_events` pass every check. Frontend APIs write as an
administrator with their own access layer and are not affected. Regular TYPO3 permissions
(tables, fields, pages) apply on top.

### Status transitions

| From → To | Without `publish_live_events` | With `publish_live_events` |
|---|---|---|
| Draft → Review | own events | ✓ |
| Review → Draft | own events | ✓ |
| Rejected → Draft / Review | own events | ✓ |
| Keep the current status (save a status message) | own events, except live | ✓ |
| any → Live | ✗ | ✓ |
| Live → any | ✗ | ✓ |
| any → Rejected | ✗ | ✓ |

"Own events" are those whose `owner_be_user` is the acting user. Events in review stay editable
for their owner; there is no lock during review.

- Only publishers take an event offline, so a published event cannot disappear from the website
  without a publisher noticing. Editors ask a publisher to move it back to draft.
- Rejecting is a review decision, so an editor cannot reject other people's submissions. A
  rejected owner moves their event back to draft or review.
- Editors change the status of their own events only, so nobody moves a colleague's work
  through the workflow behind their back.

### Event content

| Action | Without `publish_live_events` | With `publish_live_events` |
|---|---|---|
| Edit a non-live event | if visible (own, or `view_all_events`) | ✓ |
| Edit a live event | ✗, except its appointments | ✓ |
| Change, add or delete the file references (images, files) of a live event | ✗ | ✓ |
| Hide / unhide, delete a live event | ✗ | ✓ |
| Create an event with status *Live* or *Rejected* | ✗ | ✓ |
| Set `owner_be_user` | ✗, always the creating user | ✓ |
| Copy an event | the copy is a *Draft* of the copying user | same |
| Write events the user cannot see | ✗ | ✓ |

- Live content changes only through publishers, so a published event cannot be changed or taken
  down unreviewed through any DataHandler path. Editors copy the event, edit the copy and submit
  it for review, or ask a publisher.
- Copies start as the copying user's draft, so copying a live event does not publish an
  unreviewed duplicate under someone else's name.
- Editors cannot assign or take over events, so ownership (and with it the transitions and the
  event restriction) cannot be shifted by hand. Publishers reassign events when needed.
- Records hidden by the [event restriction](#rules) cannot be written, so the restriction is a
  real boundary and not just a filtered list.
- File references of an event follow the event's edit rights, so a live event's images cannot be
  swapped through the file reference records or pulled into another event's file field. Editors
  ask a publisher.
- The guard decides on the value DataHandler stores: `status`, `owner_be_user` and an
  appointment's `event` are reduced to a plain uid first (DataHandler url-decodes select values
  and cuts them at `|`), so an encoded value cannot slip past a check.

### Appointments

Appointments are managed independently of the event's status:

| Action | Without `publish_live_events` | With `publish_live_events` |
|---|---|---|
| Create / edit / delete appointments of a live event | ✓ | ✓ |
| Create / edit / delete appointments of a visible non-live event | ✓ | ✓ |
| Appointments of events the user cannot see | ✗ | ✓ |

Dates of a running event change often, and editors keep them up to date without a publisher.
Subscribers of live-edit notifications are mailed about these changes, see
[Notifications](Notifications.md#transitions). Moving an appointment to another event, or pulling
one into an event's inline list, needs access to both events.

### Editing live content

For a user without `publish_live_events` a live event's form is read-only except for its
**appointments**: inline appointments can still be created, edited, sorted and deleted. The
appointment form itself is never read-only because of the event's status, only when the user
cannot see the event. Saving the form resubmits the stored values of the read-only fields; the
DataHandler guard ignores them and refuses any value that differs from the stored one.

### Backend ownership

Every event records the backend user that created it in `owner_be_user`, set automatically by
`Hooks\EventOwnerBackendUserHook` and shown in the *Workflow* palette. An explicit assignment in
the create datamap is kept only for publishers and administrators. This is the field
`view_all_events` scopes against.

### Creating records in the calendar module

A selection in the calendar, or the *New Event* / *New Event Appointment* button, opens a modal
in which the editor picks the calendar. The record is stored on the **page of that calendar
record**, so on a multi-site installation each site's events end up in that site's storage
folder without any configuration. Hidden sysfolders work.

The modal only offers calendars the user can create appointments in, i.e. the calendar's page
is inside the user's web mounts, the user has *Edit content* on it and `tables_modify` for
appointments (`Service\CalendarSelectionService`). Creating an event additionally needs
`tables_modify` for events. Administrators see every calendar. Without any available calendar
the module offers no creation at all.

- One available calendar: it is used, and the modal shows no calendar field.
- Several: the calendar of the user's last creation is preselected, as long as it is still
  available.

The modal opens the native record form with the chosen dates as default values. Nothing is
stored until the editor saves that form, and closing it leaves no record behind.

The server checks the submitted calendar against the same list and refuses others with HTTP 403.

The record types offered for a new event, and in the *Type* filter, are resolved like in the
record form: page TSconfig of the calendar's page (`TCEFORM.tx_ximatypo3calendar_domain_model_event.record_type.removeItems`,
`keepItems`, `addItems`, `altLabels`), `itemsProcFunc` and the `authMode` of the editor's groups
all apply.

## Query restrictions

`Database\EventRestriction` and `Database\EntryRestriction` are registered globally in
`ext_localconf.php` under `TYPO3_CONF_VARS['DB']['additionalQueryRestrictions']`, so they are
appended to **every** query against
`tx_ximatypo3calendar_domain_model_event` / `..._entry` — Extbase and plain QueryBuilder alike.

### Rules

| Context | Visible |
|---------|---------|
| CLI | Everything — never restricted. |
| Frontend, anonymous | Events with status `LIVE`, and their appointments. |
| Frontend, logged-in FE user | Additionally the events they **own**, and those appointments. |
| Frontend, regular page or API item | Backend users may preview permitted non-live events. |
| Frontend, public API collection | Events with status `LIVE` only; backend ownership does not widen the collection. |
| Frontend, configured internal API collection | Like a regular page: anonymous requests get `LIVE` only, backend users keep their preview rights. |
| Backend, with `view_all_events` (or admin) | Everything. |
| Backend, without `view_all_events` | `LIVE` events plus the events they own via `owner_be_user`. |
| Any context except public API collections | Plus every record whose type is [exempted](#exempting-record-types). |

The backend is *not* unrestricted. A backend user without `view_all_events` is filtered by the
same restriction class that filters the frontend.

### Frontend API visibility

This applies when the site uses an API under the site setting `tca_api.apiPrefix`
(for example [maikschneider/tca-api](https://github.com/maikschneider/tca-api)). The first path
segment after the prefix is the resource name.

Frontend API collections are live-only by default, including when a backend session is present,
and they don't include [exempted record types](#exempting-record-types). A site with a calendar
API meant for editors can list its resource names in the site setting
`xima_typo3_calendar.internalApiResources`:

```yaml
xima_typo3_calendar:
  internalApiResources: 'appointments'
```

Collections of these resources follow the regular frontend rules instead: anonymous requests
still get `LIVE` events only, and a request with a backend session sees what that user may
preview (all statuses with `view_all_events`, otherwise their own events). The setting never
makes non-live events public. API item requests and regular event detail pages keep the backend
preview behaviour.

### Exempting record types

A project can register additional event record types — a `private-event` governed by its own
access rules, say — that should bypass these restrictions entirely. List the `record_type`
values in the extension configuration:

```
# Extension Configuration → xima_typo3_calendar
restrictions.unrestrictedRecordTypes = private-event, internal-event
```

Those records stay visible regardless of status or owner. For appointments the exemption applies
when **either** the appointment's own record type **or** its parent event's record type is
listed. Leave the setting empty for default behaviour.

Registering the record type itself is described in [Extending](Extending.md#custom-event-record-types).

## Working with the restrictions

### They survive `removeAll()`

Both classes return `true` from `isEnforced()`, which means they implement
`EnforceableQueryRestrictionInterface` — and TYPO3 deliberately **keeps** those when you call:

```php
$queryBuilder->getRestrictions()->removeAll(); // EventRestriction is still applied
```

Dropping them takes an explicit removal per class:

```php
$queryBuilder->getRestrictions()
    ->removeAll()
    ->removeByType(EventRestriction::class)
    ->removeByType(EntryRestriction::class);
```

This bites hardest in functional tests: the globally registered restriction is silently appended
to the test's own query, so assertions measure the production restriction instead of the subject
under test, and fixtures look like they lost rows. The symptom is a test failing with rows
absent while a direct probe of the same expression looks correct — diff the generated SQL to
spot the extra `AND`.

Places that legitimately need a restriction-free read use `Database\RawRecordReader`, for
example the workflow checks, which have to see the foreign drafts they refuse.

### The entry restriction joins, not filters

`EntryRestriction` cannot simply add a `WHERE` on the event table: when the event table is
`LEFT JOIN`ed, TYPO3 moves that table's restrictions into the `ON` clause, which would turn
missing rows into `NULL` columns rather than removing the entry. It therefore builds a
correlated `EXISTS` subquery against the event table instead.

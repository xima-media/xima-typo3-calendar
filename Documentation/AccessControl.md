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
| `publish_live_events` | Setting an event to **Live**, and editing live events and their appointments. |
| `view_all_events`     | Seeing every draft/review/rejected event, not only the ones the user owns. |

Read them through `CalendarPermissionService::canPublishLiveEvents()` /
`canViewAllEvents()` rather than checking `$GLOBALS['BE_USER']` directly.

### How publishing is gated

Three layers, so the permission cannot be bypassed by driving a different surface:

| Layer | Class | Effect |
|-------|-------|--------|
| TCA `itemsProcFunc` | `Backend\Tca\EventStatusItemsProcessor` | Removes **Live** from the status selector. An event that is *already* live keeps the option, so its status still renders and is not silently downgraded on save. |
| Record-list modal | `ViewHelpers\Backend\CanPublishLiveEventsViewHelper` | Hides the Live option in the status modal. |
| AJAX endpoint | `Controller\Backend\EventsController::updateStatus` | Refuses the transition server-side. |

### Editing live content

A user without `publish_live_events` gets the whole edit form read-only on a live event, and on
any appointment whose parent event is live (`Backend\FormDataProvider\LiveEventReadOnlyForNonPublisher`).
Disabled fields are not submitted, so DataHandler writes nothing back either. Their own
draft/review/rejected events stay fully editable; to change a published event they must move it
back to review/draft, or hand it to someone who may publish.

### Backend ownership

Every event records the backend user that created it in `owner_be_user`, set automatically by
`Hooks\EventOwnerBackendUserHook` and shown in the *Workflow* palette. An explicit assignment in
the create datamap is respected. This is the field `view_all_events` scopes against.

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
| Frontend, logged-in backend user | The backend rules below — an editor previewing a draft event on the website gets the page, not a 404. |
| Backend, with `view_all_events` (or admin) | Everything. |
| Backend, without `view_all_events` | `LIVE` events plus the events they own via `owner_be_user`. |
| Any context | Plus every record whose type is [exempted](#exempting-record-types). |

The backend is *not* unrestricted. A backend user without `view_all_events` is filtered by the
same restriction class that filters the frontend.

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

Places that legitimately need a restriction-free read do a raw lookup and say so, for example
`LiveEventReadOnlyForNonPublisher::parentEventIsLive()` reading the true parent status.

### The entry restriction joins, not filters

`EntryRestriction` cannot simply add a `WHERE` on the event table: when the event table is
`LEFT JOIN`ed, TYPO3 moves that table's restrictions into the `ON` clause, which would turn
missing rows into `NULL` columns rather than removing the entry. It therefore builds a
correlated `EXISTS` subquery against the event table instead.

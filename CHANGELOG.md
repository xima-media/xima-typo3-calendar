# Changelog

All notable changes to this extension are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this extension follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- **Calendar export.** Appointments and whole events can be taken into a visitor's own
  calendar, as an RFC 5545 `.ics` download or through a Google / Outlook deep link. New
  `.ics` routes on the event detail plugin, a `CalendarExportService` returning the links as
  data, and an `exportLinks` ViewHelper — so a Fluid site and an API-driven frontend both get
  them without reimplementing date handling. See [Calendar Export](Documentation/Ics.md).
- Test suite: 75 unit and 147 functional tests, with coverage reporting to Codecov.
- Documentation set — [Access Control](Documentation/AccessControl.md),
  [Notifications](Documentation/Notifications.md), [Calendar Feed](Documentation/CalendarFeed.md),
  [Extending](Documentation/Extending.md) — and a [CONTRIBUTING](CONTRIBUTING.md) guide covering
  local setup, tests, static analysis, and the asset build.

### Fixed

- Backend users viewing an event or appointment in the **frontend** were answered with a 404
  whenever the event was not `LIVE`. The frontend branch of the restrictions only ever knew about
  frontend users, so neither the `view_all_events` permission nor event ownership via
  `owner_be_user` counted there. The backend session is now honoured in the frontend as well, so
  editors can preview their drafts on the website.
- The installation instructions claimed that adding the site set loads the route enhancers. Site
  sets carry TypoScript and settings, not routing; the enhancers have to be imported into the
  site's `config.yaml` explicitly.
- Documentation corrections against source: the requirements feature flag is the extension
  configuration key `features.requirementsManagement`, not the unused
  `SYS.features.ximaTypo3Calendar.requirementsManagement` global; route-enhancer variables are
  underscored and four routes are configured, not two; registration fields live on the event, not
  the appointment.
- `Documentation/DataHandlerEvents.md` claimed `EventChangedEvent` dispatches no `updated`
  events. It does, and the workflow notifications depend on it. Deduplication is per DataHandler
  run, not request-wide.
- Restriction test isolation, and wider restriction coverage.

- The **ready-to-publish events** dashboard widget grouped by the event uid while selecting the
  appointment date from the joined table. On MySQL 5.7+ defaults (`ONLY_FULL_GROUP_BY`) the query
  threw and the widget broke; elsewhere it reported an arbitrary appointment's date. It now
  aggregates with `MIN()` and reports the earliest upcoming appointment.
- A pure hidden/unhidden toggle no longer emits a spurious `UPDATED` event carrying only
  `tstamp`. Hiding a **live** event previously mailed every backend user subscribed to live-edit
  notifications, with no changed fields to show. DataHandler-managed control fields are now
  excluded from the change diff, resolved per table from TCA `ctrl`.

### Removed

- `EventRepository::findPublished()` and `findLatest()`, which matched a non-existent `type`
  field and had no callers, together with the unreachable `Event/Latest.html` template — no
  controller action could render it.

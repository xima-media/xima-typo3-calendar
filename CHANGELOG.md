# Changelog

All notable changes to this extension are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this extension follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Test suite: 75 unit and 147 functional tests, with coverage reporting to Codecov.
- Documentation set — [Access Control](Documentation/AccessControl.md),
  [Notifications](Documentation/Notifications.md), [Calendar Feed](Documentation/CalendarFeed.md),
  [Extending](Documentation/Extending.md) — and a [CONTRIBUTING](CONTRIBUTING.md) guide covering
  local setup, tests, static analysis, and the asset build.

### Fixed

- Documentation corrections against source: the requirements feature flag is the extension
  configuration key `features.requirementsManagement`, not the unused
  `SYS.features.ximaTypo3Calendar.requirementsManagement` global; route-enhancer variables are
  underscored and four routes are configured, not two; registration fields live on the event, not
  the appointment.
- `Documentation/DataHandlerEvents.md` claimed `EventChangedEvent` dispatches no `updated`
  events. It does, and the workflow notifications depend on it. Deduplication is per DataHandler
  run, not request-wide.
- Restriction test isolation, and wider restriction coverage.

### Known issues

- A pure hidden/unhidden toggle also emits an `UPDATED` event carrying only `tstamp`. Listeners
  must diff the fields they care about rather than treating `UPDATED` as a promise of a
  meaningful change. See
  [DataHandler Events → Known limitation](Documentation/DataHandlerEvents.md#known-limitation).

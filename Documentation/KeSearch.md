# ke_search indexing

The extension ships a custom indexer for [ke_search](https://extensions.typo3.org/extension/ke_search).
It is optional: without the extension installed nothing is registered.

## What is indexed

One index entry per event. The appointments define which events qualify and supply the sort
date; they are not indexed as entries of their own, so a series with 40 dates stays one search
result.

An event qualifies when

- its status is `LIVE`,
- it is neither hidden nor deleted,
- it lives inside the configured storage folders, and
- at least one of its appointments has not ended yet.

Cancelled appointments count, matching `Event::getNextAppointment()`: a cancelled next date is
still what a visitor is looking for. The end of an appointment follows
`AppointmentDateUtility`, so an all-day appointment stays in the index until the day is over and
one without an end date is assumed to last 30 minutes.

`sortdate` is the start of the appointment representing the event: the next one that has not
started yet, or the earliest of a series that lies entirely in the past.

## Setup

Create an indexer configuration of type **Calendar events** and set

| Field | Meaning |
|-------|---------|
| Startingpoints / sysfolder | Where the events are stored |
| Target page | The detail page the results link to |
| Storage pid | Where ke_search keeps the index entries |
| Index past events | Index events whose appointments have all ended, too |

**Index past events** turns off the time window. By default an event is indexed only while at
least one of its appointments has not ended yet; with the option set, every live event in the
configured folders is indexed regardless of when its appointments were. Use it for an archive
that stays searchable.

The indexer runs in full indexing mode only. That is deliberate: ke_search runs its cleanup in
full mode alone, and the cleanup is what removes an event once its last appointment has passed.

## Adapting it to a project

Two PSR-14 events, both documented with examples in [Extending](Extending.md#ke-search-indexing).

`ModifyKeSearchIndexerQueryEvent` narrows which events are indexed. The extension filters on
visibility and the time window only. Anything installation-specific belongs here, in particular

- `record_type`, when a project has introduced its own event types, and
- `publish_to_website`, which is not filtered by default because installations that leave the
  flag unmaintained would index nothing.

`ModifyKeSearchIndexEntryEvent` shapes the entry itself. `params` defaults to the arguments of
the extension's own `EventDetail` plugin; a project that renders the detail view through its own
route enhancer rewrites them there.

## Languages

Events are not translatable, and the `language` field on an event states the language it is held
in rather than a translation of the record. Entries are therefore written into the site's default
language only. Once the records become translatable this is the place to revisit.

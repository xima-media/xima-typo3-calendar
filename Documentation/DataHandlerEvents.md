# DataHandler Change Events

The extension dispatches typed PSR-14 events when calendar records are created, updated, hidden,
deleted, reactivated, or when their location or date range changes. All of it comes from
`Hooks\DataHandlerEventDispatcherHook`, synchronously inside the DataHandler run.

## Payload

Every event carries the same four properties (from `Event\AbstractRecordChangedEvent`):

| Property | Type | Meaning |
|----------|------|---------|
| `uid` | `int` | The affected record UID |
| `table` | `string` | e.g. `tx_ximatypo3calendar_domain_model_entry` |
| `changeType` | `ChangeType` | Backed enum — use `->value` for the string form |
| `changedFields` | `array` | `fieldName => ['old' => …, 'new' => …]` |

`ChangeType` cases: `CREATED`, `UPDATED`, `HIDDEN`, `DELETED`, `REACTIVATED`, `LOCATION_CHANGED`,
`DATE_RANGE_CHANGED`.

Only fields that actually changed are included; no-op updates where `old === new` are filtered
out. `HIDDEN`, `DELETED`, and `REACTIVATED` are pure lifecycle transitions and carry an empty
`changedFields` by design — every other change type must report at least one changed field to be
dispatched at all.

## Events

| Event | Table | Change types |
|-------|-------|--------------|
| `EventChangedEvent` | `..._event` | `CREATED`, `UPDATED`, `HIDDEN`, `DELETED`, `REACTIVATED`, `LOCATION_CHANGED` |
| `EntryChangedEvent` | `..._entry` | all seven, incl. `DATE_RANGE_CHANGED` |
| `RequirementBookingChangedEvent` | `..._requirementbooking` | `CREATED`, `UPDATED`, `HIDDEN`, `DELETED`, `REACTIVATED` |

`DATE_RANGE_CHANGED` (`start_date`, `end_date`) is appointment-only. `LOCATION_CHANGED`
(`location`) applies to both events and appointments.

`UPDATED` excludes `hidden`, because a pure visibility toggle is owned by the dedicated
`HIDDEN`/`REACTIVATED` events and must not also surface as a generic content update.

`EventChangedEvent` with `UPDATED` is what drives the
[workflow notifications](Notifications.md) — the listener inspects the `status` field diff.

## Writing a listener

```php
<?php

declare(strict_types=1);

namespace YourVendor\YourExtension\EventListener;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EntryChangedEvent;

#[AsEventListener(identifier: 'your-ext/entry-change-listener')]
final readonly class EntryChangeListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(EntryChangedEvent $event): void
    {
        match ($event->changeType) {
            ChangeType::CREATED => $this->logger->info('Appointment created', ['uid' => $event->uid]),
            ChangeType::DATE_RANGE_CHANGED => $this->logger->info('Appointment rescheduled', [
                'uid' => $event->uid,
                'changes' => $event->changedFields,
            ]),
            default => null,
        };
    }
}
```

With `autoconfigure: true` the `#[AsEventListener]` attribute is enough. To register manually:

```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['eventDispatcher']['listeners'][
    \Xima\XimaTypo3Calendar\Event\EntryChangedEvent::class
][] = [
    'listener' => \YourVendor\YourExtension\EventListener\EntryChangeListener::class,
    'method' => '__invoke',
];
```

Because listeners run inside the DataHandler transaction, keep them fast and avoid triggering
further DataHandler writes. `EventListener\EventWorkflowNotification` is the in-tree reference
implementation.

### Payload examples

```php
// RequirementBookingChangedEvent, CREATED
$event->changedFields === [
    'requirement' => ['old' => null, 'new' => '5'],
    'amount' => ['old' => null, 'new' => '2'],
];

// EntryChangedEvent, DATE_RANGE_CHANGED
$event->changedFields === [
    'start_date' => ['old' => 1622505600, 'new' => 1622592000],
];
```

## Behaviour notes

### Deduplication is per DataHandler run

Events are deduplicated by `table:uid:changeType` **for the lifetime of one `DataHandler`
instance**. The datamap and cmdmap phases of a single save share that instance, so a record saved
once produces one event per change type. The moment a different `DataHandler` starts, the keys
are cleared — otherwise long-lived processes (CLI, the scheduler, import queues) would suppress
legitimate repeat events. A request that runs two DataHandler instances therefore *can* dispatch
the same key twice.

### New records emit only `CREATED`

`LOCATION_CHANGED` and `DATE_RANGE_CHANGED` describe a transition from a prior value, which a
brand-new record does not have. Creating a record dispatches `CREATED` alone.

Copying a record in the backend also dispatches `CREATED` — there is no separate copy event.

### Hidden, deleted, reactivated

| State | Field | Event |
|-------|-------|-------|
| Hidden | `hidden=1` | `HIDDEN` |
| Deleted | `deleted=1` (soft delete) | `DELETED` |
| Unhidden | `hidden=0` | `REACTIVATED` |
| Undeleted | — | not supported |

### Cascading inline deletes

Deleting a parent makes TYPO3 recursively delete its inline children, and those children do not
pass through `processCmdmap_postProcess`. The hook therefore dispatches from two places:

- **Direct delete** — from `processCmdmap_postProcess`, after the persisted state changed.
- **Cascaded inline child delete** — from `processCmdmap_deleteAction`, because the post-process
  hook is never called for them.

Consequence for listeners: a cascaded child's `DELETED` arrives slightly earlier than a direct
one, and **the record still exists in the database** at that moment.

### Control fields never trigger an update

DataHandler writes its own `tstamp` (and `crdate` on creation) into the field array before the
hook inspects it, so excluding `hidden` from `UPDATED` is not enough on its own to keep a pure
visibility toggle quiet. The hook therefore also excludes the DataHandler-managed control
fields, resolved per table from TCA `ctrl` rather than hardcoded.

Consequences for listeners: a visibility toggle dispatches `HIDDEN`/`REACTIVATED` only, and an
`UPDATED` always carries at least one field the editor actually changed. Hiding a record while
editing a field in the same save still reports the field change.

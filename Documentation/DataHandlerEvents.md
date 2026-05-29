# DataHandler Change Events

This extension dispatches custom events when records in the calendar tables are created, updated, hidden, deleted, or when specific fields like location or date ranges are changed.

## Overview

All events follow a consistent payload structure:
- `uid` (int): The affected record UID
- `table` (string): The table name (e.g., `tx_ximatypo3calendar_domain_model_entry`)
- `changeType` (string): The type of change (`created`, `updated`, `hidden`, `deleted`, `reactivated`, `location_changed`, `date_range_changed`)
- `changedFields` (array): Dictionary of field changes in format `fieldName => ['old' => <oldValue>, 'new' => <newValue>]`

Only fields that actually changed are included. No-op updates (where `old === new`) are automatically filtered out.
For lifecycle events with `changeType` `hidden`, `deleted`, and `reactivated`, `changedFields` is intentionally empty.

## Event Classes

| Event | Table(s) | Triggered On | Notes |
|---|---|---|---|
| `RequirementBookingLifecycleChangedEvent` | `tx_ximatypo3calendar_domain_model_requirementbooking` | Create, Update, Hide, Delete, Reactivate | |
| `EntryLifecycleChangedEvent` | `tx_ximatypo3calendar_domain_model_entry` | Create, Update, Hide, Delete, Reactivate | Full field tracking on update |
| `EventLifecycleChangedEvent` | `tx_ximatypo3calendar_domain_model_event` | Create, Hide, Delete, Reactivate | No `updated` events dispatched |
| `LocationChangedEvent` | `tx_ximatypo3calendar_domain_model_event`, `tx_ximatypo3calendar_domain_model_entry` | When `location` field changes | Emitted alongside lifecycle events |
| `EntryDateRangeChangedEvent` | `tx_ximatypo3calendar_domain_model_entry` | When `start_date` or `end_date` changes | Emitted alongside lifecycle events |

## Consuming Events

### Create an EventListener

```php
<?php

namespace YourVendor\YourExtension\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Event\EntryLifecycleChangedEvent;
use Xima\XimaTypo3Calendar\Event\ChangeType;

#[AsEventListener(identifier: 'your-ext/entry-change-listener')]
readonly class EntryChangeListener
{
    public function __invoke(EntryLifecycleChangedEvent $event): void
    {
        if ($event->changeType === ChangeType::CREATED) {
            // Handle new entry creation
            \TYPO3\CMS\Core\Utility\DebugUtility::var_dump('Entry created: ' . $event->uid);
        }

        if ($event->changeType === ChangeType::UPDATED) {
            // Handle updates to any field
            foreach ($event->changedFields as $fieldName => $change) {
                echo "Field '{$fieldName}' changed from " . 
                     json_encode($change['old']) . ' to ' . 
                     json_encode($change['new']) . "\n";
            }
        }

        if ($event->changeType === ChangeType::DELETED) {
            // Handle deletion
            // Note: Entry is soft-deleted (deleted=1)
        }
    }
}
```

### Register the Listener

If you're using TYPO3's `Services.yaml`, the listener will be auto-registered via autoconfiguration:

```text
services:
  _defaults:
    autoconfigure: true
  YourVendor\YourExtension\EventListener\EntryChangeListener: ~
```

Alternatively, register manually in `ext_localconf.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['eventDispatcher']['listeners'][
    \Xima\XimaTypo3Calendar\Event\EntryLifecycleChangedEvent::class
][] = [
    'listener' => YourVendor\YourExtension\EventListener\EntryChangeListener::class,
    'method' => '__invoke',
];
```

## Payload Examples

### RequirementBookingLifecycleChangedEvent (created)

```php
$event->uid // 123
$event->table // 'tx_ximatypo3calendar_domain_model_requirementbooking'
$event->changeType // 'created'
$event->changedFields // [
//     'requirement' => ['old' => null, 'new' => '5'],
//     'amount' => ['old' => null, 'new' => '2'],
//     'note' => ['old' => null, 'new' => 'Setup speaker desk'],
// ]
```

### EntryLifecycleChangedEvent (updated with date range change)

```php
$event->uid // 456
$event->table // 'tx_ximatypo3calendar_domain_model_entry'
$event->changeType // 'updated'
$event->changedFields // [
//     'start_date' => ['old' => 1622505600, 'new' => 1622592000],
//     'title' => ['old' => 'Old Title', 'new' => 'New Title'],
// ]
```

### LocationChangedEvent

```php
$event->uid // 789
$event->table // 'tx_ximatypo3calendar_domain_model_entry'
$event->changeType // 'location_changed'
$event->changedFields // [
//     'location' => ['old' => '0', 'new' => '3'],
// ]
```

### EntryDateRangeChangedEvent

```php
$event->uid // 789
$event->table // 'tx_ximatypo3calendar_domain_model_entry'
$event->changeType // 'date_range_changed'
$event->changedFields // [
//     'start_date' => ['old' => 1622505600, 'new' => 1622592000],
//     'end_date' => ['old' => 1622509200, 'new' => 1622595600],
// ]
```

## Behavior Notes

### Deduplication

Events are deduplicated request-wide by `table:uid:changeType`. If the same record and change type are triggered multiple times in a single request, only the first event is dispatched.

### "Created" from Copy

When a record is copied (via TYPO3 backend copy function), the copied record dispatches a `created` event, not a separate copy event.

### Hidden vs. Deleted

- **Hidden** (`hidden=1`): Soft hide via checkbox; dispatches `hidden` event
- **Deleted** (`deleted=1`): True soft delete; dispatches `deleted` event
- **Reactivated**: Unhidden record (`hidden=0`); dispatches `reactivated` event
- **Undelete**: Currently not supported (rarely used in TYPO3)

For the lifecycle events above, `changedFields` is empty by design.

### Cascading Inline Deletes

When a parent record with inline children is deleted, TYPO3 recursively deletes child records via `deleteAction()`.

- **Direct delete** (for example deleting an entry in the backend): the event is dispatched in `processCmdmap_postProcess`, after the persisted state changed.
- **Cascading inline child delete** (for example deleting an entry which deletes related requirement bookings): the child `deleted` event is dispatched in `processCmdmap_deleteAction`, because TYPO3 does not call `processCmdmap_postProcess` for those child deletions.

This means listeners may observe child delete events slightly earlier than direct delete events. The record still exists in the database at dispatch time for cascading inline child deletes.

### Event Update Scope

- `RequirementBooking`: All field changes trigger `updated`
- `Entry`: All field changes trigger `updated`
- `Event`: No `updated` events dispatched (only `created`, `hidden`, `deleted`, `reactivated`)

## Common Use Cases

### Send Notification on Booking Creation

```php
#[AsEventListener(identifier: 'my-ext/notify-booking-created')]
readonly class BookingNotifier
{
    public function __invoke(RequirementBookingLifecycleChangedEvent $event): void
    {
        if ($event->changeType !== ChangeType::CREATED) {
            return;
        }

        // Send email or push notification
        // $this->mailService->sendBookingNotification($event->uid);
    }
}
```

### Track Entry Schedule Changes

```php
#[AsEventListener(identifier: 'my-ext/track-entry-changes')]
readonly class EntryChangeTracker
{
    public function __invoke(EntryDateRangeChangedEvent $event): void
    {
        // Log all date/time changes
        // $this->auditLog->record('entry_datetime_changed', $event->uid, $event->changedFields);
    }
}
```

### Invalidate Cache on Location Change

```php
#[AsEventListener(identifier: 'my-ext/location-cache-invalidator')]
readonly class LocationCacheInvalidator
{
    public function __construct(
        private CacheManager $cacheManager,
    ) {}

    public function __invoke(LocationChangedEvent $event): void
    {
        // Clear frontend cache for affected entry/event
        $this->cacheManager->flushCachesInGroupByTags('pages', ['entry_' . $event->uid]);
    }
}
```

## Example Listener

See `Classes/EventListener/ExampleDataChangeListener.php` for a working example that logs all calendar change events.


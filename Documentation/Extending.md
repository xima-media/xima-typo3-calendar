# Extending

What other extensions can hook into, and how to register it.

## PSR-14 events

| Event | Dispatched by | Purpose |
|-------|---------------|---------|
| `Event\EventChangedEvent` | `Hooks\DataHandlerEventDispatcherHook` | Event record created/updated/hidden/deleted/reactivated |
| `Event\EntryChangedEvent` | same | Appointment record changes, incl. location and date-range changes |
| `Event\RequirementBookingChangedEvent` | same | Requirement booking changes |
| `Event\BeforeWidgetItemsFetchedEvent` | all four widget data providers | Modify a widget's query before it runs |
| `Event\ModifyEventDetailViewEvent` | `Controller\EventController::showAction()` | Add or replace the detail view's assigned variables |
| `Event\ModifyKeSearchIndexerQueryEvent` | `Indexer\EventIndexer` | Narrow which events the ke_search indexer picks up |
| `Event\ModifyKeSearchIndexEntryEvent` | same | Shape a single ke_search index entry |
| `Event\ConfigureCalendarFilterEvent` | `Service\CalendarFilterService` | Add or remove filters of the calendar module's sidebar |
| `Event\BuildCalendarEntriesQueryEvent` | `Domain\Repository\EntryRepository::getBackendCalendarEntries()` | Join tables and select columns for the calendar module's feed |
| `Event\ApplyCalendarFilterEvent` | same | Restrict the feed query to the filter values the editor selected |
| `Event\CalendarEntrySerializationEvent` | `Serializer\VkurkoCalendarSerializer::serializeBackendEntries()` | Add properties and filter values to one feed entry |
| `Event\ModifyEventSearchResultsEvent` | `Service\EventSearchService::search()` | Add details to, drop or reorder the events offered in the creation modal |

The three record-change events share `AbstractRecordChangedEvent` (`uid`, `table`, `changeType`,
`changedFields`) and are documented in detail in [DataHandler Events](DataHandlerEvents.md). They
fire **synchronously inside the DataHandler run**.

### Modifying widget queries

`BeforeWidgetItemsFetchedEvent` carries a mutable `QueryBuilder`. The provider re-reads it after
dispatch, so a listener can narrow, re-order, or replace the query outright.
`getDispatchingClassName()` returns the calling provider's FQCN, so one listener can serve all
four widgets:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;
use Xima\XimaTypo3Calendar\Widgets\Provider\UpcomingAppointmentsDataProvider;

#[AsEventListener(identifier: 'my-ext/scope-calendar-widgets')]
final readonly class ScopeCalendarWidgets
{
    public function __invoke(BeforeWidgetItemsFetchedEvent $event): void
    {
        if ($event->getDispatchingClassName() !== UpcomingAppointmentsDataProvider::class) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();
        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq('calendar', $queryBuilder->createNamedParameter(7)),
        );
        $event->setQueryBuilder($queryBuilder);
    }
}
```

### Enriching the detail view

`ModifyEventDetailViewEvent` carries the variables the detail view is about to be rendered with
(`event`, `appointment`, and `nextAppointment` — the requested appointment, or the one representing
the series when the URL addresses the event alone) plus the Extbase request. The controller re-reads them after dispatch, so
a listener can add variables of its own — a breadcrumb, related records — or replace what the
controller resolved, without registering a project plugin:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use Xima\XimaTypo3Calendar\Event\ModifyEventDetailViewEvent;

#[AsEventListener(identifier: 'my-ext/event-detail-view')]
final readonly class EnrichEventDetailView
{
    public function __invoke(ModifyEventDetailViewEvent $event): void
    {
        $pageId = $event->getRequest()->getAttribute('routing')->getPageId();

        $values = $event->getAssignedValues();
        $values['breadcrumb'] = array_reverse(
            GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get(),
        );

        $event->setAssignedValues($values);
    }
}
```

### Event search of the creation modal

`ModifyEventSearchResultsEvent` carries the events the creation modal is about to offer for a
new appointment, at most ten, plus the search term. Each result is an array with `uid`, `title`,
`firstStart` and `lastEnd` (timestamps of the event's appointments, `null` without any) and
`details`, a list of strings the modal shows after the date range:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Event\ModifyEventSearchResultsEvent;

#[AsEventListener(identifier: 'my-ext/event-search-results')]
final readonly class AddOrganizerToEventSearchResults
{
    public function __invoke(ModifyEventSearchResultsEvent $event): void
    {
        $results = $event->getResults();
        foreach ($results as &$result) {
            $result['details'][] = $this->findOrganizerName($result['uid']);
        }

        $event->setResults($results);
    }
}
```

### ke_search indexing

Registered only when `ke_search` is installed — see [ke_search indexing](KeSearch.md) for what
the indexer selects and how to set it up.

`ModifyKeSearchIndexerQueryEvent` carries the mutable query that collects what to index. It
selects `a.uid AS appointment_uid` and `e.uid AS event_uid` from the entry table aliased `a`,
joined to the event table aliased `e`; the aliases and both column aliases are part of the
contract. The extension filters on the configured folders, the live status, the deleted and
hidden flags of both tables, and the time window; project rules go here — on either table, since
the appointments this query admits are the ones the time window and the sort date are measured
against:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use Xima\XimaTypo3Calendar\Event\ModifyKeSearchIndexerQueryEvent;

#[AsEventListener(identifier: 'my-ext/scope-indexed-events')]
final readonly class ScopeIndexedEvents
{
    public function __invoke(ModifyKeSearchIndexerQueryEvent $event): void
    {
        $queryBuilder = $event->getQueryBuilder();
        $queryBuilder->andWhere(
            // Keep the project's own record types out of the index
            $queryBuilder->expr()->notIn(
                'e.record_type',
                $queryBuilder->createNamedParameter(
                    ['private-event', 'internal-event'],
                    Connection::PARAM_STR_ARRAY,
                ),
            ),
            // And index only the dates that are actually going ahead
            $queryBuilder->expr()->eq('a.canceled', 0),
        );
        $event->setQueryBuilder($queryBuilder);
    }
}
```

`ModifyKeSearchIndexEntryEvent` is dispatched once per event, right before the entry
is stored, and carries the title, content, abstract, tags, params, target page and additional
fields. Project-specific columns are the usual reason to reach for it:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3Calendar\Event\ModifyKeSearchIndexEntryEvent;

#[AsEventListener(identifier: 'my-ext/event-index-entry')]
final readonly class ShapeEventIndexEntry
{
    public function __invoke(ModifyKeSearchIndexEntryEvent $event): void
    {
        $event->setContent(
            $event->getContent() . "\n" . (string)$event->getEventRow()['tx_myext_keywords'],
        );
    }
}
```

### Calendar module filters

The filters of the [calendar module](BackendModule.md#filter-sidebar) are listeners on four
events; the shipped `categories`, `statuses` and `recordTypes` filters use exactly the same API
(`EventListener\CalendarFilter*`). A filter needs up to four steps:

| Event | Step |
|-------|------|
| `ConfigureCalendarFilterEvent` | Describe the filter: a `CheckboxCalendarFilter` (flat list) or a `TreeCalendarFilter` (nested, optional colour coding), with its items. `getRequest()` returns the backend request. |
| `BuildCalendarEntriesQueryEvent` | Add joins and selected columns the other steps need. The query reads the entry table as `e`, the calendar as `c`, the event as `v` and the location as `loc`. |
| `ApplyCalendarFilterEvent` | Restrict the query to the selected values: `getFilters()['filters'][<identifier>]` is a list of the selected item values. Treat them as user input. |
| `CalendarEntrySerializationEvent` | Tell the client which item an entry belongs to with `addFilterValue()`, and add data with `addExtendedProperty()`. The client uses the filter values for colours, icons, appearances and tooltips. |

The three query and serialization events are dispatched for the
[frontend feed](CalendarFeed.md) as well, with no filters selected. Keep `buildQuery()` and
`serialize()` cheap and side-effect free.

A filter is only shown when its identifier is listed in the `filterOrder` page TSconfig (or used
by `eventAppearance`), see [Calendar Module → Page TSconfig](BackendModule.md#page-tsconfig).
The selection is validated against the configured items before it is stored in the user
settings.

Example: filter appointments by their type (in person, online, hybrid). The class lives in your
extension, e.g. `Classes/EventListener/AppointmentTypeFilter.php`; with the usual autowired and
autoconfigured `Services.yaml` the attributes register it.

```php
namespace MyVendor\MyExt\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CheckboxCalendarFilter;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CheckboxCalendarFilterItem;
use Xima\XimaTypo3Calendar\Event\ApplyCalendarFilterEvent;
use Xima\XimaTypo3Calendar\Event\BuildCalendarEntriesQueryEvent;
use Xima\XimaTypo3Calendar\Event\CalendarEntrySerializationEvent;
use Xima\XimaTypo3Calendar\Event\ConfigureCalendarFilterEvent;

final readonly class AppointmentTypeFilter
{
    private const TYPES = ['inPerson' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid'];

    #[AsEventListener(identifier: 'my-ext/calendar-filter-appointment-type')]
    public function build(ConfigureCalendarFilterEvent $event): void
    {
        $filter = new CheckboxCalendarFilter(identifier: 'appointmentTypes', title: 'Appointment type');
        foreach (self::TYPES as $value => $label) {
            $filter->addItem(new CheckboxCalendarFilterItem(label: $label, value: $value));
        }
        $event->addFilter($filter);
    }

    #[AsEventListener(identifier: 'my-ext/calendar-query-appointment-type')]
    public function buildQuery(BuildCalendarEntriesQueryEvent $event): void
    {
        $event->getQueryBuilder()->addSelect('e.type AS appointment_type');
    }

    #[AsEventListener(identifier: 'my-ext/calendar-apply-appointment-type')]
    public function apply(ApplyCalendarFilterEvent $event): void
    {
        $selected = array_intersect(
            $event->getFilters()['filters']['appointmentTypes'] ?? [],
            array_keys(self::TYPES),
        );
        if ($selected === []) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();
        $queryBuilder->andWhere($queryBuilder->expr()->in(
            'e.type',
            $queryBuilder->createNamedParameter(array_values($selected), Connection::PARAM_STR_ARRAY),
        ));
    }

    #[AsEventListener(identifier: 'my-ext/calendar-serialize-appointment-type')]
    public function serialize(CalendarEntrySerializationEvent $event): void
    {
        $event->addFilterValue('appointmentTypes', $event->getSourceRow()['appointment_type'] ?? null);
    }
}
```

```typoscript
mod.tx_ximatypo3calendar.filterOrder = recordTypes, categories, statuses, appointmentTypes
```

To remove a shipped filter, leave its identifier out of `filterOrder` and point
`eventAppearance` sources that use it to another filter (they keep a filter visible), or
remove it in a
`ConfigureCalendarFilterEvent` listener that runs after the shipped one (`after:` its listener
identifier, e.g. `xima-typo3-calendar/configure-status-filter`) and calls `setFilters()`.

## Fluid ViewHelpers

`ViewHelpers\Backend\CanPublishLiveEventsViewHelper` exposes the publish permission to backend
templates. The namespace root is `Xima\XimaTypo3Calendar\ViewHelpers`:

```html
{namespace xtc=Xima\XimaTypo3Calendar\ViewHelpers}
<f:if condition="{xtc:backend.canPublishLiveEvents()}">
    <button>Set live</button>
</f:if>
```

## Template overrides

Three separate mechanisms, depending on what is being overridden:

| Target | Mechanism |
|--------|-----------|
| Frontend plugin templates | TypoScript constants `plugin.tx_ximatypo3calendar.view.{layoutRootPath,templateRootPath,partialRootPath}` — see the [README](../README.md#template-overrides) |
| Notification e-mails | `TYPO3_CONF_VARS['MAIL']['templateRootPaths']` at an index above `1783341481` — see [Notifications](Notifications.md#templates) |
| Record-list columns | Page TSconfig `templates.<vendor/package>.<timestamp>` — the extension registers its own recordlist overrides in `Configuration/page.tsconfig`; register yours at a higher key |

The recordlist override root is `Resources/Private/xima_typo3_recordlist/`, currently holding
`Partials/Columns/EventStatus.html` — the status column and the consumer of the ViewHelper above.

## Custom event record types

Both the record-type label convention and the visibility exemption assume projects add their own
event types.

1. Register the record type in TCA against `tx_ximatypo3calendar_domain_model_event` (or the
   entry table) with your own `record_type` value.
2. Add labels following the extension's own layout — one directory per record type:
   `Resources/Private/Language/RecordTypes/<record-type>/labels.xlf` plus a `de.labels.xlf`
   counterpart.
3. If the type carries its own access rules and must bypass the built-in restrictions, list it in
   `restrictions.unrestrictedRecordTypes` — see
   [Access Control](AccessControl.md#exempting-record-types).

A record type may leave `status` out of its `showitem`. Its events are stored with a `NULL`
status, while new events of a type that shows the field start as `DRAFT`. An event without a
status is never `LIVE`, so the restrictions hide it from anyone who neither owns it nor holds
`view_all_events`, and the dashboard widgets and the ke_search indexer skip it. Such a type
usually belongs in `restrictions.unrestrictedRecordTypes` as well.

The column only changes for new records. Rows created before a record type dropped the field
keep their old value:

```sql
UPDATE tx_ximatypo3calendar_domain_model_event SET status = NULL WHERE record_type = 'plain-event';
```

## Service configuration

`Configuration/Services.php` autoloads `Xima\XimaTypo3Calendar\` and **excludes**
`Classes/Domain/Model/*`. Services are private by default; anything a third party resolves from
the container directly needs an explicit `public()`.

Two widget providers take constructor knobs you can override in your own `Services.yaml`:
`ReadyToPublishEventsDataProvider` (`$limit`, default 8) and
`SoonNeededRequirementsDataProvider` (`$daysInPreview` 2, `$limit` 10).

## Enum values

Useful when writing listeners against the raw field values:

| Enum | Backing | Cases |
|------|---------|-------|
| `EventStatus` | `int` | `DRAFT=0`, `REVIEW=1`, `LIVE=2`, `REJECTED=3`; the column is `NULL` for record types without the status field |
| `EventAppointmentType` | `string` | `inPerson`, `online`, `hybrid` |
| `EventLanguage` | `string` | `ALL=''`, `de`, `en` |

## Backend integration points

The calendar module's page TSconfig is documented in
[Calendar Module → Page TSconfig](BackendModule.md#page-tsconfig).

| Surface | Key |
|---------|-----|
| Module identifiers | `calendar_modules` (group), `calendar_events`, `calendar_calendar` |
| AJAX routes | `xima_calendar_events` → `/xima/calendar/events`; `calendar_event_status_update` → `/xima/calendar/event/status`; calendar module: `xima_calendar_filter_options`, `xima_calendar_filter_state`, `xima_calendar_view_state`, `xima_calendar_new_record` |
| Custom permissions | `TYPO3_CONF_VARS['BE']['customPermOptions']['tx_ximatypo3calendar_permissions']` |
| Dashboard widget group | `xima_typo3_calendar` |
| JavaScript import map | `@xima/xima-typo3-calendar/` → `EXT:xima_typo3_calendar/Resources/Public/JavaScript/` |

## Exposing events to a frontend

The extension ships no frontend API by design — see
[Calendar Feed → Building your own frontend API](CalendarFeed.md#building-your-own-frontend-api).

## Querying event data yourself

If you write your own queries against the event or entry tables, read
[Access Control → Working with the restrictions](AccessControl.md#working-with-the-restrictions)
first. Both restrictions are *enforced*, which means `$queryBuilder->getRestrictions()->removeAll()`
does **not** remove them.

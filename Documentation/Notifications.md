# Event Workflow & Notifications

Events move **Draft → Review → Live**, with **Rejected** as the alternative outcome of a review.
Status transitions and edits to live events send e-mail notifications.

## Pipeline

| Class | Role |
|-------|------|
| `Hooks\DataHandlerEventDispatcherHook` | Dispatches `EventChangedEvent` with the field diff. |
| `EventListener\EventWorkflowNotification` | Maps the diff to a template and a recipient set. |
| `Service\NotificationRecipientResolver` | Resolves owners and opted-in backend users. |
| `Service\NotificationMailService` | Renders the template and sends the mail. |
| `Service\StatusChangeContext` | Request-scoped bridge carrying the acting backend user and the *notify owner* toggle from the status modal into the DataHandler flow. |

Everything runs **synchronously inside the DataHandler run**, which is what makes
`StatusChangeContext` work — and why the templates cannot use `f:translate` (see
[Templates](#templates)).

## Transitions

| Trigger | Template | Recipients |
|---------|----------|------------|
| Status → **Review** | `EventReviewNotification` | Backend users subscribed to review notifications |
| Status → **Review** from the backend (e.g. pulled back from live) | `EventReviewOwnerNotification` | The event's owners |
| Status → **Draft** | `EventDraftNotification` | The event's owners |
| Status → **Live** | `EventPublishedNotification` | The event's owners |
| Status → **Rejected** | `EventRejectedNotification` | The event's owners |
| A **live event is edited** (status unchanged, or already live) | `EventLiveNotification` | Backend users subscribed to live-edit notifications |

The owner mail on a move into review fires only when the change came *from the backend*.
An owner submitting their own event for review from the frontend never populates the
backend-only status change context, so they are not mailed about their own submission.

Publishing (`→ Live`) is the initial go-live and does **not** additionally count as a live edit;
`EventWorkflowNotification::isLiveEventUpdate()` distinguishes the two by inspecting the old and
new status.

## Recipients

**Owners** resolve to *both* the event's frontend user (`owner`) and its backend owner
(`owner_be_user`), deduplicated by e-mail address. Two suppression rules apply:

- The status modal's **notify owner** toggle suppresses every owner-facing mail.
- The acting backend user is dropped from the recipient list, so nobody is mailed about their
  own change.

**Subscribed backend users** opt in per preference under **User Settings → Notifications**:

| Field | Preference |
|-------|------------|
| `tx_ximatypo3calendar_notify_review` | Notify me when new events are submitted for review |
| `tx_ximatypo3calendar_notify_live` | Notify me when live events are edited |
| `tx_ximatypo3calendar_notify_categories` | Only notify for events in these categories |

The category field is a `sys_category` checkbox tree rendered by
`Backend\UserSettings\NotificationCategoryField` (a `TYPO3_USER_SETTINGS` `userFunc`, since the
module has no native category field). Its root comes from the extension configuration key
`notifications.parentCategory`; leave it unset to offer the category roots.

**An empty category selection means "all categories"**, not "none".

## Mail contents

- Owner mails carry the optional status message and name the backend user who made the change.
- Only recipients with backend access receive the backend edit link.
- The live-edit mail lists the changed field labels, resolved from the table's TCA. System
  fields (`t3ver_*`, `status_message`, and the TCA ctrl/enable columns) are filtered out, so
  renamed enable columns are covered too.

## Templates

Six templates in `Resources/Private/Templates/Email/`:

```
EventReviewNotification.html       EventDraftNotification.html
EventReviewOwnerNotification.html  EventPublishedNotification.html
EventLiveNotification.html         EventRejectedNotification.html
```

They **must not use `f:translate`**. They are rendered from the DataHandler flow — including CLI
and scheduler runs — where no TSFE exists. Labels are passed in from
`NotificationMailService::TEMPLATE_EMAIL_LABELS` instead.

To override them, register a higher `templateRootPaths` index for TYPO3's mail rendering:

```php
// ext_localconf.php of your site extension
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1800000000]
    = 'EXT:my_site/Resources/Private/Templates/CalendarEmail/';
```

The extension itself registers index `1783341481`; pick anything higher.

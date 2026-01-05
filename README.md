# TYPO3 Calendar Extension (xima/xima-typo3-calendar)

Standalone calendar and event management extension for TYPO3 13.4+.

## Migration Status

This extension was created by migrating the event feature from `xm_dkfz_net_events` and `xm_dkfz_net_site` extensions.

### What's Been Migrated

✅ **Completed:**
- Extension structure and composer.json
- 6 Domain models (Event, EventOrganizer, EventAppointment, EventLocation, EventSpeaker, DraftStatus)
- Backend CalendarController (renamed from EventController)
- Frontend EventController (refactored to use database instead of API)
- EventRepository for database access
- 5 ContentBlocks with updated table names
- 4 Event listeners and operation handlers
- 2 ViewHelpers (Base64, JsonDecode)
- All configuration files (Backend, Extbase, TCA, TypoScript, Icons, Services)
- Fluid templates (List.html, Latest.html)
- Language files (English and German)
- Icons

### Database Schema

**New table names:**
- `tx_ximatypo3calendar_domain_model_event`
- `tx_ximatypo3calendar_domain_model_organizer`
- `tx_ximatypo3calendar_domain_model_appointment`
- `tx_ximatypo3calendar_domain_model_location`
- `tx_ximatypo3calendar_domain_model_speaker`

## Installation & Migration Steps

### 1. Register Extension

Add to root `composer.json`:

```json
{
  "require": {
    "xima/xima-typo3-calendar": "@dev"
  }
}
```

### 2. Install Extension

```bash
composer update
vendor/bin/typo3 extension:activate xima_typo3_calendar
vendor/bin/typo3 cache:flush
```

### 3. Migrate Data

The extension includes a migration command to copy data from old tables to new ones:

```bash
vendor/bin/typo3 calendar:migrate
```

This command will:
- Copy all event data from `tx_xima_event*` tables to new tables
- Update file references in `sys_file_reference`
- Update category relations in `sys_category_record_mm`

### 4. Verify Migration

1. Check database: Verify records in new tables
2. Test backend module: Access "Web > Calendar Events"
3. Test frontend: Display events using plugins
4. Test API: Verify T3API endpoints work

### 5. Update Site Configuration

Replace old event plugins with new ones:
- Old: `xm_dkfz_net_events` plugins
- New: `xima_typo3_calendar` plugins (LatestEvents, ListEvents)

### 6. Clean Up Old Extensions (After Verification)

Once migration is verified and working:

1. Remove event code from `xm_dkfz_net_site`:
   - Delete event-related classes, controllers, models
   - Remove event configuration from TCA, routing, etc.

2. Deprecate or remove `xm_dkfz_net_events` extension

3. Drop old database tables:
   ```sql
   DROP TABLE tx_xima_event;
   DROP TABLE tx_xima_eventorganizer;
   DROP TABLE tx_xima_eventappointment;
   DROP TABLE tx_xima_eventlocation;
   DROP TABLE tx_xima_eventspeaker;
   ```

## Features

### Backend Module

- Manage events, appointments, locations, speakers, and organizers
- List and edit all event-related records
- Draft and change request workflow

### Frontend Plugins

- **Latest Events**: Display latest published events
- **List Events**: Display a configurable list of events

### API Endpoints (T3API)

- GET `/events` - List events
- GET `/events/{id}` - Get event details
- POST `/events` - Create event
- PATCH `/events/{id}` - Update event
- POST `/events/{id}/change` - Create change request

## Configuration

### TypoScript

```typoscript
plugin.tx_ximatypo3calendar {
  settings {
    storagePid = 123  # PID where events are stored
  }
}

plugin.tx_ximatypo3calendar_latestevents.settings {
  maxItems = 3  # Number of events to display
}

plugin.tx_ximatypo3calendar_listevents.settings {
  maxItems = 10
}
```

## Architecture Decisions

- **Database-only**: No external API integration (simplified from original)
- **Standard TYPO3 classes**: Uses core FileReference, Category, FrontendUser instead of custom classes
- **ContentBlocks**: Modern TYPO3 13.4 record type configuration
- **Portable**: Fully independent extension with no site-specific dependencies

## Requirements

- PHP ~8.2
- TYPO3 ^13.4
- sourcebroker/t3api (for API functionality)

## Support

For issues or questions, please refer to the main project documentation.

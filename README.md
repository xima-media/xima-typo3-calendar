# TYPO3 Calendar Extension

Calendar and event management extension for TYPO3.

## Requirements

- PHP >= 8.2
- TYPO3 >= 13.4
- sourcebroker/t3api

## Installation

```bash
composer require xima/xima-typo3-calendar
```

## Features

- Calendar and event management
- REST API for events (via t3api)
- Backend module for calendar overview
- Frontend user ownership and access control

## Record Types

| Record Type       | Description                                                  | Table                                           |
|-------------------|--------------------------------------------------------------|-------------------------------------------------|
| Calendar          | Calendar container for entries                               | `tx_ximatypo3calendar_domain_model_calendar`    |
| Event             | Event with appointments, categories, and publishing options  | `tx_ximatypo3calendar_domain_model_event`       |
| Event Appointment | Individual appointment with date, location, and registration | `tx_ximatypo3calendar_domain_model_entry`       |
| Organizer         | Event organizer                                              | `tx_ximatypo3calendar_domain_model_organizer`   |
| Location          | Event location                                               | `tx_ximatypo3calendar_domain_model_location`    |
| Speaker           | Speaker/presenter                                            | `tx_ximatypo3calendar_domain_model_speaker`     |
| Requirement       | Requirement for an appointment (e.g. speaker desk, beamer)   | `tx_ximatypo3calendar_domain_model_requirement` |

### Class Diagram

![Class Diagram](Documentation/Images/calendar.png)

## API Endpoints

| Method | Endpoint             | Description                        |
|--------|----------------------|------------------------------------|
| GET    | `/events`            | List all events                    |
| GET    | `/events/{id}`       | Get single event                   |
| POST   | `/events`            | Create event (authenticated)       |
| PATCH  | `/events/{id}`       | Update event (owner only)          |
| DELETE | `/events/{id}`       | Delete event (owner only)          |
| GET    | `/appointments`      | List all appointments              |
| POST   | `/appointments`      | Create appointment (authenticated) |
| PATCH  | `/appointments{id}`  | Update appointment (owner only)    |
| DELETE | `/appointments/{id}` | Delete appointment (owner only)    |

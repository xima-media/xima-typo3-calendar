# TYPO3 Calendar Extension

Calendar and event management extension for TYPO3.

## Requirements

- PHP >= 8.2
- TYPO3 >= 13.4
- friendsoftypo3/content-blocks
- sourcebroker/t3api

## Installation

```bash
composer require xima/xima-typo3-calendar
```

## Features

- Calendar and event management via Content Blocks
- REST API for events (via t3api)
- Backend module for calendar overview
- Frontend user ownership and access control

## Content Blocks

| Record Type              | Description                                                  |
|--------------------------|--------------------------------------------------------------|
| `xima/calendar`          | Calendar container for entries                               |
| `xima/event`             | Event with appointments, categories, and publishing options  |
| `xima/event-appointment` | Individual appointment with date, location, and registration |
| `xima/organizer`         | Event organizer                                              |
| `xima/location`          | Event location                                               |
| `xima/speaker`           | Speaker/presenter                                            |

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

<?php

return [
    'calendar_event_status_update' => [
        'path' => '/xima/calendar/event/status',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\EventsController::class . '::updateStatus',
    ],
    'xima_calendar_events' => [
        'path' => '/xima/calendar/events',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarController::class . '::eventsAction',
    ],
    'xima_calendar_create_event' => [
        'path' => '/xima/calendar/event/create',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarEventCreationController::class . '::createEventAction',
    ],
    'xima_calendar_cleanup_event' => [
        'path' => '/xima/calendar/event/cleanup',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarEventCreationController::class . '::cleanupEventAction',
    ],
];

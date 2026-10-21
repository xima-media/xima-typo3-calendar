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
    'xima_calendar_filter_options' => [
        'path' => '/xima/calendar/filter/options',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarFilterController::class . '::optionsAction',
    ],
    'xima_calendar_filter_state' => [
        'path' => '/xima/calendar/filter/state',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarFilterController::class . '::saveStateAction',
    ],
    'xima_calendar_view_state' => [
        'path' => '/xima/calendar/view/state',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarFilterController::class . '::saveViewAction',
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

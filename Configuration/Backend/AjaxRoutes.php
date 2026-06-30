<?php

return [
    'calendar_event_review' => [
        'path' => '/xima/calendar/event/review',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\EventsController::class . '::reviewEvent',
    ],
    'xima_calendar_events' => [
        'path' => '/xima/calendar/events',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarController::class . '::eventsAction',
    ],
];

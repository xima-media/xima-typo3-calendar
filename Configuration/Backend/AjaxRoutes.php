<?php

return [
    'calendar_event_review' => [
        'path' => '/xima/calendar/event/review',
        'target' => \Xima\XimaTypo3Calendar\Controller\Backend\CalendarController::class . '::reviewEvent',
    ],
];

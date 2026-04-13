<?php

return [
    'calendar_events' => [
        'parent' => 'web',
        'position' => ['after' => 'list'],
        'access' => 'user',
        'iconIdentifier' => 'module-calendar-events',
        'workspaces' => '*',
        'labels' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_events.xlf',
        'extensionName' => 'XimaTypo3Calendar',
        'controllerActions' => [
            \Xima\XimaTypo3Calendar\Controller\Backend\EventsController::class => [
                'processRequest',
            ],
        ],
        'inheritNavigationComponentFromMainModule' => false,
    ],
    'calendar_calendar' => [
        'parent' => 'web',
        'position' => ['after' => 'calendar_events'],
        'access' => 'user',
        'iconIdentifier' => 'module-calendar-calendar',
        'workspaces' => '*',
        'labels' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf',
        'extensionName' => 'XimaTypo3Calendar',
        'controllerActions' => [
            \Xima\XimaTypo3Calendar\Controller\Backend\CalendarController::class => [
                'processRequest',
            ],
        ],
        'inheritNavigationComponentFromMainModule' => false,
    ],
];

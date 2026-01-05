<?php

return [
    'calendar_events' => [
        'parent' => 'web',
        'position' => ['after' => 'list'],
        'access' => 'user',
        'iconIdentifier' => 'module-calendar-events',
        'workspaces' => '*',
        'labels' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'XimaTypo3Calendar',
        'controllerActions' => [
            \Xima\XimaTypo3Calendar\Controller\Backend\CalendarController::class => [
                'processRequest',
            ],
        ],
        'inheritNavigationComponentFromMainModule' => false,
    ],
];

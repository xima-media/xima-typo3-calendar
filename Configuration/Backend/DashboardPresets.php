<?php

declare(strict_types=1);

return [
    'xima_typo3_calendar' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:dashboard_preset.calendar',
        'description' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:dashboard_preset.calendar.description',
        'iconIdentifier' => 'module-calendar-calendar',
        'defaultWidgets' => [
            'xima_readytopublishevents',
            'xima_upcomingappointments',
            'xima_canceledappointments',
            // Registered only with features/requirementsManagement; the dashboard skips unknown identifiers.
            'xima_soonneededrequirements',
        ],
        'showInWizard' => true,
    ],
];

<?php

declare(strict_types=1);

return [
    'frontend' => [
        'xima/calendar-visibility' => [
            'target' => \Xima\XimaTypo3Calendar\Middleware\CalendarVisibility::class,
            'before' => [
                'maikschneider/tca-api/middleware',
            ],
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
        'xima/calendar-api' => [
            'target' => \Xima\XimaTypo3Calendar\Middleware\CalendarApi::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];

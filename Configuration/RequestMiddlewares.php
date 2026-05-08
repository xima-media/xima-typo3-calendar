<?php

declare(strict_types=1);

return [
    'frontend' => [
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

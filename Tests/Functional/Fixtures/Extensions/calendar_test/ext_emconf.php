<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Calendar test fixture',
    'description' => 'Test fixture extension for the xima_typo3_calendar functional tests.',
    'category' => 'example',
    'version' => '1.0.0',
    'state' => 'stable',
    'author' => 'XIMA Media GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'xima_typo3_calendar' => '',
        ],
    ],
];

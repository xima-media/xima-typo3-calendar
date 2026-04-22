<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/speaker/labels.xlf:title',
        'label' => 'last_name',
        'hideTable' => false,
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-speaker',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,first_name,last_name,department',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/speaker/labels.xlf:title.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
        'first_name' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/speaker/labels.xlf:first_name.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
        'last_name' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/speaker/labels.xlf:last_name.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'required' => true,
            ],
        ],
        'department' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/speaker/labels.xlf:department.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,title,first_name,last_name,department,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden',
        ],
    ],
];



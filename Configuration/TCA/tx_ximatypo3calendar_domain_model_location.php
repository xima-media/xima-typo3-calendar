<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/location/labels.xlf:title',
        'label' => 'name',
        'hideTable' => false,
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-location',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'name',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
    ],
    'columns' => [
        'name' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/location/labels.xlf:name.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'required' => true,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,name,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden',
        ],
    ],
];



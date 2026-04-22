<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/organizer/labels.xlf:title',
        'label' => 'title',
        'hideTable' => false,
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-organizer',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'editlock' => 'editlock',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
        'access' => ['showitem' => 'editlock'],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/organizer/labels.xlf:title.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,title,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden,--palette--;;access',
        ],
    ],
];



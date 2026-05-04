<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement/labels.xlf:title',
        'label' => 'title',
        'hideTable' => false,
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-requirement',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'editlock' => 'editlock',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,description',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
        'access' => ['showitem' => 'editlock'],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement/labels.xlf:title.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement/labels.xlf:description.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'assignee' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement/labels.xlf:assignee.label',
            'exclude' => true,
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'foreign_table' => 'fe_users',
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,title,description,assignee,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden,--palette--;;access',
        ],
    ],
];

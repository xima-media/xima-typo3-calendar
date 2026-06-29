<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement-booking/labels.xlf:title',
        'label' => 'requirement',
        'hideTable' => true,
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-requirementbooking',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'note',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
        'access' => ['showitem' => 'editlock'],
        'amount_palette' => ['showitem' => 'amount,start_date,end_date'],
    ],
    'columns' => [
        'foreign_table_parent_uid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'requirement' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement-booking/labels.xlf:requirement.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_requirement',
                'relationship' => 'manyToOne',
                'maxitems' => 1,
            ],
        ],
        'amount' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement-booking/labels.xlf:amount.label',
            'exclude' => true,
            'config' => [
                'type' => 'number',
                'default' => 1,
            ],
        ],
        'start_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement-booking/labels.xlf:start_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'end_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement-booking/labels.xlf:end_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'note' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/requirement-booking/labels.xlf:note.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'rows' => 3,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,requirement,--palette--;;amount_palette,note,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden,--palette--;;access',
        ],
    ],
];

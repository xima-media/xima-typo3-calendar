<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/calendar/labels.xlf:title',
        'label' => 'title',
        'hideTable' => false,
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-calendar',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
            'fe_group' => 'fe_group',
        ],
        'searchFields' => 'title,description',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
        'access' => ['showitem' => 'starttime,endtime,--linebreak--,fe_group'],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/calendar/labels.xlf:title.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/calendar/labels.xlf:description.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'entries' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/calendar/labels.xlf:entries.label',
            'exclude' => true,
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_entry',
                'foreign_field' => 'calendar',
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,title,description,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/calendar/labels.xlf:tabs.entries_tab,entries,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden,--palette--;;access',
        ],
    ],
];



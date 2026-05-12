<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:title',
        'label' => 'start_date',
        'hideTable' => true,
        'type' => 'record_type',
        'typeicon_column' => 'record_type',
        'typeicon_classes' => [
            'event-appointment' => 'tx-ximatypo3calendar-entry-event-appointment',
            'default' => 'tx-ximatypo3calendar-entry-event-appointment',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,description,online_link,ticket_link,address,directions,speakers',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
        'datetime_palette' => ['showitem' => 'start_date,end_date,all_day,--linebreak--,doors_open'],
        'attendance_palette' => ['showitem' => 'online_link,ticket_link'],
    ],
    'columns' => [
        'event' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:event.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_event',
                'required' => true,
                'maxitems' => 1,
            ],
        ],
        'record_type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 'event-appointment',
                'items' => [
                    [
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:title',
                        'value' => 'event-appointment',
                        'icon' => 'tx-ximatypo3calendar-entry-event-appointment',
                    ],
                ],
            ],
        ],
        'calendar' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:calendar.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_calendar',
                'maxitems' => 1,
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:title.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'size' => 60,
                'max' => 255,
            ],
        ],
        'start_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:start_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
                'required' => true,
                'format' => 'datetime',
                'disableAgeDisplay' => true,
            ],
        ],
        'end_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:end_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'disableAgeDisplay' => true,
            ],
        ],
        'all_day' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:all_day.label',
            'exclude' => true,
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'canceled' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:canceled.label',
            'exclude' => true,
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'modified_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:modified_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:description.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:type.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'value' => 'inPerson',
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:type.items.inPerson.label',
                    ],
                    [
                        'value' => 'online',
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:type.items.online.label',
                    ],
                    [
                        'value' => 'hybrid',
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:type.items.hybrid.label',
                    ],
                ],
            ],
        ],
        'online_link' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:online_link.label',
            'exclude' => true,
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
            ],
        ],
        'ticket_link' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:ticket_link.label',
            'exclude' => true,
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
            ],
        ],
        'location' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:location.label',
            'exclude' => true,
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_ximatypo3calendar_domain_model_location',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_location',
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'address' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:address.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'rows' => 3,
            ],
        ],
        'directions' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:directions.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'rows' => 5,
            ],
        ],
        'speakers' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:speakers.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'size' => 60,
                'max' => 255,
            ],
        ],
        'requirement_bookings' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:requirement_bookings.label',
            'exclude' => true,
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_requirementbooking',
                'foreign_field' => 'foreign_table_parent_uid',
                'appearance' => [
                    'collapseAll' => true,
                    'useSortable' => true,
                ],
            ],
        ],
        'contact' => [
            'exclude' => true,
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:contact.label',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'foreign_table' => 'fe_users',
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'doors_open' => [
            'exclude' => true,
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:doors_open.label',
            'config' => [
                'type' => 'datetime',
                'format' => 'time',
                'disableAgeDisplay' => true,
            ],
        ],
        'notes' => [
            'exclude' => true,
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:notes.label',
            'config' => [
                'type' => 'text',
                'rows' => 3,
            ],
        ],
        'max_participants' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:max_participants.label',
            'exclude' => true,
            'config' => [
                'type' => 'number',
            ],
        ],
    ],
    'types' => [
        'event-appointment' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,record_type,calendar,event,title,--palette--;;datetime_palette,canceled,modified_date,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:tabs.content_tab,description,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:tabs.location_tab,type,--palette--;;attendance_palette,location,address,directions,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:tabs.speakers_tab,speakers,contact,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:tabs.participants_tab,max_participants,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event-appointment/labels.xlf:tabs.requirements_tab,requirement_bookings,notes,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden',
        ],
    ],
];

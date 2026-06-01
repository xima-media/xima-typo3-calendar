<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:title',
        'label' => 'title',
        'hideTable' => false,
        'type' => 'record_type',
        'typeicon_classes' => [
            'default' => 'tx-ximatypo3calendar-event',
        ],
        'delete' => 'deleted',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'sortby' => 'sorting',
        'editlock' => 'editlock',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,description,additional_information,url,registration_link,fee,registration_address',
    ],
    'palettes' => [
        'hidden' => ['showitem' => 'hidden'],
        'access' => ['showitem' => 'editlock'],
        'event_info_palette' => ['showitem' => 'title,language'],
        'publishing_palette' => ['showitem' => 'publish_to_website,publish_to_feed,publish_date'],
        'registration_palette' => ['showitem' => 'requires_registration,registration_link,registration_deadline,fee'],
        'workflow_palette' => ['showitem' => 'owner,status'],
    ],
    'columns' => [
        'record_type' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:record_type.label',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 'default',
                'items' => [
                    [
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:title',
                        'value' => 'default',
                        'icon' => 'tx-ximatypo3calendar-event',
                    ],
                ],
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:title.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
                'size' => 60,
                'max' => 255,
            ],
        ],
        'language' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:language.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'value' => '',
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:language.items.label',
                    ],
                    [
                        'value' => 'de',
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:language.items.de.label',
                    ],
                    [
                        'value' => 'en',
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:language.items.en.label',
                    ],
                ],
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:description.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
            ],
        ],
        'additional_information' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:additional_information.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'rows' => 3,
            ],
        ],
        'location' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:location.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_location',
                'items' => [
                    [
                        'label' => '',
                        'value' => null,
                    ],
                ],
                'maxitems' => 1,
            ],
        ],
        'url' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:url.label',
            'exclude' => true,
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
            ],
        ],
        'preview_image' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:preview_image.label',
            'exclude' => true,
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'maxitems' => 1,
            ],
        ],
        'files' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:files.label',
            'exclude' => true,
            'config' => [
                'type' => 'file',
            ],
        ],
        'publish_to_website' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:publish_to_website.label',
            'exclude' => true,
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'publish_to_feed' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:publish_to_feed.label',
            'exclude' => true,
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'publish_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:publish_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'appointments' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:appointments.label',
            'exclude' => true,
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_entry',
                'foreign_field' => 'event',
            ],
        ],
        'organizer' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:organizer.label',
            'exclude' => true,
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_ximatypo3calendar_domain_model_organizer',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_organizer',
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'hosts' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:hosts.label',
            'exclude' => true,
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'foreign_table' => 'fe_users',
            ],
        ],
        'categories' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:categories.label',
            'exclude' => true,
            'config' => [
                'type' => 'category',
                'relationship' => 'manyToMany',
            ],
        ],
        'requires_registration' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:requires_registration.label',
            'exclude' => true,
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'registration_link' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:registration_link.label',
            'exclude' => true,
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url', 'page'],
            ],
        ],
        'registration_deadline' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:registration_deadline.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'fee' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:fee.label',
            'exclude' => true,
            'config' => [
                'type' => 'input',
            ],
        ],
        'registration_address' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:registration_address.label',
            'exclude' => true,
            'config' => [
                'type' => 'text',
                'rows' => 3,
            ],
        ],
        'related_events' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:related_events.label',
            'exclude' => true,
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_ximatypo3calendar_domain_model_event',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_event',
            ],
        ],
        'owner' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:owner.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
            ],
        ],
        'status' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:status.label',
            'exclude' => true,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'value' => 0,
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:status.items.0.label',
                    ],
                    [
                        'value' => 1,
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:status.items.1.label',
                    ],
                    [
                        'value' => 2,
                        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:status.items.2.label',
                    ],
                ],
            ],
        ],
        'approval_date' => [
            'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:approval_date.label',
            'exclude' => true,
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        'default' => [
            'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,record_type,--palette--;;event_info_palette,description,additional_information,location,url,preview_image,files,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.publishing_tab,--palette--;;publishing_palette,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.appointments_tab,appointments,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.organizer_tab,organizer,hosts,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.category_tab,categories,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.registration_tab,--palette--;;registration_palette,registration_address,--palette--;;participants_palette,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.related_tab,related_events,--div--;LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:tabs.workflow_tab,--palette--;;workflow_palette,approval_date,--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,--palette--;;hidden,--palette--;;access',
        ],
    ],
];

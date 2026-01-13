<?php

//
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns(
    'tx_ximatypo3calendar_domain_model_entry',
    [
        'calendar' => [
            'label' => 'Calendar',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_calendar',
            ],
        ],
    ]
);

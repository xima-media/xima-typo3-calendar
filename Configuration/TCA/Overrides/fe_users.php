<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns(
    'fe_users',
    [
        'events' => [
            'label' => 'Events',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_ximatypo3calendar_domain_model_event',
                'foreign_table' => 'tx_ximatypo3calendar_domain_model_event',
                'foreign_field' => 'owner',
            ],
        ],
    ]
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'events',
    '',
    ''
);

<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$ll = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:';

// Notification preferences for the event workflow. Also surfaced in the
// "User Settings" module (see ext_tables.php). Stored on be_users so the
// notification logic (DataHandlerHook) can query them.
ExtensionManagementUtility::addTCAcolumns('be_users', [
    'tx_ximatypo3calendar_notify_review' => [
        'label' => $ll . 'notifications.notify_review',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ],
    ],
    'tx_ximatypo3calendar_notify_live' => [
        'label' => $ll . 'notifications.notify_live',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ],
    ],
    'tx_ximatypo3calendar_notify_categories' => [
        'label' => $ll . 'notifications.notify_categories',
        'config' => [
            // manyToMany category renders as a native selectTree in the backend form.
            'type' => 'category',
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'be_users',
    implode(',', [
        '--div--;' . $ll . 'notifications.tab',
        'tx_ximatypo3calendar_notify_review',
        'tx_ximatypo3calendar_notify_live',
        'tx_ximatypo3calendar_notify_categories',
    ]),
);

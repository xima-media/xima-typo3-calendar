<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3Calendar\Backend\UserSettings\NotificationCategoryField;

defined('TYPO3') or die();

$ll = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:';

// Expose the event notification preferences in the "User Settings" module.
// Values are persisted to the be_users columns defined in TCA/Overrides/be_users.php.
$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3calendar_notify_review'] = [
    'type' => 'check',
    'label' => $ll . 'notifications.notify_review',
    'table' => 'be_users',
];
$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3calendar_notify_live'] = [
    'type' => 'check',
    'label' => $ll . 'notifications.notify_live',
    'table' => 'be_users',
];
// The User Settings module has no native category-tree field type, so the
// category restriction is rendered by a custom userFunc (a checkbox tree that
// syncs the selected UIDs into a hidden field for DataHandler).
$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3calendar_notify_categories'] = [
    'type' => 'user',
    'label' => $ll . 'notifications.notify_categories',
    'table' => 'be_users',
    'userFunc' => NotificationCategoryField::class . '->render',
];

ExtensionManagementUtility::addFieldsToUserSettings(
    implode(',', [
        '--div--;' . $ll . 'notifications.tab',
        'tx_ximatypo3calendar_notify_review',
        'tx_ximatypo3calendar_notify_live',
        'tx_ximatypo3calendar_notify_categories',
    ]),
);

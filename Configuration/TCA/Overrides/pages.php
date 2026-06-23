<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-events'] = 'apps-pagetree-folder-contains-events';
ExtensionManagementUtility::addTcaSelectItem(
    'pages',
    'module',
    [
        'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:folder.module.label',
        'value' => 'events',
        'icon' => 'apps-pagetree-folder-contains-events',
    ]
);

<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

$pluginKeys = ['event_appointment_list', 'event_appointment_detail', 'event_list', 'event_detail'];

foreach ($pluginKeys as $pluginKey) {
    ExtensionUtility::registerPlugin(
        'XimaTypo3Calendar',
        GeneralUtility::underscoredToUpperCamelCase($pluginKey),
        'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:plugin.' . $pluginKey . '.title',
        'tx-ximatypo3calendar-plugin-' . str_replace('_', '-', $pluginKey),
        'plugins',
        'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:plugin.' . $pluginKey . '.description',
    );
}

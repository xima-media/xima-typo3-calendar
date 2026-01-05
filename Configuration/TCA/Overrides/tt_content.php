<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Register Latest Events plugin
ExtensionUtility::registerPlugin(
    'XimaTypo3Calendar',
    'LatestEvents',
    'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:latestEvents.title',
    'EXT:xima_typo3_calendar/Resources/Public/Icons/plugin-event-listing.svg',
    'plugins',
    'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:latestEvents.description'
);

// Register List Events plugin
ExtensionUtility::registerPlugin(
    'XimaTypo3Calendar',
    'ListEvents',
    'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:listEvents.title',
    'EXT:xima_typo3_calendar/Resources/Public/Icons/plugin-event-listing.svg',
    'plugins',
    'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang.xlf:listEvents.description'
);

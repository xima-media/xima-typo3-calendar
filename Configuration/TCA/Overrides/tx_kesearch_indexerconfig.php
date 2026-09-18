<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3Calendar\Indexer\EventIndexerConfiguration;

defined('TYPO3') || die();

if (!ExtensionManagementUtility::isLoaded('ke_search')) {
    return;
}

// ke_search gates the two storage-selection fields on a whitelist of its own indexer types, so
// a custom type renders the form without any way to say where its records live.
foreach (['startingpoints_recursive', 'sysfolder'] as $storageField) {
    $displayCond = $GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns'][$storageField]['displayCond'] ?? null;
    if (is_string($displayCond) && str_starts_with($displayCond, 'FIELD:type:IN:')) {
        $GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns'][$storageField]['displayCond']
            = $displayCond . ',' . EventIndexerConfiguration::INDEXER_TYPE;
    }
}

ExtensionManagementUtility::addToAllTCAtypes(
    'tx_kesearch_indexerconfig',
    'ximatypo3calendar_index_past_events',
    '',
    'after:sysfolder'
);

$GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns']['ximatypo3calendar_index_past_events'] = [
    'label' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:indexer.event.indexPastEvents',
    'description' => 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:indexer.event.indexPastEvents.description',
    'displayCond' => 'FIELD:type:=:' . EventIndexerConfiguration::INDEXER_TYPE,
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 0,
    ],
];

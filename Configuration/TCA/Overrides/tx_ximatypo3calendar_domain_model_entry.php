<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
if (!$extensionConfiguration->get('xima_typo3_calendar', 'features/requirementsManagement')) {
    $GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_entry']['columns']['requirement_bookings']['config']['type'] = 'passthrough';
    $GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_entry']['columns']['notes']['config']['type'] = 'passthrough';
}

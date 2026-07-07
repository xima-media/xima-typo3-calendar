<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

try {
    $requirementsManagement = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)
        ->get('xima_typo3_calendar', 'features/requirementsManagement');
} catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
    // Before the extension configuration is written (e.g. a fresh install) the
    // lookup throws. TCA loads on every request, so swallow it and default the
    // feature to off instead of breaking the whole backend.
    $requirementsManagement = false;
}

if (!$requirementsManagement) {
    $GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_requirementbooking']['ctrl']['hideTable'] = true;
}

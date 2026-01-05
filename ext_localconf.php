<?php

defined('TYPO3') || die();

// Register plugins
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'XimaTypo3Calendar',
    'LatestEvents',
    [
        Xima\XimaTypo3Calendar\Controller\Frontend\EventController::class => 'latest',
    ],
    [],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'XimaTypo3Calendar',
    'ListEvents',
    [
        Xima\XimaTypo3Calendar\Controller\Frontend\EventController::class => 'list',
    ],
    [],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// Register T3API operation handler for event change requests
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3api']['operationHandlers'][
    \Xima\XimaTypo3Calendar\OperationHandler\EventChangeRequestOperationHandler::class
] = 600;

// Register event database query restriction
$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][
    Xima\XimaTypo3Calendar\Database\EventRestriction::class
] = [];

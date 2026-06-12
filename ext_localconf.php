<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Xima\XimaTypo3Calendar\Controller\EventAppointmentController;

defined('TYPO3') || die();

// Register event database query restriction
$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][Xima\XimaTypo3Calendar\Database\EventRestriction::class] = [];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['xima_typo3_calendar']
    = \Xima\XimaTypo3Calendar\Hooks\DataHandlerHook::class;

ExtensionUtility::configurePlugin(
    'XimaTypo3Calendar',
    'EventAppointmentList',
    [
        EventAppointmentController::class => 'list',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'XimaTypo3Calendar',
    'EventAppointmentDetail',
    [
        EventAppointmentController::class => 'show',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

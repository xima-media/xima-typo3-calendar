<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Xima\XimaTypo3Calendar\Controller\EventController;

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][Xima\XimaTypo3Calendar\Database\EventRestriction::class] = [];
$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][Xima\XimaTypo3Calendar\Database\EntryRestriction::class] = [];

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1783341481] = 'EXT:xima_typo3_calendar/Resources/Private/Templates/Email/';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['xima_typo3_calendar']
    = \Xima\XimaTypo3Calendar\Hooks\DataHandlerHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['xima_typo3_calendar_owner']
    = \Xima\XimaTypo3Calendar\Hooks\EventOwnerBackendUserHook::class;

// Render the edit form read-only for backend users who may not handle live
// events when the opened record is a live event or an appointment of one.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][\Xima\XimaTypo3Calendar\Backend\FormDataProvider\LiveEventReadOnlyForNonPublisher::class] = [
    'depends' => [
        \TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseEditRow::class,
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['xima_typo3_calendar2']
    = \Xima\XimaTypo3Calendar\Hooks\DataHandlerEventDispatcherHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['xima_typo3_calendar']
    = \Xima\XimaTypo3Calendar\Hooks\DataHandlerEventDispatcherHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['ximaTypo3Calendar.requirementsManagement'] ??= false;

ExtensionUtility::configurePlugin(
    'XimaTypo3Calendar',
    'EventList',
    [
        EventController::class => 'list',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'XimaTypo3Calendar',
    'EventDetail',
    [
        EventController::class => 'show',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

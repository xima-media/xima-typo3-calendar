<?php

defined('TYPO3') || die();

// Register event database query restriction
$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][Xima\XimaTypo3Calendar\Database\EventRestriction::class] = [];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['xima_typo3_calendar']
    = \Xima\XimaTypo3Calendar\Hooks\DataHandlerEventDispatcherHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['xima_typo3_calendar']
    = \Xima\XimaTypo3Calendar\Hooks\DataHandlerEventDispatcherHook::class;

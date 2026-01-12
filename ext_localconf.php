<?php

defined('TYPO3') || die();

// Register T3API operation handler for event change requests
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3api']['operationHandlers'][
    \Xima\XimaTypo3Calendar\OperationHandler\EventChangeRequestOperationHandler::class
] = 600;

// Register event database query restriction
$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][
    Xima\XimaTypo3Calendar\Database\EventRestriction::class
] = [];

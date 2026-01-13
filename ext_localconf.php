<?php

defined('TYPO3') || die();

// Register event database query restriction
$GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'][Xima\XimaTypo3Calendar\Database\EventRestriction::class] = [];

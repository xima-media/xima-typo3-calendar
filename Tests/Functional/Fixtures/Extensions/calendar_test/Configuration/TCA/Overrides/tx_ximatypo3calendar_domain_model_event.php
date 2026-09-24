<?php

defined('TYPO3') or die();

$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['columns']['record_type']['config']['items'][] = [
    'label' => 'Event without workflow',
    'value' => 'plain-event',
];

$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['types']['plain-event'] = [
    'showitem' => 'record_type,title',
];

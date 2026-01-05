<?php

$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['columns']['type']['config']['items'][] = [
    'label' => 'Draft',
    'value' => 'draft',
];

$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['ctrl']['typeicon_classes']['draft'] = 'content-clock';

$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['columns']['type']['config']['items'][] = [
    'label' => 'Change',
    'value' => 'change',
];

$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['types']['draft'] = $GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['types']['live'];
$GLOBALS['TCA']['tx_ximatypo3calendar_domain_model_event']['ctrl']['typeicon_classes']['change'] = 'actions-open';

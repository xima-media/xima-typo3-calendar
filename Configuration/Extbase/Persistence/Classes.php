<?php

declare(strict_types=1);

return [
    \Xima\XimaTypo3Calendar\Domain\Model\Api\Event::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_event',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\EventOrganizer::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_organizer',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\EventEntry::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_entry',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\EventLocation::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_location',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\EventSpeaker::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_speaker',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\FrontendUser::class => [
        'tableName' => 'fe_users',
    ],
];

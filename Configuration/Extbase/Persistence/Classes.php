<?php

declare(strict_types=1);

return [
    \Xima\XimaTypo3Calendar\Domain\Model\Api\Event::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_event',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\FileReference::class => [
        'tableName' => 'sys_file_reference',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\Organizer::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_organizer',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\CalendarEntry::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_entry',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\EventAppointment::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_entry',
        'recordType' => 'event-appointment',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\Location::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_location',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\Speaker::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_speaker',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\Calendar::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_calendar',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\Api\FrontendUser::class => [
        'tableName' => 'fe_users',
    ],
];

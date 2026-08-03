<?php

declare(strict_types=1);

return [
    \Xima\XimaTypo3Calendar\Domain\Model\EventAppointment::class => [
        'tableName' => 'tx_ximatypo3calendar_domain_model_entry',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\FrontendUser::class => [
        'tableName' => 'fe_users',
    ],
    \Xima\XimaTypo3Calendar\Domain\Model\SysCategory::class => [
        'tableName' => 'sys_category',
    ],
];

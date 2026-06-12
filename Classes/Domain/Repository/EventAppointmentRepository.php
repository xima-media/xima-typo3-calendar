<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;

class EventAppointmentRepository extends Repository
{
    protected $defaultOrderings = [
        'startDate' => 'ASC',
    ];
}

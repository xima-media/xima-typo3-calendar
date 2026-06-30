<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

enum ChangeType: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case HIDDEN = 'hidden';
    case DELETED = 'deleted';
    case REACTIVATED = 'reactivated';
    case LOCATION_CHANGED = 'location_changed';
    case DATE_RANGE_CHANGED = 'date_range_changed';
}

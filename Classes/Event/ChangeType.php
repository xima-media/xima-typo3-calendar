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

    /**
     * Pure lifecycle transitions carry no field diff; every other change type
     * must report at least one changed field to be dispatched.
     */
    public function allowsEmptyFields(): bool
    {
        return in_array($this, [self::HIDDEN, self::DELETED, self::REACTIVATED], true);
    }
}

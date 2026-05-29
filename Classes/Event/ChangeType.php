<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

final class ChangeType
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const HIDDEN = 'hidden';
    public const DELETED = 'deleted';
    public const REACTIVATED = 'reactivated';
    public const LOCATION_CHANGED = 'location_changed';
    public const DATE_RANGE_CHANGED = 'date_range_changed';

    private function __construct()
    {
    }
}

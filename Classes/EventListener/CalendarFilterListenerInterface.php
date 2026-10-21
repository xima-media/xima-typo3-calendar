<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use Xima\XimaTypo3Calendar\Event\ApplyCalendarFilterEvent;
use Xima\XimaTypo3Calendar\Event\BuildCalendarEntriesQueryEvent;
use Xima\XimaTypo3Calendar\Event\CalendarEntrySerializationEvent;
use Xima\XimaTypo3Calendar\Event\ConfigureCalendarFilterEvent;

interface CalendarFilterListenerInterface
{
    public function build(ConfigureCalendarFilterEvent $event): void;

    public function buildQuery(BuildCalendarEntriesQueryEvent $event): void;

    public function apply(ApplyCalendarFilterEvent $event): void;

    public function serialize(CalendarEntrySerializationEvent $event): void;
}

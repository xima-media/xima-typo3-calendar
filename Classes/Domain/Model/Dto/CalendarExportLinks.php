<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Dto;

/**
 * The export targets available for one event or appointment.
 *
 * The provider links are null whenever the export covers more than one appointment: Google
 * and Outlook compose exactly one entry, so there is no honest link for a multi-appointment
 * event. `ics` is null when the site has no event detail page configured.
 */
final readonly class CalendarExportLinks
{
    public function __construct(
        public ?string $ics = null,
        public ?string $google = null,
        public ?string $outlookPersonal = null,
        public ?string $outlookBusiness = null,
    ) {
    }

    public function hasAny(): bool
    {
        return $this->ics !== null
            || $this->google !== null
            || $this->outlookPersonal !== null
            || $this->outlookBusiness !== null;
    }
}

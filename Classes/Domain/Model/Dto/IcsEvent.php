<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Dto;

/**
 * A single VEVENT, already normalized.
 *
 * Everything that needs a domain model, a site or a timezone decision has happened before
 * this object exists, which is what keeps {@see \Xima\XimaTypo3Calendar\Serializer\IcsSerializer}
 * a pure formatter.
 */
final readonly class IcsEvent
{
    /**
     * @param \DateTimeImmutable $end Exclusive, as RFC 5545 defines DTEND
     * @param string[] $categories
     */
    public function __construct(
        public string $uid,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $allDay,
        public string $summary,
        public string $description = '',
        public string $location = '',
        public ?string $url = null,
        public bool $cancelled = false,
        public array $categories = [],
        public ?string $organizerEmail = null,
        public ?string $organizerName = null,
        public int $sequence = 0,
        public ?\DateTimeImmutable $lastModified = null,
    ) {
    }
}

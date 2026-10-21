<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Dto;

abstract class AbstractCalendarFilter implements \JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    abstract public function jsonSerialize(): array;

    /**
     * @return string
     */
    abstract public function getIdentifier(): string;
}

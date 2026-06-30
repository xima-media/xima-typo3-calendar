<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

abstract readonly class AbstractRecordChangedEvent
{
    /**
     * @param array<string, array{old: mixed, new: mixed}> $changedFields
     */
    public function __construct(
        public int $uid,
        public string $table,
        public ChangeType $changeType,
        public array $changedFields
    ) {
    }
}

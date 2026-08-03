<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

/**
 * Base class for the record change events dispatched by DataHandlerEventDispatcherHook.
 *
 * Dispatched synchronously inside the DataHandler run, which is what lets listeners read
 * request-scoped state such as StatusChangeContext. See Documentation/DataHandlerEvents.md.
 */
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

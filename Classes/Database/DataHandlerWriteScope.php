<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Database;

/**
 * Tracks whether DataHandler is processing a write in the current request.
 *
 * The event and entry restrictions decide what a *reader* may see. DataHandler re-reads the
 * records it is writing (e.g. when sorting inline children before it links them to their
 * parent), and a freshly inserted appointment has no parent yet, so a frontend reader would
 * never be allowed to see it. Who may write is checked before DataHandler runs; inside the
 * write the restrictions therefore stand aside.
 *
 * A counter rather than a flag, because DataHandler instances nest (hooks and listeners
 * start their own).
 */
final class DataHandlerWriteScope
{
    private int $depth = 0;

    public function enter(): void
    {
        $this->depth++;
    }

    public function leave(): void
    {
        $this->depth = max(0, $this->depth - 1);
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }
}

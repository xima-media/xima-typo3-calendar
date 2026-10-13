<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Xima\XimaTypo3Calendar\Database\DataHandlerWriteScope;

/**
 * Opens the DataHandler write scope for the duration of a datamap or cmdmap run.
 *
 * @see DataHandlerWriteScope
 */
#[Autoconfigure(public: true)]
class DataHandlerWriteScopeHook
{
    public function __construct(
        protected DataHandlerWriteScope $writeScope,
    ) {
    }

    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        $this->writeScope->enter();
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->writeScope->leave();
    }

    public function processCmdmap_beforeStart(DataHandler $dataHandler): void
    {
        $this->writeScope->enter();
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $this->writeScope->leave();
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use Throwable;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;

readonly class ModuleButtonProvider implements ButtonProviderInterface
{
    public function __construct(
        private UriBuilder $uriBuilder,
        private ExtensionConfiguration $extensionConfiguration,
        private string $routeIdentifier,
        private ?string $table,
        private string $title,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritDoc
     */
    public function getLink(): string
    {
        $parameters = [];
        if ($this->table) {
            $parameters['table'] = $this->table;
        }
        $recordPid = $this->extensionConfiguration
            ->get('xima_typo3_calendar', 'recordPid');
        if ($recordPid !== null) {
            $parameters['id'] = $recordPid;
        }

        try {
            return (string)$this->uriBuilder->buildUriFromRoute($this->routeIdentifier, $parameters);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @inheritDoc
     */
    public function getTarget(): string
    {
        return '';
    }
}

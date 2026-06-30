<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets\Provider;

use Throwable;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;

readonly class ModuleButtonProvider implements ButtonProviderInterface
{
    public function __construct(
        private UriBuilder $uriBuilder,
        private ConnectionPool $connectionPool,
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
        $recordPid = $this->getRecordPid();
        if ($recordPid !== 0) {
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

    private function getRecordPid(): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $recordPid = $qb->select('uid')
            ->from('pages')
            ->where($qb->expr()->eq('module', $qb->createNamedParameter('events')))
            ->executeQuery()
            ->fetchOne();

        return $recordPid ?: 0;
    }
}

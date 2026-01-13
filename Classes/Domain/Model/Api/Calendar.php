<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/calendars",
 *         }
 *     },
 *     itemOperations={
 *         "get": {
 *             "path": "/calendars/{id}",
 *         }
 *     }
 * )
 */
class Calendar extends AbstractEntity
{
    protected string $title = '';

    protected string $description = '';

    /**
     * @var ObjectStorage<CalendarEntry>|null
     */
    #[Lazy]
    #[Cascade(['remove'])]
    protected ?ObjectStorage $entries = null;

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->entries = new ObjectStorage();
    }

    public function getEntries(): ?ObjectStorage
    {
        return $this->entries;
    }

    public function setEntries(?ObjectStorage $entries): void
    {
        $this->entries = $entries;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}

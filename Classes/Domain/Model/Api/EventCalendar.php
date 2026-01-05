<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class EventCalendar extends AbstractEntity
{
    /**
     * @T3api\Serializer\Groups({"api_get_dkfz_event", "api_post_dkfz_event"})
     */
    protected string $title = '';

    /**
     * @T3api\Serializer\Groups({"api_get_dkfz_event", "api_post_dkfz_event"})
     */
    protected string $description = '';

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

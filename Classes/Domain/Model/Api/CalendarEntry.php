<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class CalendarEntry extends AbstractEntity
{
    /**
     * @var Calendar|null
     * @T3api\Serializer\MaxDepth(0)
     */
    #[Validate(['validator' => 'NotEmpty'])]
    protected ?Calendar $calendar = null;

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $title = '';

    /**
     * @var \DateTime|null
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    #[Validate(['validator' => 'DateTime'])]
    #[Validate(['validator' => 'NotEmpty'])]
    protected ?\DateTime $startDate = null;

    /**
     * @var \DateTime|null
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected ?\DateTime $endDate = null;

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected bool $allDay = false;

    protected string $recordType = '';

    public function getCalendar(): ?Calendar
    {
        return $this->calendar;
    }

    public function setCalendar(?Calendar $calendar): void
    {
        $this->calendar = $calendar;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): void
    {
        $this->allDay = $allDay;
    }

    public function getRecordType(): string
    {
        return $this->recordType;
    }

    public function setRecordType(string $recordType): void
    {
        $this->recordType = $recordType;
    }
}

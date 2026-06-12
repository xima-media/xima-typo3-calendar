<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model;

use DateTime;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventAppointmentType;

class EventAppointment extends AbstractEntity
{
    protected ?Event $event = null;

    protected string $title = '';

    protected string $description = '';

    protected ?DateTime $startDate = null;

    protected ?DateTime $endDate = null;

    protected bool $allDay = false;

    protected bool $canceled = false;

    protected ?EventAppointmentType $type = null;

    protected string $onlineLink = '';

    protected ?Location $location = null;

    protected string $address = '';

    protected string $directions = '';

    protected string $speakers = '';

    protected string $ticketLink = '';

    /**
     * @var ObjectStorage<RequirementBooking>|null
     */
    protected ?ObjectStorage $requirementBookings = null;

    protected ?FrontendUser $contact = null;

    protected ?DateTime $doorsOpen = null;

    protected string $notes = '';

    protected int $maxParticipants = 0;

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->requirementBookings = new ObjectStorage();
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): void
    {
        $this->event = $event;
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

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTime $endDate): void
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

    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function setCanceled(bool $canceled): void
    {
        $this->canceled = $canceled;
    }

    public function getType(): EventAppointmentType
    {
        return $this->type;
    }

    public function setType(EventAppointmentType $type): void
    {
        $this->type = $type;
    }

    public function getOnlineLink(): string
    {
        return $this->onlineLink;
    }

    public function setOnlineLink(string $onlineLink): void
    {
        $this->onlineLink = $onlineLink;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getDirections(): string
    {
        return $this->directions;
    }

    public function setDirections(string $directions): void
    {
        $this->directions = $directions;
    }

    public function getSpeakers(): string
    {
        return $this->speakers;
    }

    public function setSpeakers(string $speakers): void
    {
        $this->speakers = $speakers;
    }

    public function getTicketLink(): string
    {
        return $this->ticketLink;
    }

    public function setTicketLink(string $ticketLink): void
    {
        $this->ticketLink = $ticketLink;
    }

    public function getRequirementBookings(): ?ObjectStorage
    {
        return $this->requirementBookings;
    }

    public function setRequirementBookings(?ObjectStorage $requirementBookings): void
    {
        $this->requirementBookings = $requirementBookings;
    }

    public function getContact(): ?FrontendUser
    {
        return $this->contact;
    }

    public function setContact(?FrontendUser $contact): void
    {
        $this->contact = $contact;
    }

    public function getDoorsOpen(): ?DateTime
    {
        return $this->doorsOpen;
    }

    public function setDoorsOpen(?DateTime $doorsOpen): void
    {
        $this->doorsOpen = $doorsOpen;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): void
    {
        $this->notes = $notes;
    }

    public function getMaxParticipants(): int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(int $maxParticipants): void
    {
        $this->maxParticipants = $maxParticipants;
    }
}

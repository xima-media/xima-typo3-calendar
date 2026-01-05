<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class EventEntry extends AbstractEntity
{
    /**
     * @var EventCalendar|null
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    #[Validate(['validator' => 'NotEmpty'])]
    protected ?EventCalendar $calendar = null;

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

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $description = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $type = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $onlineLink = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $directions = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected bool $canceled = false;

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected bool $requiresRegistration = false;

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $registrationLink = '';

    /**
     * @var \DateTime|null
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected ?\DateTime $registrationDeadline = null;

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $fee = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $address = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $additionalInformation = '';

    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected string $url = '';

    /**
     * @var EventLocation|null
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     */
    protected ?EventLocation $location = null;

    /**
     * @var ObjectStorage<EventSpeaker>
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     * @Cascade("remove")
     * @Lazy
     */
    protected ObjectStorage $speakers;

    /**
     * @var ObjectStorage<FrontendUser>
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     * @Lazy
     */
    protected ObjectStorage $hosts;

    /**
     * @var ObjectStorage<FileReference>
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event"})
     * @Cascade("remove")
     * @Lazy
     */
    protected ObjectStorage $files;

    public function __construct()
    {
        $this->speakers = new ObjectStorage();
        $this->hosts = new ObjectStorage();
        $this->files = new ObjectStorage();
    }

    // Getters and Setters

    public function getCalendar(): ?EventCalendar
    {
        return $this->calendar;
    }

    public function setCalendar(?EventCalendar $calendar): void
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
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

    public function getDirections(): string
    {
        return $this->directions;
    }

    public function setDirections(string $directions): void
    {
        $this->directions = $directions;
    }

    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function setCanceled(bool $canceled): void
    {
        $this->canceled = $canceled;
    }

    public function isRequiresRegistration(): bool
    {
        return $this->requiresRegistration;
    }

    public function setRequiresRegistration(bool $requiresRegistration): void
    {
        $this->requiresRegistration = $requiresRegistration;
    }

    public function getRegistrationLink(): string
    {
        return $this->registrationLink;
    }

    public function setRegistrationLink(string $registrationLink): void
    {
        $this->registrationLink = $registrationLink;
    }

    public function getRegistrationDeadline(): ?\DateTime
    {
        return $this->registrationDeadline;
    }

    public function setRegistrationDeadline(?\DateTime $registrationDeadline): void
    {
        $this->registrationDeadline = $registrationDeadline;
    }

    public function getFee(): string
    {
        return $this->fee;
    }

    public function setFee(string $fee): void
    {
        $this->fee = $fee;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getAdditionalInformation(): string
    {
        return $this->additionalInformation;
    }

    public function setAdditionalInformation(string $additionalInformation): void
    {
        $this->additionalInformation = $additionalInformation;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getLocation(): ?EventLocation
    {
        return $this->location;
    }

    public function setLocation(?EventLocation $location): void
    {
        $this->location = $location;
    }

    /**
     * @return ObjectStorage<EventSpeaker>
     */
    public function getSpeakers(): ObjectStorage
    {
        return $this->speakers;
    }

    /**
     * @param ObjectStorage<EventSpeaker> $speakers
     */
    public function setSpeakers(ObjectStorage $speakers): void
    {
        $this->speakers = $speakers;
    }

    public function addSpeaker(EventSpeaker $speaker): void
    {
        $this->speakers->attach($speaker);
    }

    public function removeSpeaker(EventSpeaker $speaker): void
    {
        $this->speakers->detach($speaker);
    }

    /**
     * @return ObjectStorage<FrontendUser>
     */
    public function getHosts(): ObjectStorage
    {
        return $this->hosts;
    }

    /**
     * @param ObjectStorage<FrontendUser> $hosts
     */
    public function setHosts(ObjectStorage $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function addHost(FrontendUser $host): void
    {
        $this->hosts->attach($host);
    }

    public function removeHost(FrontendUser $host): void
    {
        $this->hosts->detach($host);
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getFiles(): ObjectStorage
    {
        return $this->files;
    }

    /**
     * @param ObjectStorage<FileReference> $files
     */
    public function setFiles(ObjectStorage $files): void
    {
        $this->files = $files;
    }

    public function addFile(FileReference $file): void
    {
        $this->files->attach($file);
    }

    public function removeFile(FileReference $file): void
    {
        $this->files->detach($file);
    }
}

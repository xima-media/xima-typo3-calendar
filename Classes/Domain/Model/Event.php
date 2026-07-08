<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventLanguage;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

class Event extends AbstractEntity
{
    protected string $title = '';

    protected string $description = '';

    protected string $statusMessage = '';

    protected ?EventLanguage $language = null;

    protected string $additionalInformation = '';

    protected string $url = '';

    protected ?FileReference $previewImage = null;

    /**
     * @var ObjectStorage<FileReference>|null
     */
    protected ?ObjectStorage $files = null;

    protected bool $publishToWebsite = false;

    protected bool $publishToFeed = false;

    protected ?\DateTime $publishDate = null;

    /**
     * @var ObjectStorage<EventAppointment>|null
     */
    protected ?ObjectStorage $appointments = null;

    protected ?FrontendUser $organizer = null;

    /**
     * @var ObjectStorage<FrontendUser>|null
     */
    protected ?ObjectStorage $hosts = null;

    /**
     * @var ObjectStorage<SysCategory>|null
     */
    protected ?ObjectStorage $categories = null;

    protected bool $requiresRegistration = false;

    protected ?FrontendUser $owner = null;

    protected ?EventStatus $status = null;

    protected ?\DateTime $approvalDate = null;

    protected ?Location $location = null;

    protected string $registrationLink = '';

    protected ?\DateTime $registrationDeadline = null;

    protected string $fee = '';

    protected string $registrationAddress = '';

    /**
     * @var ObjectStorage<Event>|null
     */
    protected ?ObjectStorage $relatedEvents = null;

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->files = new ObjectStorage();
        $this->appointments = new ObjectStorage();
        $this->hosts = new ObjectStorage();
        $this->categories = new ObjectStorage();
        $this->relatedEvents = new ObjectStorage();
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

    public function getRegistrationAddress(): string
    {
        return $this->registrationAddress;
    }

    public function setRegistrationAddress(string $registrationAddress): void
    {
        $this->registrationAddress = $registrationAddress;
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

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(string $statusMessage): void
    {
        $this->statusMessage = $statusMessage;
    }

    public function getLanguage(): ?EventLanguage
    {
        return $this->language;
    }

    public function setLanguage(?EventLanguage $language): void
    {
        $this->language = $language;
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

    public function getPreviewImage(): ?FileReference
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?FileReference $previewImage): void
    {
        $this->previewImage = $previewImage;
    }

    public function getFiles(): ?ObjectStorage
    {
        return $this->files;
    }

    public function setFiles(?ObjectStorage $files): void
    {
        $this->files = $files;
    }

    public function isPublishToWebsite(): bool
    {
        return $this->publishToWebsite;
    }

    public function setPublishToWebsite(bool $publishToWebsite): void
    {
        $this->publishToWebsite = $publishToWebsite;
    }

    public function isPublishToFeed(): bool
    {
        return $this->publishToFeed;
    }

    public function setPublishToFeed(bool $publishToFeed): void
    {
        $this->publishToFeed = $publishToFeed;
    }

    public function getPublishDate(): ?\DateTime
    {
        return $this->publishDate;
    }

    public function setPublishDate(?\DateTime $publishDate): void
    {
        $this->publishDate = $publishDate;
    }

    public function getAppointments(): ?ObjectStorage
    {
        return $this->appointments;
    }

    public function setAppointments(?ObjectStorage $appointments): void
    {
        $this->appointments = $appointments;
    }

    public function getOrganizer(): ?FrontendUser
    {
        return $this->organizer;
    }

    public function setOrganizer(?FrontendUser $organizer): void
    {
        $this->organizer = $organizer;
    }

    public function getHosts(): ?ObjectStorage
    {
        return $this->hosts;
    }

    public function setHosts(?ObjectStorage $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function getCategories(): ?ObjectStorage
    {
        return $this->categories;
    }

    public function setCategories(?ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    public function isRequiresRegistration(): bool
    {
        return $this->requiresRegistration;
    }

    public function setRequiresRegistration(bool $requiresRegistration): void
    {
        $this->requiresRegistration = $requiresRegistration;
    }

    public function getOwner(): ?FrontendUser
    {
        return $this->owner;
    }

    public function setOwner(?FrontendUser $owner): void
    {
        $this->owner = $owner;
    }

    public function getStatus(): ?EventStatus
    {
        return $this->status;
    }

    public function setStatus(?EventStatus $status): void
    {
        $this->status = $status;
    }

    public function getApprovalDate(): ?\DateTime
    {
        return $this->approvalDate;
    }

    public function setApprovalDate(?\DateTime $approvalDate): void
    {
        $this->approvalDate = $approvalDate;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    public function getRelatedEvents(): ?ObjectStorage
    {
        return $this->relatedEvents;
    }

    public function setRelatedEvents(?ObjectStorage $relatedEvents): void
    {
        $this->relatedEvents = $relatedEvents;
    }
}

<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use DateTime;
use SourceBroker\T3api\Annotation as T3api;
use SourceBroker\T3api\Annotation\Serializer\VirtualProperty;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/events",
 *         },
 *         "post_new": {
 *             "path": "/events",
 *             "method": "POST",
 *             "security": "frontend.user.isLoggedIn",
 *             "normalizationContext": {
 *                 "groups": {"api_post_xima_event"}
 *             },
 *         },
 *     },
 *     itemOperations={
 *         "get": {
 *             "path": "/events/{id}",
 *         },
 *         "patch_event_update": {
 *             "method": "PATCH",
 *             "path": "/events/{id}",
 *             "security": "object.getOwner() && object.getOwner().getUid() == currentUserId",
 *             "normalizationContext": {
 *                 "groups": {"api_patch_xima_event_update"}
 *             },
 *         },
 *         "delete": {
 *             "method": "DELETE",
 *             "path": "/events/{id}",
 *             "security": "object.getOwner() && object.getOwner().getUid() == currentUserId",
 *         },
 *     },
 *     attributes={
 *         "upload": {
 *             "folder": "1:/user_upload/event-images/",
 *             "allowedFileExtensions": {"jpg", "jpeg", "png"},
 *             "conflictMode": DuplicationBehavior::RENAME,
 *         }
 *     },
 * )
 */
class Event extends AbstractEntity
{
    /**
     * @T3api\Serializer\Type\CurrentFeUser(FrontendUser::class)
     * @T3api\Serializer\Groups({"api_post_xima_event"})
     * @T3api\Serializer\MaxDepth(0)
     */
    protected ?FrontendUser $owner = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $title = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $language = EventLanguage::ALL->value;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $description = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $additionalInformation = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $url = '';

    /**
     * @var ObjectStorage<Category>|null
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $categories = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected bool $publishToWebsite = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected bool $publishToFeed = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected ?DateTime $publishDate = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected bool $requiresRegistration = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $registrationLink = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected ?DateTime $registrationDeadline = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $fee = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $registrationAddress = '';

    /**
     * Submit a sys_file uid as `{"previewImage": {"uidLocal": 123}}`.
     * Remove the image with `{"previewImage": 0}`.
     *
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    #[Cascade(['remove'])]
    protected ?FileReference $previewImage = null;

    /**
     * @var ObjectStorage<FileReference>|null
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    #[Cascade(['remove'])]
    protected ?ObjectStorage $files = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     * /
     */
    protected ?Organizer $organizer = null;

    /**
     * @var ObjectStorage<FrontendUser>|null
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $hosts = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     * @var ?ObjectStorage<EventAppointment>
     */
    #[Cascade(['remove'])]
    protected ?ObjectStorage $appointments = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_review"})
     */
    protected int $status = EventStatus::DRAFT->value;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected ?Location $location = null;

    /**
     * @var ObjectStorage<Category>|null
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $eventTypes = null;

    /**
     * @var ObjectStorage<Category>|null
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $targetGroups = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected int $maxParticipants = 0;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected int $numberOfPersonsGroup = 0;

    /**
     * @var ObjectStorage<Event>|null
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $relatedEvents = null;

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->appointments = new ObjectStorage();
        $this->categories = new ObjectStorage();
        $this->files = new ObjectStorage();
        $this->hosts = new ObjectStorage();
        $this->eventTypes = new ObjectStorage();
        $this->targetGroups = new ObjectStorage();
        $this->relatedEvents = new ObjectStorage();
    }

    /**
     * @return ObjectStorage<EventAppointment>|null
     */
    public function getAppointments(): ?ObjectStorage
    {
        return $this->appointments;
    }

    /**
     * @param ObjectStorage<EventAppointment>|null $appointments
     */
    public function setAppointments(?ObjectStorage $appointments): void
    {
        $this->appointments = $appointments ?? new ObjectStorage();
    }

    public function getOrganizer(): ?Organizer
    {
        return $this->organizer;
    }

    public function setOrganizer(?Organizer $organizer): void
    {
        $this->organizer = $organizer;
    }

    /**
     * @return ObjectStorage<FrontendUser>|null
     */
    public function getHosts(): ?ObjectStorage
    {
        return $this->hosts;
    }

    /**
     * @param ObjectStorage<FrontendUser>|null $hosts
     */
    public function setHosts(?ObjectStorage $hosts): void
    {
        $this->hosts = $hosts ?? new ObjectStorage();
    }

    public function getOwner(): ?FrontendUser
    {
        return $this->owner;
    }

    public function setOwner(?FrontendUser $owner): void
    {
        $this->owner = $owner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getAdditionalInformation(): string
    {
        return $this->additionalInformation;
    }

    public function setAdditionalInformation(string $additionalInformation): void
    {
        $this->additionalInformation = $additionalInformation;
    }

    /**
     * @VirtualProperty("externalUrl")
     */
    public function getExternalUrl(): string
    {
        return $this->url;
    }

    /**
     * @VirtualProperty("url")
     */
    public function getUrl(): string
    {
        $requestUri = $GLOBALS['TYPO3_REQUEST']->getUri();

        return $requestUri->getScheme() . '://' . $requestUri->getAuthority()
            . '/aktuelles/veranstaltungen/event/' . $this->getUid() . '-' . $this->getFakeSlug();
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    private function getFakeSlug(): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->getTitle()), '-'));
    }

    /**
     * @return ObjectStorage<Category>|null
     */
    public function getCategories(): ?ObjectStorage
    {
        return $this->categories;
    }

    /**
     * @param ObjectStorage<Category>|null $categories
     */
    public function setCategories(?ObjectStorage $categories): void
    {
        $this->categories = $categories ?? new ObjectStorage();
    }

    public function addCategory(Category $category): void
    {
        if ($this->categories === null) {
            $this->categories = new ObjectStorage();
        }
        $this->categories->attach($category);
    }

    public function removeCategory(Category $category): void
    {
        $this->categories?->detach($category);
    }

    public function getPublishToWebsite(): bool
    {
        return $this->publishToWebsite;
    }

    public function setPublishToWebsite(bool $publishToWebsite): void
    {
        $this->publishToWebsite = $publishToWebsite;
    }

    public function getPublishToFeed(): bool
    {
        return $this->publishToFeed;
    }

    public function setPublishToFeed(bool $publishToFeed): void
    {
        $this->publishToFeed = $publishToFeed;
    }

    public function getPublishDate(): ?DateTime
    {
        return $this->publishDate;
    }

    public function setPublishDate(?DateTime $publishDate): void
    {
        $this->publishDate = $publishDate;
    }

    public function getPreviewImage(): ?FileReference
    {
        $previewImage = $this->previewImage;

        // if ($previewImage === null) {
        //     return null;
        // }

        // try {
        //     $previewImage->getOriginalResource();
        // } catch (\Throwable) {
        //     return null;
        // }

        return $previewImage;
    }

    public function setPreviewImage(?FileReference $previewImage): void
    {
        $this->previewImage = $previewImage;
    }

    /**
     * @return ObjectStorage<FileReference>|null
     */
    public function getFiles(): ?ObjectStorage
    {
        return $this->files;
    }

    /**
     * @param ObjectStorage<FileReference>|null $files
     */
    public function setFiles(?ObjectStorage $files): void
    {
        $this->files = $files ?? new ObjectStorage();
    }

    public function addDocument(FileReference $document): void
    {
        if ($this->files === null) {
            $this->files = new ObjectStorage();
        }
        $this->files->attach($document);
    }

    public function removeDocument(FileReference $document): void
    {
        $this->files?->detach($document);
    }

    public function getRequiresRegistration(): bool
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

    public function getRegistrationDeadline(): ?DateTime
    {
        return $this->registrationDeadline;
    }

    public function setRegistrationDeadline(?DateTime $registrationDeadline): void
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

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    /**
     * @return ObjectStorage<Category>|null
     */
    public function getEventTypes(): ?ObjectStorage
    {
        return $this->eventTypes;
    }

    /**
     * @param ObjectStorage<Category>|null $eventTypes
     */
    public function setEventTypes(?ObjectStorage $eventTypes): void
    {
        $this->eventTypes = $eventTypes ?? new ObjectStorage();
    }

    public function addEventType(Category $eventType): void
    {
        if ($this->eventTypes === null) {
            $this->eventTypes = new ObjectStorage();
        }
        $this->eventTypes->attach($eventType);
    }

    public function removeEventType(Category $eventType): void
    {
        $this->eventTypes?->detach($eventType);
    }

    /**
     * @return ObjectStorage<Category>|null
     */
    public function getTargetGroups(): ?ObjectStorage
    {
        return $this->targetGroups;
    }

    /**
     * @param ObjectStorage<Category>|null $targetGroups
     */
    public function setTargetGroups(?ObjectStorage $targetGroups): void
    {
        $this->targetGroups = $targetGroups;
    }

    public function addTargetGroup(Category $targetGroup): void
    {
        if ($this->targetGroups === null) {
            $this->targetGroups = new ObjectStorage();
        }
        $this->targetGroups->attach($targetGroup);
    }

    public function removeTargetGroup(Category $targetGroup): void
    {
        $this->targetGroups?->detach($targetGroup);
    }

    public function getMaxParticipants(): int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(int $maxParticipants): void
    {
        $this->maxParticipants = $maxParticipants;
    }

    public function getNumberOfPersonsGroup(): int
    {
        return $this->numberOfPersonsGroup;
    }

    public function setNumberOfPersonsGroup(int $numberOfPersonsGroup): void
    {
        $this->numberOfPersonsGroup = $numberOfPersonsGroup;
    }

    /**
     * @return ObjectStorage<Event>|null
     */
    public function getRelatedEvents(): ?ObjectStorage
    {
        return $this->relatedEvents;
    }

    /**
     * @param ObjectStorage<Event>|null $relatedEvents
     */
    public function setRelatedEvents(?ObjectStorage $relatedEvents): void
    {
        $this->relatedEvents = $relatedEvents;
    }

    public function addRelatedEvent(self $event): void
    {
        if ($this->relatedEvents === null) {
            $this->relatedEvents = new ObjectStorage();
        }
        $this->relatedEvents->attach($event);
    }

    public function removeRelatedEvent(self $event): void
    {
        $this->relatedEvents?->detach($event);
    }
}

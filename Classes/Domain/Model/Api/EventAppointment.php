<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use DateTime;
use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/appointments",
 *         },
 *         "post_new": {
 *             "path": "/appointments",
 *             "method": "POST",
 *             "security": "frontend.user.isLoggedIn",
 *             "normalizationContext": {
 *                 "groups": {"api_post_xima_appointment"}
 *             },
 *         },
 *     },
 *     itemOperations={
 *         "get": {
 *             "path": "/appointments/{id}",
 *         },
 *         "patch_appointment_update": {
 *             "method": "PATCH",
 *             "path": "/appointments/{id}",
 *             "security": "object.getEvent().getOwner() && object.getEvent().getOwner().getUid() == currentUserId",
 *             "normalizationContext": {
 *                 "groups": {"api_patch_xima_appointment_update"}
 *             },
 *         },
 *         "delete": {
 *             "method": "DELETE",
 *             "path": "/appointments/{id}",
 *             "security": "object.getEvent().getOwner() && object.getEvent().getOwner().getUid() == currentUserId",
 *         },
 *     },
 *     attributes={
 *         "upload": {
 *             "folder": "1:/user_upload/event-images/",
 *             "allowedFileExtensions": {"jpg", "jpeg", "png"},
 *             "conflictMode": DuplicationBehavior::RENAME,
 *         },
 *         "persistence": {
 *             "storagePid": "1478"
 *         }
 *     },
 * )
 */
class EventAppointment extends CalendarEntry
{
    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $description = '';

    /**
     * @T3api\Serializer\MaxDepth(0)
     */
    protected ?Event $event = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected bool $canceled = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $type = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?Location $location = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $address = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $onlineLink = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $directions = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected bool $requiresRegistration = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
      */
     protected string $registrationLink = '';

     /**
      * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
      */
     protected ?DateTime $registrationDeadline = null;

     /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $fee = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $url = '';

    /**
     * @var ObjectStorage<Speaker>|null
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $speakers = null;

    /**
     * @var ObjectStorage<FrontendUser>|null
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $hosts = null;

    /**
     * @var ObjectStorage<FileReference>|null
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment", "api_patch_xima_event_update", "api_post_xima_event"})
     * @T3api\ORM\Cascade("persist")
     */
    #[Cascade(['remove'])]
    protected ?ObjectStorage $files = null;

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->speakers = new ObjectStorage();
        $this->hosts = new ObjectStorage();
        $this->files = new ObjectStorage();
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): void
    {
        $this->event = $event;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function setCanceled(bool $canceled): void
    {
        $this->canceled = $canceled;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return ObjectStorage<Speaker>|null
     */
    public function getSpeakers(): ?ObjectStorage
    {
        return $this->speakers;
    }

    /**
     * @param ObjectStorage<Speaker>|null $speakers
     */
    public function setSpeakers(?ObjectStorage $speakers): void
    {
        $this->speakers = $speakers ?? new ObjectStorage();
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
}

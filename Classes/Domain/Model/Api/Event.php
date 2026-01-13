<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use DateTime;
use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
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
 *         },
 *         "persistence": {
 *             "storagePid": "1424"
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

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->appointments = new ObjectStorage();
        $this->categories = new ObjectStorage();
        $this->files = new ObjectStorage();
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
        $this->appointments = $appointments;
    }

    public function getOrganizer(): ?Organizer
    {
        return $this->organizer;
    }

    public function setOrganizer(?Organizer $organizer): void
    {
        $this->organizer = $organizer;
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
        $this->categories = $categories;
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
        return $this->previewImage;
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
        $this->files = $files;
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
}

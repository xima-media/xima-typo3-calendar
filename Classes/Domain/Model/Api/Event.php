<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use DateTime;
use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
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
 *                 "groups": {"api_post_dkfz_event"}
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
 *                 "groups": {"api_patch_dkfz_event_update"}
 *             },
 *         },
 *         "change_request": {
 *             "method": "POST",
 *             "path": "/events/{id}/change",
 *             "security": "object.getOwner() && object.getOwner().getUid() == currentUserId",
 *             "normalizationContext": {
 *                 "groups": {"api_patch_dkfz_event_update"}
 *             },
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
     * @T3api\Serializer\Groups({"api_post_dkfz_event"})
     */
    protected ?FrontendUser $owner = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     */
    protected string $title = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     */
    protected string $language = '';

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     */
    protected string $description = '';

    /**
     * @var ObjectStorage<Category>|null
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     * @T3api\ORM\Cascade("persist")
     */
    #[Lazy]
    protected ?ObjectStorage $category = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     */
    protected bool $publishToWebsite = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     */
    protected bool $publishToFeed = false;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     */
    protected ?DateTime $publishDate = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     * @T3api\ORM\Cascade("persist")
     */
    #[Lazy]
    #[Cascade(['remove'])]
    protected ?FileReference $previewImage = null;

    /**
     * @var ObjectStorage<FileReference>|null
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     * @T3api\ORM\Cascade("persist")
     */
    #[Lazy]
    #[Cascade(['remove'])]
    protected ?ObjectStorage $documents = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     * @T3api\ORM\Cascade("persist")
     * /
     */
    protected ?EventOrganizer $organizer = null;

    protected ?Event $draftEvent = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update", "api_post_dkfz_event"})
     * @T3api\ORM\Cascade("persist")
     * @var ?ObjectStorage<EventEntry>
     */
    protected ?ObjectStorage $appointments = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_review"})
     */
    protected int $status = DraftStatus::DRAFT->value;

    protected string $type = 'draft';

    /**
     * @T3api\Serializer\Groups({"api_post_dkfz_event_change"})
     * @T3api\ORM\Cascade("persist")
     */
    protected ?Event $targetEvent = null;

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
        $this->appointments = new ObjectStorage();
        $this->category = new ObjectStorage();
        $this->documents = new ObjectStorage();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getTargetEvent(): ?Event
    {
        return $this->targetEvent;
    }

    public function setTargetEvent(?Event $targetEvent): void
    {
        $this->targetEvent = $targetEvent;
    }

    /**
     * @return ObjectStorage<EventEntry>|null
     */
    public function getAppointments(): ?ObjectStorage
    {
        return $this->appointments;
    }

    /**
     * @param ObjectStorage<EventEntry>|null $appointments
     */
    public function setAppointments(?ObjectStorage $appointments): void
    {
        $this->appointments = $appointments;
    }

    public function getDraftEvent(): ?Event
    {
        return $this->draftEvent;
    }

    public function setDraftEvent(?Event $draftEvent): void
    {
        $this->draftEvent = $draftEvent;
    }

    public function getOrganizer(): ?EventOrganizer
    {
        return $this->organizer;
    }

    public function setOrganizer(?EventOrganizer $organizer): void
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
    public function getCategory(): ?ObjectStorage
    {
        return $this->category;
    }

    /**
     * @param ObjectStorage<Category>|null $category
     */
    public function setCategory(?ObjectStorage $category): void
    {
        $this->category = $category;
    }

    public function addCategory(Category $category): void
    {
        if ($this->category === null) {
            $this->category = new ObjectStorage();
        }
        $this->category->attach($category);
    }

    public function removeCategory(Category $category): void
    {
        $this->category?->detach($category);
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
    public function getDocuments(): ?ObjectStorage
    {
        return $this->documents;
    }

    /**
     * @param ObjectStorage<FileReference>|null $documents
     */
    public function setDocuments(?ObjectStorage $documents): void
    {
        $this->documents = $documents;
    }

    public function addDocument(FileReference $document): void
    {
        if ($this->documents === null) {
            $this->documents = new ObjectStorage();
        }
        $this->documents->attach($document);
    }

    public function removeDocument(FileReference $document): void
    {
        $this->documents?->detach($document);
    }
}

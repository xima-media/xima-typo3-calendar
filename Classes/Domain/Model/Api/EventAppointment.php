<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;

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
 *             "security": "object.getOwner() && object.getOwner().getUid() == currentUserId",
 *             "normalizationContext": {
 *                 "groups": {"api_patch_xima_appointment_update"}
 *             },
 *         },
 *         "change_request": {
 *             "method": "POST",
 *             "path": "/appointments/{id}/change",
 *             "security": "object.getOwner() && object.getOwner().getUid() == currentUserId",
 *             "normalizationContext": {
 *                 "groups": {"api_patch_xima_appointment_update"}
 *             },
 *         },
 *         "delete": {
 *             "method": "DELETE",
 *             "path": "/appointments/{id}",
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
class EventAppointment extends AbstractCalendarEntry
{
    /**
     * @T3api\Serializer\Groups({"api_patch_xima_appointment_update", "api_post_xima_appointment"})
     */
    protected string $description = '';

    public function __construct()
    {
        $this->initializeObject();
    }

    private function initializeObject(): void
    {
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

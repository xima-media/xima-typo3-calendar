<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/event-organizers",
 *         }
 *     },
 *     attributes={
 *         "pagination_client_enabled": true,
 *         "pagination_items_per_page": 50,
 *         "maximum_items_per_page": 50,
 *     },
 * )
 */
class EventOrganizer extends AbstractEntity
{
    /**
     * @T3api\Serializer\Groups({"api_patch_dkfz_event_update"})
     */
    protected string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}

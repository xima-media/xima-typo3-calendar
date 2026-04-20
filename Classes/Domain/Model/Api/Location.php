<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/locations",
 *             "normalizationContext": {
 *                 "groups": {"api_get_xima_location"}
 *             },
 *         }
 *     },
 *     attributes={
 *         "pagination_client_enabled": true,
 *         "pagination_items_per_page": 100,
 *         "maximum_items_per_page": 100
 *     },
 * )
 */
class Location extends AbstractEntity
{
    /**
     * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event", "api_get_xima_location"})
     */
    protected string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

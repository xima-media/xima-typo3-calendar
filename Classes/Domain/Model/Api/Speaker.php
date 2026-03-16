<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/speakers",
 *             "normalizationContext": {
 *                 "groups": {"api_get_xima_speaker"}
 *             },
 *         }
 *     },
 *     attributes={
 *         "pagination_client_enabled": true,
 *         "pagination_items_per_page": 100,
 *         "maximum_items_per_page": 100,
 *         "persistence": {
 *             "storagePid": "1478"
 *         }
 *     },
 * )
 */
class Speaker extends AbstractEntity
{
    /**
        * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event", "api_get_xima_speaker"})
     */
    protected string $title = '';

    /**
        * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event", "api_get_xima_speaker"})
     */
    protected string $firstName = '';

    /**
        * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event", "api_get_xima_speaker"})
     */
    protected string $lastName = '';

    /**
        * @T3api\Serializer\Groups({"api_get_xima_event", "api_post_xima_event", "api_get_xima_speaker"})
     */
    protected string $department = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
    }

    /**
     * Helper method to get full name
     */
    public function getFullName(): string
    {
        $parts = array_filter([
            $this->title,
            $this->firstName,
            $this->lastName,
        ]);

        return implode(' ', $parts);
    }
}

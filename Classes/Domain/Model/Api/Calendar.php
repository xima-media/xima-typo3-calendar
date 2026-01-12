<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *         "get": {
 *             "path": "/calendars",
 *             "normalizationContext": {
 *                 "groups": {"api_fwfwef"}
 *             },
 *         }
 *     },
 *     itemOperations={
 *         "get": {
 *             "path": "/calendars/{id}",
 *             "normalizationContext": {
 *                 "groups": {"api_fwfwef"}
 *             },
 *         }
 *     }
 * )
 */
class Calendar extends AbstractEntity
{
    protected string $title = '';

    protected string $description = '';

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
}

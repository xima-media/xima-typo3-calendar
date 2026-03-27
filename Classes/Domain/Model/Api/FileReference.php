<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Extbase\Domain\Model\FileReference as ExtbaseFileReference;

final class FileReference extends ExtbaseFileReference
{
    protected ?int $uidForeign = null;

    /**
     * @T3api\Serializer\Groups({"api_patch_xima_event_update", "api_post_xima_event"})
     */
    protected string $crop = '';

    public function setUidLocal(?int $uidLocal): void
    {
        $this->uidLocal = $uidLocal;
    }

    public function getUidLocal(): ?int
    {
        return $this->uidLocal;
    }

    public function getUidForeign(): ?int
    {
        return $this->uidForeign;
    }

    public function setUidForeign(?int $uidForeign): void
    {
        $this->uidForeign = $uidForeign;
    }

    public function getCrop(): string
    {
        return $this->crop;
    }

    public function setCrop(string $crop): void
    {
        $this->crop = $crop;
    }
}
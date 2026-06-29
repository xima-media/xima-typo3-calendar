<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class SysCategory extends AbstractEntity
{
    protected string $title = '';

    protected ?SysCategory $parent = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }
}

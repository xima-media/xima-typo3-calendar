<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Location extends AbstractEntity
{
    protected string $name = '';

    protected ?Location $parent = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParent(): ?Location
    {
        return $this->parent;
    }

    public function setParent(?Location $parent): void
    {
        $this->parent = $parent;
    }
}

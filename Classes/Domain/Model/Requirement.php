<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Requirement extends AbstractEntity
{
    protected string $title = '';
    protected string $description = '';
    protected ?FrontendUser $assignee = null;

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

    public function getAssignee(): ?FrontendUser
    {
        return $this->assignee;
    }

    public function setAssignee(?FrontendUser $assignee): void
    {
        $this->assignee = $assignee;
    }
}

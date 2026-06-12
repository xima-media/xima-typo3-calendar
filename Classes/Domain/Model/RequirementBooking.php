<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class RequirementBooking extends AbstractEntity
{
    protected ?EventAppointment $eventAppointment = null;

    protected ?Requirement $requirement = null;

    protected int $amount = 0;

    protected string $note = '';

    protected ?\DateTime $startDate = null;

    protected ?\DateTime $endDate = null;

    public function getEventAppointment(): ?EventAppointment
    {
        return $this->eventAppointment;
    }

    public function setEventAppointment(?EventAppointment $eventAppointment): void
    {
        $this->eventAppointment = $eventAppointment;
    }

    public function getRequirement(): ?Requirement
    {
        return $this->requirement;
    }

    public function setRequirement(?Requirement $requirement): void
    {
        $this->requirement = $requirement;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Event;

use TYPO3\CMS\Extbase\Mvc\RequestInterface;

/**
 * Extension point for the event detail view.
 *
 * Dispatched by `EventController::showAction()` before the view is rendered. The assigned
 * values are re-read after dispatch, so a listener may add its own variables — a breadcrumb,
 * a page title, related records — or replace `event` and `appointment` outright.
 */
class ModifyEventDetailViewEvent
{
    /**
     * @param array<string, mixed> $assignedValues
     */
    public function __construct(
        private array $assignedValues,
        private readonly RequestInterface $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssignedValues(): array
    {
        return $this->assignedValues;
    }

    /**
     * @param array<string, mixed> $assignedValues
     */
    public function setAssignedValues(array $assignedValues): void
    {
        $this->assignedValues = $assignedValues;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}

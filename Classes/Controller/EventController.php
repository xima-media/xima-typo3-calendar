<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;

class EventController extends ActionController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $this->view->assign('events', $this->eventRepository->findAll());

        return $this->htmlResponse();
    }

    public function showAction(Event $event): ResponseInterface
    {
        $this->view->assign('event', $event);

        return $this->htmlResponse();
    }
}

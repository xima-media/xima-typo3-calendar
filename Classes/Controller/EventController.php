<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
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

    /**
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    public function showAction(?Event $event = null, ?EventAppointment $appointment = null): ResponseInterface
    {
        if (!$event && $appointment) {
            $event = $appointment->getEvent();
        }

        if (!$event) {
            $message = 'No event found!';
            $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $this->request,
                $message
            );
            throw new ImmediateResponseException($response, 1783512532);
        }

        $this->view->assign('event', $event);
        $this->view->assign('eventAppointment', $appointment);

        return $this->htmlResponse();
    }
}

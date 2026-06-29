<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Domain\Repository\EventAppointmentRepository;

class EventAppointmentController extends ActionController
{
    public function __construct(
        private readonly EventAppointmentRepository $eventAppointmentRepository,
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $this->view->assign('appointments', $this->eventAppointmentRepository->findAll());

        return $this->htmlResponse();
    }

    public function showAction(EventAppointment $appointment = null): ResponseInterface
    {
        $this->view->assign('appointment', $appointment);

        return $this->htmlResponse();
    }
}

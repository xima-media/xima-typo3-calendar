<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;
use Xima\XimaTypo3Calendar\Event\ModifyEventDetailViewEvent;
use Xima\XimaTypo3Calendar\Service\CalendarExportService;

class EventController extends ActionController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CalendarExportService $calendarExportService,
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
        $event = $this->resolveEvent($event, $appointment);

        $viewEvent = new ModifyEventDetailViewEvent(
            [
                'event' => $event,
                'appointment' => $appointment,
                // The series URL carries no appointment, but date, attendance mode and location
                // only exist per appointment.
                'nextAppointment' => $appointment ?? $event->getNextAppointment(),
            ],
            $this->request,
        );
        $this->eventDispatcher->dispatch($viewEvent);

        $this->view->assignMultiple($viewEvent->getAssignedValues());

        return $this->htmlResponse();
    }

    /**
     * Serves the event — or a single appointment of it — as an iCalendar download.
     *
     * The action runs inside the detail page, so the response has to leave through
     * {@see PropagateResponseException}: a plugin's response body is otherwise embedded into
     * the page and its headers are lost.
     *
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     * @throws PropagateResponseException
     */
    public function icsAction(?Event $event = null, ?EventAppointment $appointment = null): ResponseInterface
    {
        $event = $this->resolveEvent($event, $appointment);

        $ics = $appointment !== null
            ? $this->calendarExportService->icsForAppointment($appointment)
            : $this->calendarExportService->icsForEvent($event);

        $filename = $this->calendarExportService->buildFilename($event, $appointment);

        $response = (new Response())
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->getBody()->write($ics);

        throw new PropagateResponseException($response, 1787900001);
    }

    /**
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    private function resolveEvent(?Event $event, ?EventAppointment $appointment): Event
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

        return $event;
    }
}

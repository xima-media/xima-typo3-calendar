<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3Calendar\Service\CalendarEventCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPendingCreationService;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\CalendarStoragePidResolver;

final class CalendarEventCreationController
{
    private const CREATION_TYPES = ['event', 'event-appointment'];

    public function __construct(
        private readonly CalendarStoragePidResolver $storagePidResolver,
        private readonly CalendarPermissionService $permissionService,
        private readonly CalendarSelectionService $calendarSelectionService,
        private readonly CalendarEventCreationService $eventCreationService,
        private readonly CalendarPendingCreationService $pendingCreationService,
    ) {
    }

    public function createEventAction(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->invalidEventDataResponse();
        }

        $start = (int)($data['start'] ?? 0);
        $end = (int)($data['end'] ?? 0);
        $allDay = (int)($data['allDay'] ?? 0) === 1;
        $creationType = (string)($data['type'] ?? 'event-appointment');
        $calendarUid = isset($data['calendarUid']) ? (int)$data['calendarUid'] : null;
        $calendarUid = $calendarUid > 0 ? $calendarUid : null;
        $pid = $this->storagePidResolver->resolveStoragePid();

        if (!in_array($creationType, self::CREATION_TYPES, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid creation type.'], 400);
        }

        if ($pid <= 0 || $start <= 0 || $end <= $start) {
            return $this->invalidEventDataResponse();
        }

        $hasPermission = $creationType === 'event'
            ? $this->permissionService->canCreateEventAndAppointmentAtPid($pid)
            : $this->permissionService->canCreateAppointmentAtPid($pid);
        if (!$hasPermission) {
            return new JsonResponse(['success' => false, 'message' => 'No permission to create events.'], 403);
        }

        $calendars = $this->calendarSelectionService->getAvailableCalendars();
        if (($calendars !== [] && $calendarUid === null)
            || ($calendarUid !== null && !$this->calendarSelectionService->isAvailable($calendarUid))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid calendar.'], 400);
        }

        $result = $creationType === 'event'
            ? $this->eventCreationService->create($pid, $start, $end, $allDay, $calendarUid)
            : $this->eventCreationService->createAppointment($pid, $start, $end, $allDay, $calendarUid);
        return new JsonResponse($result, $result['success'] ? 200 : 500);
    }

    public function cleanupEventAction(ServerRequestInterface $request): ResponseInterface
    {
        $eventUid = (int)($request->getQueryParams()['eventUid'] ?? 0);
        $entryUid = (int)($request->getQueryParams()['entryUid'] ?? 0);
        if (($eventUid <= 0 && $entryUid <= 0) || ($eventUid > 0 && $entryUid > 0)) {
            return new JsonResponse(['success' => false], 400);
        }

        $pid = $this->storagePidResolver->resolveStoragePid();
        $hasPermission = $eventUid > 0
            ? $this->permissionService->canCreateEventAndAppointmentAtPid($pid)
            : $this->permissionService->canCreateAppointmentAtPid($pid);
        if (!$hasPermission) {
            return new JsonResponse(['success' => false], 403);
        }

        $success = $eventUid > 0
            ? $this->pendingCreationService->cleanupEvent($eventUid, $pid)
            : $this->pendingCreationService->cleanupEntry($entryUid, $pid);

        return new JsonResponse(['success' => $success]);
    }

    private function invalidEventDataResponse(): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => 'Invalid event data.'], 400);
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3Calendar\Service\CalendarSelectionService;
use Xima\XimaTypo3Calendar\Service\EventRecordTypeService;
use Xima\XimaTypo3Calendar\Service\EventSearchService;

/**
 * Turns a selection in the calendar module into the URL of the native new-record form.
 *
 * Nothing is written here. The selected range reaches the form as `defVals`, and the
 * records only exist once the editor saves. NewEventAppointmentFromDefaultValues adds
 * the prefilled appointment to a new event.
 */
final class CalendarEventCreationController
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';
    private const CREATION_TYPES = ['event', 'event-appointment'];

    public function __construct(
        private readonly CalendarSelectionService $calendarSelectionService,
        private readonly EventRecordTypeService $recordTypeService,
        private readonly EventSearchService $eventSearchService,
        private readonly UriBuilder $uriBuilder,
    ) {
    }

    public function newRecordAction(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->invalidEventDataResponse();
        }

        $start = (int)($data['start'] ?? 0);
        $end = (int)($data['end'] ?? 0);
        $allDay = (int)($data['allDay'] ?? 0) === 1;
        $creationType = (string)($data['type'] ?? 'event-appointment');
        $recordType = isset($data['recordType']) && (is_string($data['recordType']) || is_int($data['recordType']))
            ? trim((string)$data['recordType'])
            : null;
        $calendarUid = (int)($data['calendarUid'] ?? 0);
        $eventUid = (int)($data['eventUid'] ?? 0);

        if (!in_array($creationType, self::CREATION_TYPES, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid creation type.'], 400);
        }

        if ($start <= 0 || $end <= $start) {
            return $this->invalidEventDataResponse();
        }

        // Records are stored next to their calendar. Only calendars on pages where the
        // user may create appointments are available, so that check is already done here.
        $calendar = $this->calendarSelectionService->findAvailableCalendar($calendarUid);
        if ($calendar === null) {
            return new JsonResponse(['success' => false, 'message' => $this->translate('creation.calendarNotAvailable')], 403);
        }
        if ($creationType === 'event' && !$calendar['canCreateEvent']) {
            return new JsonResponse(['success' => false, 'message' => $this->translate('creation.noPermission')], 403);
        }
        $pid = $calendar['pid'];

        if ($creationType === 'event'
            && $recordType !== null
            && !$this->recordTypeService->isAvailable($request, $pid, $recordType)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid record type.'], 400);
        }

        $appointmentDefaults = [
            'record_type' => 'event-appointment',
            'start_date' => $start,
            'end_date' => $end,
            'all_day' => $allDay ? 1 : 0,
            'calendar' => $calendar['uid'],
        ];
        $this->calendarSelectionService->rememberCalendar($calendar['uid']);

        if ($creationType === 'event-appointment') {
            if ($eventUid > 0) {
                if (!$this->eventSearchService->isSelectable($eventUid)) {
                    return new JsonResponse(['success' => false, 'message' => 'Invalid event.'], 400);
                }
                $appointmentDefaults['event'] = $eventUid;
            }

            return $this->newRecordResponse(self::ENTRY_TABLE, $pid, [self::ENTRY_TABLE => $appointmentDefaults]);
        }

        // Without a chosen record type, FormEngine applies the TCA and TSconfig defaults.
        $defaultValues = [self::ENTRY_TABLE => $appointmentDefaults];
        if ($recordType !== null) {
            $defaultValues[self::EVENT_TABLE] = ['record_type' => $recordType];
        }

        return $this->newRecordResponse(self::EVENT_TABLE, $pid, $defaultValues);
    }

    /**
     * @param array<string, array<string, int|string>> $defaultValues
     */
    private function newRecordResponse(string $table, int $pid, array $defaultValues): JsonResponse
    {
        $url = $this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [$table => [$pid => 'new']],
            'defVals' => $defaultValues,
            'returnUrl' => (string)$this->uriBuilder->buildUriFromRoute('calendar_calendar'),
        ]);

        return new JsonResponse(['success' => true, 'url' => (string)$url]);
    }

    private function invalidEventDataResponse(): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => 'Invalid event data.'], 400);
    }

    private function translate(string $key): string
    {
        return $GLOBALS['LANG']->sL('LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf:' . $key);
    }
}

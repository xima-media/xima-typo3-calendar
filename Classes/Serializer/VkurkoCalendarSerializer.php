<?php

namespace Xima\XimaTypo3Calendar\Serializer;

class VkurkoCalendarSerializer
{
    public static function serializeBackendEntries(array $backendEntries): array
    {
        $events = array_map(function (array $row): array {
            $start = ($row['start_date'] && $row['start_date'] !== '0000-00-00 00:00:00')
                ? (new \DateTime('@' . $row['start_date']))->format('Y-m-d\TH:i:s')
                : null;
            $end = ($row['end_date'] && $row['end_date'] !== '0000-00-00 00:00:00')
                ? (new \DateTime('@' . $row['end_date']))->format('Y-m-d\TH:i:s')
                : null;

            // If end date is missing and it's not an all-day event, assume a default duration of 30 minutes
            if ($end === null && $start && !$row['all_day']) {
                $end = new \DateTime($start);
                $end->modify('+30 minutes');
                $end = $end->format('Y-m-d\TH:i:s');
            }

            $title = $row['title'] ?? null;
            if ($title && ($row['event_title'] ?? null)) {
                $title .= ' (' . $row['event_title'] . ')';
            } elseif (!$title && ($row['event_title'] ?? null)) {
                $title = $row['event_title'];
            }

            return [
                'id' => 'entry-' . $row['uid'],
                'title' => $title,
                'start' => $start,
                'end' => $end,
                'allDay' => (bool)$row['all_day'],
                'extendedProps' => [
                    'calendarUid' => (int)$row['calendar'],
                    'calendarTitle' => $row['calendar_title'] ?? '',
                    'recordType' => $row['record_type'] ?? '',
                    'eventUid' => $row['event_uid'] ?? null,
                    'eventTitle' => $row['event_title'] ?? null,
                    'eventDescription' => $row['event_description'] ?? null,
                    'eventCategoryTitle' => $row['event_category_title'] ?? null,
                    'eventCategoryId' => isset($row['event_category_id']) ? (int)$row['event_category_id'] : null,
                    'eventStatus' => isset($row['event_status']) ? (int)$row['event_status'] : null,
                    'eventLanguage' => $row['event_language'] ?? null,
                    'eventOwner' => $row['event_owner'] ?? null,
                    'appointmentDescription' => $row['description'] ?? null,
                    'appointmentLocation' => $row['location_name'] ?? null,
                    'appointmentCanceled' => (bool)($row['canceled'] ?? false),
                    'appointmentSpeakers' => $row['speakers'] ?? null,
                ],
            ];
        }, $backendEntries);

        return $events;
    }
}

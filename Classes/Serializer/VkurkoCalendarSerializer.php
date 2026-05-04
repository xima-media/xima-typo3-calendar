<?php

namespace Xima\XimaTypo3Calendar\Serializer;

class VkurkoCalendarSerializer
{
    public static function serializeBackendEntries(array $backendEntries): array
    {
        $events = array_map(function (array $row): array {
            $start = ($row['start_date'] && $row['start_date'] !== '0000-00-00 00:00:00')
                ? (new \DateTime($row['start_date']))->format('Y-m-d\TH:i:s')
                : null;
            $end = ($row['end_date'] && $row['end_date'] !== '0000-00-00 00:00:00')
                ? (new \DateTime($row['end_date']))->format('Y-m-d\TH:i:s')
                : null;

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
                ],
            ];
        }, $backendEntries);

        return $events;
    }
}

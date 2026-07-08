<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Serializer;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VkurkoCalendarSerializer
{
    /**
     * Runtime cache of the resolved site per record pid.
     *
     * @var array<int, Site|null>
     */
    private static array $siteCache = [];

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
                    'url' => self::buildEventSingleUrl($row),
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

    /**
     * Build the frontend URL of the event single view for the given record.
     *
     * The single view page is configured per site via the
     * `xima_typo3_calendar.pages.eventSinglePid` site setting. The `event` argument
     * (and cHash) are handled by the CalendarPlugin route enhancer / page router.
     */
    private static function buildEventSingleUrl(array $row): ?string
    {
        $pid = (int)($row['pid'] ?? 0);
        $eventUid = (int)($row['event_uid'] ?? 0);
        if ($pid === 0 || $eventUid === 0) {
            return null;
        }

        $site = self::resolveSite($pid);
        if (!$site instanceof Site) {
            return null;
        }

        $singlePid = (int)$site->getSettings()->get('xima_typo3_calendar.pages.eventSinglePid');
        if ($singlePid === 0) {
            return null;
        }

        return (string)$site->getRouter()->generateUri(
            $singlePid,
            [
                'tx_ximatypo3calendar_eventdetail' => [
                    'controller' => 'Event',
                    'action' => 'show',
                    'event' => $eventUid,
                    'appointment' => (int)$row['uid'],
                ],
            ]
        );
    }

    /**
     * Resolve — and runtime-cache — the site the given page id belongs to.
     */
    private static function resolveSite(int $pid): ?Site
    {
        if (array_key_exists($pid, self::$siteCache)) {
            return self::$siteCache[$pid];
        }

        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pid);
        } catch (SiteNotFoundException) {
            $site = null;
        }

        return self::$siteCache[$pid] = $site;
    }
}

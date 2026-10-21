<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Serializer;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Event\CalendarEntrySerializationEvent;
use Xima\XimaTypo3Calendar\Utility\AppointmentDateUtility;

/**
 * Converts calendar entry rows into event objects for the vkurko/calendar JS component.
 *
 * The output shape is that library's event contract, not an internal format (see
 * Documentation/RestApi.md#calendar-feed).
 */
class VkurkoCalendarSerializer
{
    /**
     * Runtime cache of the resolved site per record pid.
     *
     * @var array<int, Site|null>
     */
    private static array $siteCache = [];

    public static function serializeBackendEntries(
        array $backendEntries,
        int $languageId = 0,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): array {
        // The event contract carries no offset, so the consumer reads the string as local
        // time. '@<timestamp>' yields a UTC DateTime, which would shift every time by the
        // installation's offset — convert before formatting.
        $timeZone = new \DateTimeZone(date_default_timezone_get());

        $events = array_map(function (array $row) use ($timeZone, $languageId, $eventDispatcher): array {
            $startDate = AppointmentDateUtility::getStartFromRow($row);
            $endDate = AppointmentDateUtility::getEndFromRow($row);
            $start = $startDate?->setTimezone($timeZone)->format('Y-m-d\TH:i:s');
            $end = $endDate?->setTimezone($timeZone)->format('Y-m-d\TH:i:s');

            // Event Calendar needs an end date for timed events without one.
            if ($end === null && $start) {
                $endDate = new \DateTime($start);
                $endDate->modify('+' . AppointmentDateUtility::DEFAULT_DURATION_MINUTES . ' minutes');
                $end = $endDate->format('Y-m-d\TH:i:s');
            }

            $title = $row['title'] ?? null;
            if ($title && ($row['event_title'] ?? null)) {
                $title .= ' (' . $row['event_title'] . ')';
            } elseif (!$title && ($row['event_title'] ?? null)) {
                $title = $row['event_title'];
            }

            $eventData = [
                'id' => 'entry-' . $row['uid'],
                'title' => $title,
                'start' => $start,
                'end' => $end,
                'allDay' => (bool)$row['all_day'],
                'extendedProps' => [
                    'url' => self::buildEventSingleUrl($row, $languageId),
                    'calendarUid' => (int)$row['calendar'],
                    'calendarTitle' => $row['calendar_title'] ?? '',
                    'recordType' => is_string($row['record_type'] ?? null) ? $row['record_type'] : '',
                    'eventCategoryId' => self::toNullableInt($row['event_category_id'] ?? null),
                    'eventCategoryTitle' => $row['event_category_title'] ?? null,
                    'eventStatus' => self::toNullableInt($row['event_status'] ?? null),
                    'filterValues' => [
                        'categories' => self::toNullableInt($row['event_category_id'] ?? null),
                    ],
                    'eventUid' => $row['event_uid'] ?? null,
                    'eventTitle' => $row['event_title'] ?? null,
                    'eventDescription' => $row['event_description'] ?? null,
                    'eventLanguage' => $row['event_language'] ?? null,
                    'eventOwner' => $row['event_owner'] ?? null,
                    'appointmentDescription' => $row['description'] ?? null,
                    'appointmentLocation' => $row['location_name'] ?? null,
                    'appointmentCanceled' => (bool)($row['canceled'] ?? false),
                    'appointmentSpeakers' => $row['speakers'] ?? null,
                ],
            ];

            $serializationEvent = new CalendarEntrySerializationEvent(
                $eventData,
                $row,
            );

            $eventDispatcher?->dispatch($serializationEvent);

            return $serializationEvent->getEventData();
        }, $backendEntries);

        return $events;
    }

    private static function toNullableInt(mixed $value): ?int
    {
        return is_int($value) || is_numeric($value) ? (int)$value : null;
    }

    /**
     * Build the frontend URL of the event single view for the given record.
     *
     * The single view page is configured per site via the
     * `xima_typo3_calendar.eventShowPid` site setting. The `event` argument (and cHash) are
     * handled by the CalendarEventDetailPlugin route enhancer / page router.
     */
    private static function buildEventSingleUrl(array $row, int $languageId): ?string
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

        $singlePid = (int)$site->getSettings()->get('xima_typo3_calendar.eventShowPid');
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
                '_language' => $languageId,
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

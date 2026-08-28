<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CalendarExportLinks;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\IcsEvent;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;
use Xima\XimaTypo3Calendar\Serializer\IcsSerializer;
use Xima\XimaTypo3Calendar\Utility\AppointmentDateUtility;
use Xima\XimaTypo3Calendar\Utility\PlainTextUtility;

/**
 * Turns events and appointments into calendar exports.
 *
 * This is the extension's half of the export: the ICS bytes, the URL that serves them, and
 * the deep links into the hosted calendars. Buttons, dialogs and labels are the consuming
 * project's business — the same appointment is rendered by a Fluid template in one project
 * and by a JS component fed from an API in the next.
 *
 * The provider links live here rather than in the frontend because they consume the very
 * dates the ICS consumes. Deriving the exclusive all-day end a second time in another
 * language is where the two representations of one appointment drift apart.
 */
#[Autoconfigure(public: true)]
class CalendarExportService
{
    private const GOOGLE_BASE = 'https://calendar.google.com/calendar/render';
    private const OUTLOOK_PERSONAL_BASE = 'https://outlook.live.com';
    private const OUTLOOK_BUSINESS_BASE = 'https://outlook.office.com';

    /**
     * Provider links travel in a URL, so the description cannot travel in full.
     */
    private const PROVIDER_DESCRIPTION_LIMIT = 1000;

    private const PLUGIN_NAMESPACE = 'tx_ximatypo3calendar_eventdetail';

    /**
     * @var array<int, Site|null>
     */
    private array $siteCache = [];

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly EntryRepository $entryRepository,
        private readonly EventRepository $eventRepository,
    ) {
    }

    /**
     * One VEVENT per appointment of the event.
     */
    public function icsForEvent(Event $event): string
    {
        $appointments = $this->collectAppointments($event);
        $timestamps = $this->resolveTimestamps($appointments, $event);
        $icsEvents = [];

        foreach ($appointments as $appointment) {
            $icsEvent = $this->buildIcsEvent($appointment, $event, $timestamps[$appointment->getUid()] ?? 0);
            if ($icsEvent !== null) {
                $icsEvents[] = $icsEvent;
            }
        }

        return IcsSerializer::serialize($icsEvents);
    }

    public function icsForAppointment(EventAppointment $appointment): string
    {
        $event = $appointment->getEvent();
        $timestamps = $this->resolveTimestamps([$appointment], $event);

        $icsEvent = $this->buildIcsEvent($appointment, $event, $timestamps[$appointment->getUid()] ?? 0);

        return IcsSerializer::serialize($icsEvent === null ? [] : [$icsEvent]);
    }

    /**
     * Export targets for a whole event.
     *
     * An event with exactly one appointment still gets provider links; beyond that they are
     * omitted, because Google and Outlook each compose a single entry.
     */
    public function linksForEvent(Event $event): CalendarExportLinks
    {
        $appointments = $this->collectAppointments($event);

        if (count($appointments) === 1) {
            return $this->linksForAppointment($appointments[0]);
        }

        return new CalendarExportLinks($this->buildIcsUrl($event, null));
    }

    public function linksForAppointment(EventAppointment $appointment): CalendarExportLinks
    {
        $event = $appointment->getEvent();
        // The provider links carry no revision, so the record timestamp is not worth a query.
        $icsEvent = $this->buildIcsEvent($appointment, $event, 0);

        if ($icsEvent === null) {
            return new CalendarExportLinks();
        }

        return new CalendarExportLinks(
            $this->buildIcsUrl($event, $appointment),
            $this->buildGoogleUrl($icsEvent),
            $this->buildOutlookUrl($icsEvent, self::OUTLOOK_PERSONAL_BASE),
            $this->buildOutlookUrl($icsEvent, self::OUTLOOK_BUSINESS_BASE),
        );
    }

    /**
     * Download filename for the given export, including the extension.
     */
    public function buildFilename(?Event $event, ?EventAppointment $appointment = null): string
    {
        $title = $appointment?->getTitle() ?: $event?->getTitle() ?? '';
        $slug = $this->slugify($title);

        return ($slug === '' ? 'event' : $slug) . '.ics';
    }

    private function buildIcsEvent(EventAppointment $appointment, ?Event $event, int $tstamp): ?IcsEvent
    {
        $start = AppointmentDateUtility::getStart($appointment);
        $end = AppointmentDateUtility::getEnd($appointment);
        if ($start === null || $end === null) {
            return null;
        }

        $summary = $appointment->getTitle() ?: $event?->getTitle() ?? '';
        if ($summary === '') {
            return null;
        }

        return new IcsEvent(
            uid: $this->buildUid($appointment),
            start: $start,
            end: $end,
            allDay: $appointment->isAllDay(),
            summary: $summary,
            description: $this->resolveDescription($appointment, $event),
            location: $this->resolveLocation($appointment, $event),
            url: $this->buildDetailUrl($event, $appointment),
            cancelled: $appointment->isCanceled(),
            categories: $this->resolveCategories($event),
            organizerEmail: $event?->getOrganizer()?->getEmail() ?: null,
            organizerName: $this->resolveOrganizerName($event),
            sequence: $tstamp,
            lastModified: $tstamp > 0
                ? new \DateTimeImmutable('@' . $tstamp)
                : null,
        );
    }

    /**
     * Last-write timestamps of the given appointments, keyed by uid.
     *
     * The event's own timestamp is folded in: renaming an event changes what the export says
     * without ever touching an appointment row, and a client only accepts an update whose
     * SEQUENCE has grown.
     *
     * @param EventAppointment[] $appointments
     * @return array<int, int>
     */
    private function resolveTimestamps(array $appointments, ?Event $event): array
    {
        $uids = [];
        foreach ($appointments as $appointment) {
            $uid = $appointment->getUid();
            if ($uid !== null) {
                $uids[] = $uid;
            }
        }

        $timestamps = $this->entryRepository->getTimestampsByUids($uids);

        $eventUid = $event?->getUid();
        $eventTimestamp = $eventUid === null
            ? 0
            : (int)($this->eventRepository->getEventRecordByUid($eventUid)['tstamp'] ?? 0);

        foreach ($timestamps as $uid => $timestamp) {
            $timestamps[$uid] = max($timestamp, $eventTimestamp);
        }

        return $timestamps;
    }

    /**
     * A UID has to stay the same across downloads, or a client files every download as a new
     * entry instead of updating the one it already has.
     */
    private function buildUid(EventAppointment $appointment): string
    {
        $site = $this->resolveSite($appointment->getPid());
        $host = $site?->getBase()->getHost();

        return 'entry-' . $appointment->getUid() . '@' . ($host !== null && $host !== '' ? $host : 'xima-typo3-calendar');
    }

    private function resolveDescription(EventAppointment $appointment, ?Event $event): string
    {
        foreach ([$appointment->getDescription(), $event?->getDescription(), $event?->getAdditionalInformation()] as $candidate) {
            $text = PlainTextUtility::fromHtml((string)$candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function resolveLocation(EventAppointment $appointment, ?Event $event): string
    {
        $parts = array_filter([
            $appointment->getLocation()?->getName() ?: $event?->getLocation()?->getName(),
            $appointment->getAddress(),
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(', ', array_map(static fn (string $part): string => trim(PlainTextUtility::fromHtml($part)), $parts));
    }

    /**
     * @return string[]
     */
    private function resolveCategories(?Event $event): array
    {
        $categories = [];

        foreach ($event?->getCategories() ?? [] as $category) {
            $title = $category->getTitle();
            if ($title !== '') {
                $categories[] = $title;
            }
        }

        return $categories;
    }

    private function resolveOrganizerName(?Event $event): ?string
    {
        $organizer = $event?->getOrganizer();
        if ($organizer === null) {
            return null;
        }

        $name = trim($organizer->getFirstName() . ' ' . $organizer->getLastName());

        return $name !== '' ? $name : ($organizer->getUsername() ?: null);
    }

    /**
     * @return EventAppointment[]
     */
    private function collectAppointments(Event $event): array
    {
        $appointments = [];

        foreach ($event->getAppointments() ?? [] as $appointment) {
            $appointments[] = $appointment;
        }

        return $appointments;
    }

    private function buildIcsUrl(?Event $event, ?EventAppointment $appointment): ?string
    {
        return $this->buildPluginUrl('ics', $event, $appointment);
    }

    private function buildDetailUrl(?Event $event, ?EventAppointment $appointment): ?string
    {
        return $this->buildPluginUrl('show', $event, $appointment);
    }

    /**
     * Builds an absolute frontend URL of the event detail plugin.
     *
     * The detail page is configured per site via the `xima_typo3_calendar.eventShowPid` site
     * setting; without it the extension cannot know where the plugin lives.
     */
    private function buildPluginUrl(string $action, ?Event $event, ?EventAppointment $appointment): ?string
    {
        $event ??= $appointment?->getEvent();
        if ($event === null) {
            return null;
        }

        $site = $this->resolveSite($event->getPid());
        if (!$site instanceof Site) {
            return null;
        }

        $detailPid = (int)$site->getSettings()->get('xima_typo3_calendar.eventShowPid');
        if ($detailPid === 0) {
            return null;
        }

        $arguments = [
            'controller' => 'Event',
            'action' => $action,
            'event' => $event->getUid(),
        ];

        if ($appointment !== null) {
            $arguments['appointment'] = $appointment->getUid();
        }

        return (string)$site->getRouter()->generateUri($detailPid, [self::PLUGIN_NAMESPACE => $arguments]);
    }

    private function buildGoogleUrl(IcsEvent $icsEvent): string
    {
        $format = $icsEvent->allDay ? 'Ymd' : 'Ymd\THis\Z';
        $start = $this->formatForProvider($icsEvent->start, $icsEvent->allDay, $format);
        $end = $this->formatForProvider($icsEvent->end, $icsEvent->allDay, $format);

        return self::GOOGLE_BASE . '?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $icsEvent->summary,
            'dates' => $start . '/' . $end,
            'details' => $this->providerDescription($icsEvent),
            'location' => $icsEvent->location,
        ]);
    }

    private function buildOutlookUrl(IcsEvent $icsEvent, string $baseUrl): string
    {
        $parameters = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $icsEvent->summary,
            'body' => $this->providerDescription($icsEvent),
            'location' => $icsEvent->location,
        ];

        if ($icsEvent->allDay) {
            $parameters['allday'] = 'true';
            $parameters['startdt'] = $this->formatForProvider($icsEvent->start, true, 'Y-m-d');
            $parameters['enddt'] = $this->formatForProvider($icsEvent->end, true, 'Y-m-d');
        } else {
            $parameters['startdt'] = $this->formatForProvider($icsEvent->start, false, 'Y-m-d\TH:i:s\Z');
            $parameters['enddt'] = $this->formatForProvider($icsEvent->end, false, 'Y-m-d\TH:i:s\Z');
        }

        return $baseUrl . '/calendar/0/deeplink/compose?' . http_build_query($parameters);
    }

    /**
     * All-day values are dates and belong in the installation timezone; everything else is
     * an instant and is handed over in UTC.
     */
    private function formatForProvider(\DateTimeImmutable $date, bool $allDay, string $format): string
    {
        $timeZone = new \DateTimeZone($allDay ? date_default_timezone_get() : 'UTC');

        return $date->setTimezone($timeZone)->format($format);
    }

    private function providerDescription(IcsEvent $icsEvent): string
    {
        $description = PlainTextUtility::truncate($icsEvent->description, self::PROVIDER_DESCRIPTION_LIMIT);

        if ($icsEvent->url !== null && $icsEvent->url !== '') {
            $description = trim($description . "\n\n" . $icsEvent->url);
        }

        return $description;
    }

    private function slugify(string $title): string
    {
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'ae', 'oe', 'ue', 'ss'],
            $title
        );
        $slug = (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        $slug = strtolower($slug);
        $slug = (string)preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    /**
     * A record that has never been persisted has no pid and therefore no site.
     */
    private function resolveSite(?int $pid): ?Site
    {
        if ($pid === null || $pid === 0) {
            return null;
        }

        if (array_key_exists($pid, $this->siteCache)) {
            return $this->siteCache[$pid];
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pid);
        } catch (SiteNotFoundException) {
            $site = null;
        }

        return $this->siteCache[$pid] = $site;
    }
}

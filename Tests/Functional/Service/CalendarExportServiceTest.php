<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;
use Xima\XimaTypo3Calendar\Service\CalendarExportService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Covers the mapping half of the export: which record field ends up in which property, and
 * where the links point.
 */
final class CalendarExportServiceTest extends AbstractCalendarFunctionalTestCase
{
    private CalendarExportService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/export.csv');

        $this->writeCalendarSiteConfiguration();

        $this->subject = $this->get(CalendarExportService::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function derivesTheUidFromTheRecordAndTheSiteHost(): void
    {
        $ics = $this->subject->icsForAppointment($this->loadAppointment(1));

        self::assertStringContainsString('UID:entry-1@example.org', $ics);
    }

    /**
     * The same appointment must always produce the same UID, or a second download lands as a
     * second entry in the visitor's calendar instead of updating the first.
     */
    #[Test]
    public function producesTheSameUidOnEveryCall(): void
    {
        $first = $this->subject->icsForAppointment($this->loadAppointment(1));
        $second = $this->subject->icsForAppointment($this->loadAppointment(1));

        self::assertSame($this->extract('UID', $first), $this->extract('UID', $second));
    }

    #[Test]
    public function readsTheSequenceFromTheRecordTimestamp(): void
    {
        $ics = $this->subject->icsForAppointment($this->loadAppointment(1));

        self::assertSame('1767100000', $this->extract('SEQUENCE', $ics));
        self::assertSame('20251230T130640Z', $this->extract('LAST-MODIFIED', $ics));
    }

    #[Test]
    public function pointsTheUrlAtTheAppointmentDetailView(): void
    {
        $ics = $this->subject->icsForAppointment($this->loadAppointment(1));

        self::assertSame('https://example.org/detail/1/a1', $this->extract('URL', $ics));
    }

    #[Test]
    public function usesTheAppointmentLocationAsTheLocation(): void
    {
        $ics = $this->subject->icsForAppointment($this->loadAppointment(1));

        self::assertSame('Room 101', $this->extract('LOCATION', $ics));
    }

    #[Test]
    public function buildsAnAbsoluteIcsUrlForAnAppointment(): void
    {
        $links = $this->subject->linksForAppointment($this->loadAppointment(1));

        self::assertSame('https://example.org/detail/1/a1.ics', $links->ics);
    }

    #[Test]
    public function keepsTheActiveFrontendLanguageInGeneratedLinks(): void
    {
        $appointment = $this->loadAppointment(1);
        $site = $this->get(SiteFinder::class)->getSiteByPageId(2);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('language', $site->getLanguageById(1));

        $links = $this->subject->linksForAppointment($appointment);

        self::assertSame('https://example.org/de/detail/1/a1.ics', $links->ics);
        self::assertStringContainsString(
            'https://example.org/de/detail/1/a1',
            urldecode((string)$links->google)
        );
    }

    /**
     * A single-appointment event still has a hosted-calendar equivalent, so its provider links
     * are the appointment's.
     */
    #[Test]
    public function offersProviderLinksForAnEventWithASingleAppointment(): void
    {
        $links = $this->subject->linksForEvent($this->loadEvent(1));

        self::assertSame('https://example.org/detail/1/a1.ics', $links->ics);
        self::assertStringStartsWith('https://calendar.google.com/calendar/render?', (string)$links->google);
        self::assertStringStartsWith('https://outlook.live.com/', (string)$links->outlookPersonal);
        self::assertStringStartsWith('https://outlook.office.com/', (string)$links->outlookBusiness);
    }

    #[Test]
    public function passesTheAppointmentWindowToGoogle(): void
    {
        $links = $this->subject->linksForAppointment($this->loadAppointment(1));

        parse_str((string)parse_url((string)$links->google, PHP_URL_QUERY), $query);

        self::assertSame('TEMPLATE', $query['action']);
        self::assertSame('Live appointment', $query['text']);
        self::assertSame('20260101T000000Z/20260101T010000Z', $query['dates']);
    }

    #[Test]
    public function passesTheAppointmentWindowToOutlook(): void
    {
        $links = $this->subject->linksForAppointment($this->loadAppointment(1));

        parse_str((string)parse_url((string)$links->outlookPersonal, PHP_URL_QUERY), $query);

        self::assertSame('addevent', $query['rru']);
        self::assertSame('Live appointment', $query['subject']);
        self::assertSame('2026-01-01T00:00:00Z', $query['startdt']);
        self::assertSame('2026-01-01T01:00:00Z', $query['enddt']);
    }

    /**
     * Google and Outlook each compose exactly one entry, so a multi-appointment event only
     * has an honest answer for the ICS download.
     */
    #[Test]
    public function omitsProviderLinksForAnEventWithSeveralAppointments(): void
    {
        $event = $this->loadEvent(1);
        $event->getAppointments()?->attach(new EventAppointment());

        $links = $this->subject->linksForEvent($event);

        self::assertSame('https://example.org/detail/1.ics', $links->ics);
        self::assertNull($links->google);
        self::assertNull($links->outlookPersonal);
        self::assertNull($links->outlookBusiness);
    }

    #[Test]
    public function buildsAFilenameFromTheRecordTitle(): void
    {
        self::assertSame('live-event.ics', $this->subject->buildFilename($this->loadEvent(1)));
        self::assertSame(
            'live-appointment.ics',
            $this->subject->buildFilename($this->loadEvent(1), $this->loadAppointment(1))
        );
    }

    #[Test]
    public function fallsBackToAGenericFilenameForAnUntitledRecord(): void
    {
        self::assertSame('event.ics', $this->subject->buildFilename(new Event()));
    }

    /**
     * An unpersisted record has no pid, so no site and no link can be resolved for it.
     */
    #[Test]
    public function returnsNoLinksForARecordThatHasNoSite(): void
    {
        $links = $this->subject->linksForEvent(new Event());

        self::assertFalse($links->hasAny());
    }

    private function loadEvent(int $uid): Event
    {
        $event = $this->get(EventRepository::class)->findByUid($uid);
        self::assertInstanceOf(Event::class, $event);

        return $event;
    }

    private function loadAppointment(int $uid): EventAppointment
    {
        foreach ($this->loadEvent(1)->getAppointments() ?? [] as $appointment) {
            if ($appointment->getUid() === $uid) {
                return $appointment;
            }
        }

        self::fail('Appointment ' . $uid . ' not found');
    }

    private function extract(string $property, string $ics): ?string
    {
        $unfolded = str_replace("\r\n ", '', $ics);

        return preg_match('/^' . preg_quote($property, '/') . ':(.*)$/m', $unfolded, $matches) === 1
            ? rtrim($matches[1], "\r")
            : null;
    }
}

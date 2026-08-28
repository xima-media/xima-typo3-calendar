<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Proves the delivery end of the ICS export: the route resolves, the response leaves the
 * plugin with its own headers instead of being embedded in the page, and the frontend
 * visibility rules still decide who gets an answer.
 */
final class EventControllerIcsTest extends AbstractCalendarFunctionalTestCase
{
    /**
     * The plugin's content element is a copy of `lib.contentElement`, which fluid_styled_content
     * defines — without it the element renders empty rather than failing.
     */
    protected array $coreExtensionsToLoad = [
        'dashboard',
        'fluid_styled_content',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/export.csv');

        $this->writeCalendarSiteConfiguration();
        $this->setUpFrontendRootPage(1, ['EXT:calendar_test/Configuration/TypoScript/setup.typoscript']);

        // setUpFrontendRootPage() writes the template with `clear = 3`, which drops everything
        // included before it — the site sets and the content rendering the plugin builds on.
        $this->getConnectionPool()
            ->getConnectionForTable('sys_template')
            ->update('sys_template', ['clear' => 0], ['pid' => 1]);
    }

    #[Test]
    public function servesASingleAppointmentAsACalendarDownload(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://example.org/detail/1-live-event/a1.ics')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            'attachment; filename="live-appointment.ics"',
            $response->getHeaderLine('Content-Disposition')
        );

        $body = (string)$response->getBody();
        self::assertStringStartsWith('BEGIN:VCALENDAR', $body);
        self::assertSame(1, substr_count($body, 'BEGIN:VEVENT'));
        self::assertStringContainsString('SUMMARY:Live appointment', $body);
    }

    /**
     * The body must not be wrapped in the detail page — the whole point of propagating the
     * response rather than returning it as plugin content.
     */
    #[Test]
    public function doesNotEmbedTheCalendarInThePage(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://example.org/detail/1-live-event/a1.ics')
        );

        self::assertStringNotContainsString('<html', (string)$response->getBody());
    }

    #[Test]
    public function servesEveryAppointmentOfAnEvent(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://example.org/detail/1-live-event.ics')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'attachment; filename="live-event.ics"',
            $response->getHeaderLine('Content-Disposition')
        );
        self::assertSame(1, substr_count((string)$response->getBody(), 'BEGIN:VEVENT'));
    }

    #[Test]
    public function resolvesTheEventFromTheAppointmentAlone(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://example.org/detail/a1.ics')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('SUMMARY:Live appointment', (string)$response->getBody());
    }

    /**
     * The appointment belongs to a draft event, so the enforced frontend restrictions hide it
     * from the property mapper and no event can be resolved.
     */
    #[Test]
    public function refusesAnAppointmentOfANonPublicEvent(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://example.org/detail/a2.ics')
        );

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function stillRendersTheDetailPageItself(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://example.org/detail/1-live-event')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Live event', (string)$response->getBody());
    }
}

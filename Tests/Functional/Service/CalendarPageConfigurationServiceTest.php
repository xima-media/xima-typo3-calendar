<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use Xima\XimaTypo3Calendar\Service\CalendarPageConfigurationReader;
use Xima\XimaTypo3Calendar\Service\CalendarPageConfigurationService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class CalendarPageConfigurationServiceTest extends AbstractCalendarFunctionalTestCase
{
    private const STORAGE_PAGE = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
    }

    #[Test]
    public function globalPageTsConfigProvidesTheShippedValues(): void
    {
        $service = $this->createService();
        $request = $this->createRequest(0);

        self::assertSame(
            [
                'enableDragNewEvent' => true,
                'enableClickNewEvent' => true,
                'defaultStartTime' => '10:00',
                'defaultEndTime' => '15:30',
                'defaultAllDay' => false,
                'defaultRecordType' => 'event',
                'timeSlotMinutes' => 15,
                'scrollStartTime' => '07:00:00',
                'firstDay' => 'monday',
            ],
            $service->getNewEventConfiguration($request),
        );
        self::assertSame(
            ['recordTypes', 'categories', 'statuses', 'location'],
            $service->getTooltipOrder($request),
        );
        self::assertSame(
            ['iconSource' => 'recordTypes', 'appearanceSource' => 'statuses', 'colorSource' => 'categories'],
            $service->getEventAppearanceConfiguration($request),
        );
        self::assertSame([], $service->getFilterColors($request));
    }

    #[Test]
    public function validValuesOverrideTheShippedOnes(): void
    {
        $this->setPageTsConfig(<<<'TSCONFIG'
            mod.tx_ximatypo3calendar {
                calendar.firstDay = sunday
                weekViewGrid.timeSlotMinutes = 30
                weekViewGrid.scrollStartTime = 06:30:15
                newEvent.interaction.enableDrag = 0
                newEvent.defaults {
                    startTime = 08:15
                    endTime = 17:45
                    allDay = 1
                    recordType = meeting
                }
            }
            TSCONFIG);

        $configuration = $this->createService()->getNewEventConfiguration($this->createRequest(self::STORAGE_PAGE));

        self::assertFalse($configuration['enableDragNewEvent']);
        self::assertTrue($configuration['enableClickNewEvent']);
        self::assertSame('08:15', $configuration['defaultStartTime']);
        self::assertSame('17:45', $configuration['defaultEndTime']);
        self::assertTrue($configuration['defaultAllDay']);
        self::assertSame('meeting', $configuration['defaultRecordType']);
        self::assertSame(30, $configuration['timeSlotMinutes']);
        self::assertSame('06:30:15', $configuration['scrollStartTime']);
        self::assertSame('sunday', $configuration['firstDay']);
    }

    #[Test]
    public function invalidValuesFallBackToTheServiceDefaults(): void
    {
        $this->setPageTsConfig(<<<'TSCONFIG'
            mod.tx_ximatypo3calendar {
                calendar.firstDay = not-a-day
                weekViewGrid.timeSlotMinutes = 20
                weekViewGrid.scrollStartTime = 25:00
                newEvent.interaction.enableClick = yes
                newEvent.defaults {
                    startTime = 7:00
                    endTime = 18:60
                    recordType =
                }
            }
            TSCONFIG);

        $configuration = $this->createService()->getNewEventConfiguration($this->createRequest(self::STORAGE_PAGE));

        self::assertFalse($configuration['enableClickNewEvent']);
        self::assertSame('09:00', $configuration['defaultStartTime']);
        self::assertSame('09:30', $configuration['defaultEndTime']);
        self::assertNull($configuration['defaultRecordType']);
        self::assertSame(15, $configuration['timeSlotMinutes']);
        self::assertSame('08:00:00', $configuration['scrollStartTime']);
        self::assertSame('monday', $configuration['firstDay']);
    }

    #[Test]
    public function tooltipOrderAlwaysContainsTheAppearanceSources(): void
    {
        $this->setPageTsConfig(<<<'TSCONFIG'
            mod.tx_ximatypo3calendar {
                tooltipOrder = location, statuses
                eventAppearance.colorSource = appointmentTypes
            }
            TSCONFIG);

        self::assertSame(
            ['location', 'statuses', 'recordTypes', 'appointmentTypes'],
            $this->createService()->getTooltipOrder($this->createRequest(self::STORAGE_PAGE)),
        );
    }

    #[Test]
    public function filterColorsKeepOnlyHexColoursOfNumericValues(): void
    {
        $this->setPageTsConfig(<<<'TSCONFIG'
            mod.tx_ximatypo3calendar.filterColors {
                categories {
                    3 = #1976D2
                    4 = red
                    draft = #000000
                }
                statuses.2 = #00ff00
                recordTypes.1 = #12345
            }
            TSCONFIG);

        self::assertSame(
            [
                'categories' => ['3' => '#1976D2'],
                'statuses' => ['2' => '#00ff00'],
            ],
            $this->createService()->getFilterColors($this->createRequest(self::STORAGE_PAGE)),
        );
    }

    private function createService(): CalendarPageConfigurationService
    {
        return new CalendarPageConfigurationService(new CalendarPageConfigurationReader());
    }

    private function createRequest(int $pageId): Request
    {
        $serverRequest = (new ServerRequest('https://example.com/typo3/module/calendar'))
            ->withQueryParams(['id' => (string)$pageId])
            ->withAttribute('extbase', new ExtbaseRequestParameters());

        return new Request($serverRequest);
    }

    private function setPageTsConfig(string $tsConfig): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->update('pages', ['TSconfig' => $tsConfig], ['uid' => self::STORAGE_PAGE]);
    }
}

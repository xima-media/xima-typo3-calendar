<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\TreeCalendarFilter;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\TreeCalendarFilterItem;
use Xima\XimaTypo3Calendar\Event\ConfigureCalendarFilterEvent;
use Xima\XimaTypo3Calendar\Service\CalendarFilterMarkerResolver;
use Xima\XimaTypo3Calendar\Service\CalendarFilterService;
use Xima\XimaTypo3Calendar\Service\CalendarPageConfigurationReader;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class CalendarFilterServiceTest extends AbstractCalendarFunctionalTestCase
{
    #[Test]
    public function keepsExpandedTreeItemsWhenSavingAndReadingState(): void
    {
        $filter = new TreeCalendarFilter('categories');
        $filter->addItem(new TreeCalendarFilterItem('Parent', 1));

        $eventDispatcher = self::createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ConfigureCalendarFilterEvent::class))
            ->willReturnCallback(static function (ConfigureCalendarFilterEvent $event) use ($filter): ConfigureCalendarFilterEvent {
                $event->addFilter($filter);

                return $event;
            });

        $storedState = [];
        $backendUser = self::createStub(BackendUserAuthentication::class);
        $backendUser
            ->method('getModuleData')
            ->willReturnCallback(static function () use (&$storedState): array {
                return $storedState;
            });
        $backendUser
            ->method('pushModuleData')
            ->willReturnCallback(static function (string $module, array $state) use (&$storedState): void {
                self::assertSame('calendar_calendar', $module);
                $storedState = $state;
            });

        $previousBackendUser = $GLOBALS['BE_USER'] ?? null;
        $GLOBALS['BE_USER'] = $backendUser;

        try {
            $service = new CalendarFilterService(
                $eventDispatcher,
                $this->get(CalendarPageConfigurationReader::class),
                new CalendarFilterMarkerResolver(),
            );
            $request = new ServerRequest('https://example.com/typo3/');
            $service->saveState($request, [
                'trees' => [
                    'categories' => [
                        'activeItems' => [],
                        'expandedItems' => ['1' => true],
                    ],
                ],
            ]);

            self::assertSame(
                ['1' => true],
                $service->getState($request)['trees']['categories']['expandedItems'],
            );
        } finally {
            $GLOBALS['BE_USER'] = $previousBackendUser;
        }
    }
}

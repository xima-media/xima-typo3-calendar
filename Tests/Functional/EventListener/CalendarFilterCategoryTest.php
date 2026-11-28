<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3Calendar\Event\ConfigureCalendarFilterEvent;
use Xima\XimaTypo3Calendar\EventListener\CalendarFilterCategory;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class CalendarFilterCategoryTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/category-filter.csv');
    }

    #[Test]
    public function flagsCategoriesListedOnlyAsParents(): void
    {
        $event = new ConfigureCalendarFilterEvent(new ServerRequest('https://example.com/typo3/'));
        (new CalendarFilterCategory($this->get(ConnectionPool::class)))->build($event);

        $items = $event->getFilters()[0]->jsonSerialize()['items'];
        $hasOwnEntries = array_combine(
            array_column($items, 'value'),
            array_column($items, 'hasOwnEntries'),
        );
        ksort($hasOwnEntries);

        self::assertSame([1 => false, 2 => true, 3 => true, 4 => true], $hasOwnEntries);
    }
}

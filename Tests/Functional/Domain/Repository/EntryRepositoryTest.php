<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class EntryRepositoryTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar.csv');
    }

    #[Test]
    public function backendCalendarEntriesIncludeAppointmentsOverlappingTheRequestedWindow(): void
    {
        $rows = $this->get(EntryRepository::class)->getBackendCalendarEntries(
            1767227400,
            1767231000,
        );

        self::assertContains(1, array_map('intval', array_column($rows, 'uid')));
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventAppointmentType;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

final class EventAppointmentTest extends AbstractCalendarFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/calendar.csv');
    }

    #[Test]
    public function anEmptyAppointmentTypeFallsBackToInPerson(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)
            ->update(self::TABLE_ENTRY, ['type' => ''], ['uid' => 1]);

        $event = $this->get(EventRepository::class)->findByUid(1);

        self::assertInstanceOf(Event::class, $event);
        $appointment = $event->getAppointments()?->current();

        self::assertInstanceOf(EventAppointment::class, $appointment);
        self::assertSame(EventAppointmentType::IN_PERSON, $appointment->getType());
    }
}

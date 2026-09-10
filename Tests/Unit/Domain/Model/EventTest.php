<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Domain\Model\EventAppointment;

/**
 * `getNextAppointment()` answers which appointment represents an event on a page that
 * addresses the series rather than a single date.
 */
final class EventTest extends TestCase
{
    #[Test]
    public function returnsNullWithoutAppointments(): void
    {
        self::assertNull((new Event())->getNextAppointment());
    }

    #[Test]
    public function returnsTheOnlyUpcomingAppointment(): void
    {
        $past = $this->appointment('-2 days');
        $upcoming = $this->appointment('+2 days');

        $event = $this->eventWith($past, $upcoming);

        self::assertSame($upcoming, $event->getNextAppointment());
    }

    #[Test]
    public function returnsTheEarliestUpcomingAppointment(): void
    {
        $late = $this->appointment('+30 days');
        $soon = $this->appointment('+2 days');

        $event = $this->eventWith($late, $soon);

        self::assertSame($soon, $event->getNextAppointment());
    }

    /**
     * The relation is not guaranteed to be ordered by date, so the dates decide.
     */
    #[Test]
    public function ignoresTheOrderOfTheRelation(): void
    {
        $soon = $this->appointment('+1 day');
        $event = $this->eventWith($this->appointment('+10 days'), $soon, $this->appointment('+20 days'));

        self::assertSame($soon, $event->getNextAppointment());
    }

    /**
     * A finished series still has to render a date, so the first one stands in.
     */
    #[Test]
    public function fallsBackToTheEarliestAppointmentOfAPastSeries(): void
    {
        $earliest = $this->appointment('-30 days');
        $event = $this->eventWith($this->appointment('-2 days'), $earliest);

        self::assertSame($earliest, $event->getNextAppointment());
    }

    /**
     * Cancelled dates are part of the answer: the detail view marks them as cancelled
     * rather than silently showing the date after them.
     */
    #[Test]
    public function includesCancelledAppointments(): void
    {
        $cancelled = $this->appointment('+1 day');
        $cancelled->setCanceled(true);

        $event = $this->eventWith($cancelled, $this->appointment('+5 days'));

        self::assertSame($cancelled, $event->getNextAppointment());
    }

    #[Test]
    public function skipsAppointmentsWithoutStartDate(): void
    {
        $dated = $this->appointment('+1 day');
        $event = $this->eventWith(new EventAppointment(), $dated);

        self::assertSame($dated, $event->getNextAppointment());
    }

    private function appointment(string $modifier): EventAppointment
    {
        $appointment = new EventAppointment();
        $appointment->setStartDate(new \DateTime($modifier));

        return $appointment;
    }

    private function eventWith(EventAppointment ...$appointments): Event
    {
        /** @var ObjectStorage<EventAppointment> $storage */
        $storage = new ObjectStorage();
        foreach ($appointments as $appointment) {
            $storage->attach($appointment);
        }

        $event = new Event();
        $event->setAppointments($storage);

        return $event;
    }
}

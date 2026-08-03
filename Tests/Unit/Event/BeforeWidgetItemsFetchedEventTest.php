<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Xima\XimaTypo3Calendar\Event\BeforeWidgetItemsFetchedEvent;
use Xima\XimaTypo3Calendar\Widgets\Provider\UpcomingAppointmentsDataProvider;

/**
 * The event is the extension point that lets a project narrow a dashboard
 * widget's query. Listeners identify the widget through the dispatching class
 * name and either mutate or replace the QueryBuilder.
 */
final class BeforeWidgetItemsFetchedEventTest extends TestCase
{
    #[Test]
    public function exposesTheQueryBuilderItWasConstructedWith(): void
    {
        $queryBuilder = self::createStub(QueryBuilder::class);

        $event = new BeforeWidgetItemsFetchedEvent($queryBuilder, UpcomingAppointmentsDataProvider::class);

        self::assertSame($queryBuilder, $event->getQueryBuilder());
    }

    #[Test]
    public function exposesTheDispatchingClassName(): void
    {
        $event = new BeforeWidgetItemsFetchedEvent(
            self::createStub(QueryBuilder::class),
            UpcomingAppointmentsDataProvider::class
        );

        self::assertSame(UpcomingAppointmentsDataProvider::class, $event->getDispatchingClassName());
    }

    /**
     * The providers re-read the QueryBuilder from the event after dispatching, so
     * a listener may swap in an entirely different instance.
     */
    #[Test]
    public function allowsAListenerToReplaceTheQueryBuilder(): void
    {
        $original = self::createStub(QueryBuilder::class);
        $replacement = self::createStub(QueryBuilder::class);
        $event = new BeforeWidgetItemsFetchedEvent($original, UpcomingAppointmentsDataProvider::class);

        $event->setQueryBuilder($replacement);

        self::assertSame($replacement, $event->getQueryBuilder());
        self::assertNotSame($original, $event->getQueryBuilder());
    }

    #[Test]
    public function keepsTheDispatchingClassNameImmutable(): void
    {
        $event = new BeforeWidgetItemsFetchedEvent(
            self::createStub(QueryBuilder::class),
            UpcomingAppointmentsDataProvider::class
        );
        $event->setQueryBuilder(self::createStub(QueryBuilder::class));

        self::assertSame(UpcomingAppointmentsDataProvider::class, $event->getDispatchingClassName());
    }
}

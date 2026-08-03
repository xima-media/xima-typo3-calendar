<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Event\AbstractRecordChangedEvent;
use Xima\XimaTypo3Calendar\Event\ChangeType;
use Xima\XimaTypo3Calendar\Event\EntryChangedEvent;
use Xima\XimaTypo3Calendar\Event\EventChangedEvent;
use Xima\XimaTypo3Calendar\Event\RequirementBookingChangedEvent;

/**
 * The three concrete record-changed events are distinguished by class only —
 * listeners subscribe to one of them. Their payload contract is shared and must
 * stay identical, so it is asserted for all three through the same provider.
 */
final class RecordChangedEventTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<AbstractRecordChangedEvent>}>
     */
    public static function eventClassProvider(): array
    {
        return [
            'event'               => [EventChangedEvent::class],
            'entry'               => [EntryChangedEvent::class],
            'requirement booking' => [RequirementBookingChangedEvent::class],
        ];
    }

    /**
     * @param class-string<AbstractRecordChangedEvent> $eventClass
     */
    #[Test]
    #[DataProvider('eventClassProvider')]
    public function exposesTheConstructorPayload(string $eventClass): void
    {
        $changedFields = [
            'title'  => ['old' => 'Old title', 'new' => 'New title'],
            'status' => ['old' => 0, 'new' => 2],
        ];

        $event = new $eventClass(42, 'tx_ximatypo3calendar_domain_model_event', ChangeType::UPDATED, $changedFields);

        self::assertSame(42, $event->uid);
        self::assertSame('tx_ximatypo3calendar_domain_model_event', $event->table);
        self::assertSame(ChangeType::UPDATED, $event->changeType);
        self::assertSame($changedFields, $event->changedFields);
    }

    /**
     * @param class-string<AbstractRecordChangedEvent> $eventClass
     */
    #[Test]
    #[DataProvider('eventClassProvider')]
    public function extendsTheSharedAbstractBase(string $eventClass): void
    {
        $event = new $eventClass(1, 'some_table', ChangeType::CREATED, []);

        self::assertInstanceOf(AbstractRecordChangedEvent::class, $event);
    }

    /**
     * @param class-string<AbstractRecordChangedEvent> $eventClass
     */
    #[Test]
    #[DataProvider('eventClassProvider')]
    public function isReadonlyAndRejectsMutation(string $eventClass): void
    {
        $event = new $eventClass(1, 'some_table', ChangeType::CREATED, []);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — intentionally writing to a readonly property
        $event->uid = 2;
    }

    #[Test]
    public function acceptsAnEmptyFieldDiffForLifecycleTransitions(): void
    {
        $event = new EventChangedEvent(7, 'tx_ximatypo3calendar_domain_model_event', ChangeType::DELETED, []);

        self::assertSame([], $event->changedFields);
        self::assertTrue($event->changeType->allowsEmptyFields());
    }
}

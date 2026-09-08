<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use Xima\XimaTypo3Calendar\Domain\Model\Event;
use Xima\XimaTypo3Calendar\Event\ModifyEventDetailViewEvent;

/**
 * The event is the extension point that lets a project enrich the detail view
 * without replacing the controller. Listeners add their own variables or swap
 * out the ones the controller resolved.
 */
final class ModifyEventDetailViewEventTest extends TestCase
{
    #[Test]
    public function exposesTheAssignedValuesItWasConstructedWith(): void
    {
        $values = ['event' => new Event(), 'appointment' => null];

        $event = new ModifyEventDetailViewEvent($values, self::createStub(RequestInterface::class));

        self::assertSame($values, $event->getAssignedValues());
    }

    #[Test]
    public function exposesTheRequest(): void
    {
        $request = self::createStub(RequestInterface::class);

        $event = new ModifyEventDetailViewEvent([], $request);

        self::assertSame($request, $event->getRequest());
    }

    /**
     * The controller re-reads the values from the event after dispatching, so a
     * listener may add variables of its own.
     */
    #[Test]
    public function allowsAListenerToAddAssignedValues(): void
    {
        $event = new ModifyEventDetailViewEvent(['event' => new Event()], self::createStub(RequestInterface::class));

        $values = $event->getAssignedValues();
        $values['breadcrumb'] = ['Home', 'Events'];
        $event->setAssignedValues($values);

        self::assertArrayHasKey('event', $event->getAssignedValues());
        self::assertSame(['Home', 'Events'], $event->getAssignedValues()['breadcrumb']);
    }

    #[Test]
    public function allowsAListenerToReplaceTheAssignedValues(): void
    {
        $event = new ModifyEventDetailViewEvent(['event' => new Event()], self::createStub(RequestInterface::class));

        $event->setAssignedValues([]);

        self::assertSame([], $event->getAssignedValues());
    }
}

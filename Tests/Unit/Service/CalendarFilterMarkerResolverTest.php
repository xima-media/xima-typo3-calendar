<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Service\CalendarFilterMarkerResolver;

final class CalendarFilterMarkerResolverTest extends TestCase
{
    private CalendarFilterMarkerResolver $subject;

    protected function setUp(): void
    {
        $this->subject = new CalendarFilterMarkerResolver();
    }

    #[Test]
    public function derivesMarkerFromCanceledLabel(): void
    {
        $filter = $this->subject->resolve([
            'type' => 'checkbox-list',
            'items' => [
                ['value' => 'canceled', 'label' => 'Abgesagt', 'marker' => null],
            ],
        ]);

        self::assertSame('[A]', $filter['items'][0]['marker']);
    }

    #[Test]
    public function derivesMarkerFromFirstWordOnly(): void
    {
        $filter = $this->subject->resolve([
            'type' => 'checkbox-list',
            'items' => [
                ['value' => 'canceled', 'label' => 'storniert - intern', 'marker' => null],
            ],
        ]);

        self::assertSame('[S]', $filter['items'][0]['marker']);
    }

    #[Test]
    public function derivesMarkerFromBritishCanceledValue(): void
    {
        $filter = $this->subject->resolve([
            'type' => 'checkbox-list',
            'items' => [
                ['value' => 'cancelled', 'label' => 'Canceled', 'marker' => null],
            ],
        ]);

        self::assertSame('[C]', $filter['items'][0]['marker']);
    }

    #[Test]
    public function replacesExplicitMarkerWithLabelMarker(): void
    {
        $filter = $this->subject->resolve([
            'type' => 'checkbox-list',
            'items' => [
                ['value' => 'canceled', 'label' => 'Canceled', 'marker' => '[X]'],
            ],
        ]);

        self::assertSame('[C]', $filter['items'][0]['marker']);
    }

    #[Test]
    public function ignoresOtherFilterItems(): void
    {
        $filter = $this->subject->resolve([
            'type' => 'checkbox-list',
            'items' => [
                ['value' => 'live', 'label' => 'Live', 'marker' => null],
            ],
        ]);

        self::assertNull($filter['items'][0]['marker']);
    }
}

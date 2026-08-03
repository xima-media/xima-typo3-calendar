<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Domain\Model\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventAppointmentType;

final class EventAppointmentTypeTest extends TestCase
{
    #[Test]
    public function backingValuesAreStable(): void
    {
        self::assertSame('inPerson', EventAppointmentType::IN_PERSON->value);
        self::assertSame('online', EventAppointmentType::ONLINE->value);
        self::assertSame('hybrid', EventAppointmentType::HYBRID->value);
    }

    #[Test]
    public function hasExactlyThreeCases(): void
    {
        self::assertCount(3, EventAppointmentType::cases());
    }

    #[Test]
    #[DataProvider('unknownValueProvider')]
    public function tryFromRejectsUnknownValues(string $value): void
    {
        self::assertNull(EventAppointmentType::tryFrom($value));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unknownValueProvider(): array
    {
        return [
            'wrong casing' => ['inperson'],
            'empty string' => [''],
        ];
    }
}

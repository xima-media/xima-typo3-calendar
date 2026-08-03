<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Domain\Model\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventLanguage;

final class EventLanguageTest extends TestCase
{
    #[Test]
    public function backingValuesAreStable(): void
    {
        self::assertSame('', EventLanguage::ALL->value);
        self::assertSame('de', EventLanguage::DE->value);
        self::assertSame('en', EventLanguage::EN->value);
    }

    /**
     * ALL is intentionally backed by the empty string so an unset database column
     * resolves to "no language restriction" rather than to an invalid case.
     */
    #[Test]
    public function emptyStringResolvesToAll(): void
    {
        self::assertSame(EventLanguage::ALL, EventLanguage::from(''));
    }

    #[Test]
    public function hasExactlyThreeCases(): void
    {
        self::assertCount(3, EventLanguage::cases());
    }

    #[Test]
    #[DataProvider('unknownValueProvider')]
    public function tryFromRejectsUnknownValues(string $value): void
    {
        self::assertNull(EventLanguage::tryFrom($value));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unknownValueProvider(): array
    {
        return [
            'unsupported language' => ['fr'],
            'wrong casing'         => ['DE'],
        ];
    }
}

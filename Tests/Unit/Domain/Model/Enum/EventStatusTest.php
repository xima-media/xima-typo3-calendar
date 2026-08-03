<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Domain\Model\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

/**
 * The backing integers are persisted in
 * tx_ximatypo3calendar_domain_model_event.status and are hard-coded into the
 * query restrictions, the TCA itemsProcFunc and the dashboard widget SQL.
 * Reordering the cases would silently reinterpret every existing record.
 */
final class EventStatusTest extends TestCase
{
    #[Test]
    public function backingValuesAreStable(): void
    {
        self::assertSame(0, EventStatus::DRAFT->value);
        self::assertSame(1, EventStatus::REVIEW->value);
        self::assertSame(2, EventStatus::LIVE->value);
        self::assertSame(3, EventStatus::REJECTED->value);
    }

    #[Test]
    public function draftIsTheDatabaseDefault(): void
    {
        // ext_tables.sql declares `status smallint unsigned default 0`.
        self::assertSame(EventStatus::DRAFT, EventStatus::from(0));
    }

    #[Test]
    public function hasExactlyFourCases(): void
    {
        self::assertCount(4, EventStatus::cases());
    }

    #[Test]
    #[DataProvider('unknownValueProvider')]
    public function tryFromRejectsUnknownValues(int $value): void
    {
        self::assertNull(EventStatus::tryFrom($value));
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function unknownValueProvider(): array
    {
        return [
            'above the highest case' => [4],
            'negative'               => [-1],
        ];
    }
}

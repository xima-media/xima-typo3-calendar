<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Event\ChangeType;

final class ChangeTypeTest extends TestCase
{
    #[Test]
    public function allCasesAreBackedByTheExpectedStringValues(): void
    {
        $values = array_map(static fn (ChangeType $case): string => $case->value, ChangeType::cases());

        self::assertSame(
            [
                'created',
                'updated',
                'hidden',
                'deleted',
                'reactivated',
                'location_changed',
                'date_range_changed',
            ],
            $values
        );
    }

    /**
     * Only the pure lifecycle transitions carry no field diff. Every other change
     * type is dropped by DataHandlerEventDispatcherHook::dispatchLifecycleEvent()
     * when its diff is empty — widening this set would resurrect the phantom
     * "location changed" / "date range changed" events for untouched records.
     */
    #[Test]
    #[DataProvider('emptyFieldsProvider')]
    public function allowsEmptyFieldsOnlyForLifecycleTransitions(ChangeType $changeType, bool $expected): void
    {
        self::assertSame($expected, $changeType->allowsEmptyFields());
    }

    /**
     * @return array<string, array{0: ChangeType, 1: bool}>
     */
    public static function emptyFieldsProvider(): array
    {
        return [
            'hidden'             => [ChangeType::HIDDEN, true],
            'deleted'            => [ChangeType::DELETED, true],
            'reactivated'        => [ChangeType::REACTIVATED, true],
            'created'            => [ChangeType::CREATED, false],
            'updated'            => [ChangeType::UPDATED, false],
            'location changed'   => [ChangeType::LOCATION_CHANGED, false],
            'date range changed' => [ChangeType::DATE_RANGE_CHANGED, false],
        ];
    }

    #[Test]
    public function exactlyThreeCasesAllowEmptyFields(): void
    {
        $allowing = array_values(array_filter(
            ChangeType::cases(),
            static fn (ChangeType $case): bool => $case->allowsEmptyFields()
        ));

        self::assertCount(3, $allowing);
    }
}

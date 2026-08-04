<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Utility;

final class CalendarFeedRequestUtility
{
    private const DEFAULT_START_TIMESTAMP = 0;
    private const DEFAULT_END_TIMESTAMP = 253402300799;

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function getStartTimestamp(array $queryParams): int
    {
        return self::parseTimestamp($queryParams['start'] ?? null, self::DEFAULT_START_TIMESTAMP);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function getEndTimestamp(array $queryParams): int
    {
        return self::parseTimestamp($queryParams['end'] ?? null, self::DEFAULT_END_TIMESTAMP);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return int[]
     */
    public static function getCalendarUids(array $queryParams): array
    {
        return array_map('intval', (array)($queryParams['calendars'] ?? []));
    }

    private static function parseTimestamp(mixed $value, int $default): int
    {
        if (!is_scalar($value) || trim((string)$value) === '') {
            return $default;
        }

        try {
            return (new \DateTimeImmutable((string)$value))->getTimestamp();
        } catch (\Exception) {
            return $default;
        }
    }
}

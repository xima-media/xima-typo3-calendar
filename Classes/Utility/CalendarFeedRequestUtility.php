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

    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, list<int|string>>
     */
    public static function getTreeFilters(array $queryParams): array
    {
        $filters = $queryParams['filters'] ?? [];
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        if (!is_array($filters)) {
            return [];
        }

        $result = [];
        foreach ($filters as $identifier => $values) {
            if (!is_string($identifier) || !is_array($values)) {
                continue;
            }

            $result[$identifier] = array_values(array_unique(array_filter(
                array_map(
                    static fn (mixed $value): int|string => is_numeric($value)
                        ? (int)$value
                        : (is_string($value) ? trim($value) : ''),
                    $values,
                ),
                static fn (int|string $value): bool => $value !== '',
            ), SORT_REGULAR));
        }

        return $result;
    }

    private static function getListValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($values as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            foreach (explode(',', (string)$item) as $part) {
                $result[] = trim($part);
            }
        }

        return $result;
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

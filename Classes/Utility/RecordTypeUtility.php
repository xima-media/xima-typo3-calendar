<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Utility;

final class RecordTypeUtility
{
    public static function getDefault(string $table): ?string
    {
        $config = $GLOBALS['TCA'][$table]['columns']['record_type']['config'] ?? [];
        $default = $config['default'] ?? null;
        $items = $config['items'] ?? [];

        if (is_string($default) && self::containsValue($items, $default)) {
            return $default;
        }

        foreach ($items as $item) {
            if (is_array($item) && isset($item['value']) && is_string($item['value'])) {
                return $item['value'];
            }
        }

        return null;
    }

    private static function containsValue(mixed $items, string $value): bool
    {
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && ($item['value'] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }
}

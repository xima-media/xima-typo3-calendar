<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Utility;

final class RecordTypeUtility
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function getAdditionalItems(string $table, string $defaultValue): array
    {
        $config = $GLOBALS['TCA'][$table]['columns']['record_type']['config'] ?? [];
        $items = $config['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item) || !array_key_exists('value', $item)) {
                continue;
            }

            $value = $item['value'];
            if ((!is_string($value) && !is_int($value)) || (string)$value === $defaultValue) {
                continue;
            }

            $label = $item['label'] ?? $value;
            $result[] = [
                'value' => (string)$value,
                'label' => is_string($label) ? $label : (string)$value,
            ];
        }

        return $result;
    }

    public static function isAvailable(string $table, string $value): bool
    {
        $config = $GLOBALS['TCA'][$table]['columns']['record_type']['config'] ?? [];
        return self::containsValue($config['items'] ?? [], $value);
    }

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

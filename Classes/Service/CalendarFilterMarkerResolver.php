<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

final class CalendarFilterMarkerResolver
{
    private const CANCELED_VALUES = ['canceled', 'cancelled'];

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    public function resolve(array $filter): array
    {
        if (($filter['type'] ?? null) !== 'checkbox-list' || !is_array($filter['items'] ?? null)) {
            return $filter;
        }

        foreach ($filter['items'] as $index => $item) {
            if (!is_array($item) || !in_array((string)($item['value'] ?? ''), self::CANCELED_VALUES, true)) {
                continue;
            }

            $label = trim((string)($item['label'] ?? ''));
            $firstWord = preg_split('/\s+/u', $label, 2)[0] ?? '';
            $firstCharacter = mb_substr($firstWord, 0, 1);

            if ($firstCharacter === '') {
                continue;
            }

            $item['marker'] = '[' . mb_strtoupper($firstCharacter, 'UTF-8') . ']';
            $filter['items'][$index] = $item;
        }

        return $filter;
    }
}

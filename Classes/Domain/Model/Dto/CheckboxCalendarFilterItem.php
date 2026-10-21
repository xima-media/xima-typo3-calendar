<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Dto;

final class CheckboxCalendarFilterItem implements \JsonSerializable
{
    public function __construct(
        public string $label = '',
        public int|string $value = '',
        public bool $selected = false,
        public string $appearance = '',
        public ?string $icon = null,
        public ?string $marker = null,
    ) {
    }

    /**
     * @return array{label: string, value: int|string, selected: bool, appearance: string, icon: ?string, marker: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'selected' => $this->selected,
            'appearance' => $this->appearance,
            'icon' => $this->icon,
            'marker' => $this->marker,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Dto;

final class TreeCalendarFilterItem implements \JsonSerializable
{
    public function __construct(
        public string $label = '',
        public int|string $value = '',
        public bool $selected = false,
        public ?self $parent = null,
        public bool $hasOwnEntries = true,
    ) {
    }

    /**
     * @return array{
     *     label: string,
     *     value: string,
     *     selected: bool,
     *     parentUid: int|null,
     *     hasOwnEntries: bool
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'selected' => $this->selected,
            'parentUid' => $this->parent !== null ? (int)$this->parent->value : null,
            'hasOwnEntries' => $this->hasOwnEntries,
        ];
    }
}

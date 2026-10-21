<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Domain\Model\Dto;

final class TreeCalendarFilter extends AbstractCalendarFilter
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $title = '',
        private readonly bool $supportsColorCoding = false,
    ) {
    }

    /**
     * @var list<TreeCalendarFilterItem>
     */
    public array $items = [];
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function addItem(TreeCalendarFilterItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return array{
     *     type: 'tree',
     *     identifier: string,
     *     title: string,
     *     supportsColorCoding: bool,
     *     items: list<array{
     *         label: string,
     *         value: int|string,
     *         selected: bool,
     *         parentUid: int|null
     *     }>
     *         }
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'tree',
            'identifier' => $this->identifier,
            'title' => $this->title,
            'supportsColorCoding' => $this->supportsColorCoding,
            'items' => array_map(
                static fn (TreeCalendarFilterItem $item): array => $item->jsonSerialize(),
                $this->items,
            ),
        ];
    }
}

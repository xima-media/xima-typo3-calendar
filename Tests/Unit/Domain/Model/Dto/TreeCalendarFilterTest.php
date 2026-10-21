<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Domain\Model\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\TreeCalendarFilter;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\TreeCalendarFilterItem;

final class TreeCalendarFilterTest extends TestCase
{
    #[Test]
    public function serializesItemsAsArrays(): void
    {
        $parent = new TreeCalendarFilterItem('Parent', 1);
        $filter = new TreeCalendarFilter('categories', 'Categories', true);
        $filter->addItem($parent);
        $filter->addItem(new TreeCalendarFilterItem('Child', 2, false, $parent));

        self::assertSame([
            'type' => 'tree',
            'identifier' => 'categories',
            'title' => 'Categories',
            'supportsColorCoding' => true,
            'items' => [
                [
                    'label' => 'Parent',
                    'value' => 1,
                    'selected' => false,
                    'parentUid' => null,
                ],
                [
                    'label' => 'Child',
                    'value' => 2,
                    'selected' => false,
                    'parentUid' => 1,
                ],
            ],
        ], $filter->jsonSerialize());
    }

    #[Test]
    public function serializesTreeItemsThroughJsonEncoding(): void
    {
        $filter = new TreeCalendarFilter('categories');
        $filter->addItem(new TreeCalendarFilterItem('Category', 7));

        $serialized = json_decode(
            json_encode($filter, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('Category', $serialized['items'][0]['label']);
        self::assertSame(7, $serialized['items'][0]['value']);
        self::assertNull($serialized['items'][0]['parentUid']);
    }
}

<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3Calendar\Utility\PlainTextUtility;

final class PlainTextUtilityTest extends TestCase
{
    #[Test]
    public function removesMarkup(): void
    {
        self::assertSame('Bold and italic', PlainTextUtility::fromHtml('<p><strong>Bold</strong> and <em>italic</em></p>'));
    }

    #[Test]
    public function keepsBlockBoundariesAsLineBreaks(): void
    {
        self::assertSame("First\n\nSecond", PlainTextUtility::fromHtml('<p>First</p><p>Second</p>'));
    }

    #[Test]
    public function turnsLineBreakTagsIntoSingleNewlines(): void
    {
        self::assertSame("First\nSecond", PlainTextUtility::fromHtml('First<br />Second'));
    }

    #[Test]
    public function decodesEntities(): void
    {
        self::assertSame('Wine & Cheese — 5 €', PlainTextUtility::fromHtml('Wine &amp; Cheese &mdash; 5 &euro;'));
    }

    #[Test]
    public function collapsesRunsOfWhitespace(): void
    {
        self::assertSame('A B', PlainTextUtility::fromHtml("A   \t  B"));
    }

    #[Test]
    public function returnsAnEmptyStringForBlankInput(): void
    {
        self::assertSame('', PlainTextUtility::fromHtml('   '));
        self::assertSame('', PlainTextUtility::fromHtml(''));
    }

    #[Test]
    public function leavesShortTextUntouched(): void
    {
        self::assertSame('Short enough', PlainTextUtility::truncate('Short enough', 50));
    }

    #[Test]
    public function truncatesOnAWordBoundary(): void
    {
        self::assertSame('One two…', PlainTextUtility::truncate('One two three four', 12));
    }

    #[Test]
    public function countsCharactersRatherThanBytes(): void
    {
        self::assertSame('äääää', PlainTextUtility::truncate('äääää', 5));
    }
}

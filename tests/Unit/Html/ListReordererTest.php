<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Unit\Html;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\ReorderMenuItems\Html\ListReorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Tests\Support\GuideFixture;

final class ListReordererTest extends TestCase
{
    private static function li(int $itemid, string $text): string
    {
        return '<li><a href="index.php?Itemid=' . $itemid . '">' . $text . '</a></li>';
    }

    public function testTheEntriesComeInTheOrderOfTheirMenuItems(): void
    {
        $html   = '<ul>' . self::li(3, 'C') . self::li(1, 'A') . self::li(2, 'B') . '</ul>';
        $result = (new ListReorderer())->reorder($html, [1 => 0, 2 => 1, 3 => 2]);

        self::assertSame('<ul>' . self::li(1, 'A') . self::li(2, 'B') . self::li(3, 'C') . '</ul>', $result['html']);
        self::assertSame(1, $result['lists']);
    }

    public function testOnlyTheOrderChangesNotAByteOfTheEntriesOrTheTextAround(): void
    {
        $html     = GuideFixture::fieldList([117, 118, 19, 120], '&amp;lang=de-DE');
        $expected = GuideFixture::fieldList([118, 117, 120, 19], '&amp;lang=de-DE');
        $result   = (new ListReorderer())->reorder($html, [118 => 0, 117 => 1, 120 => 2, 19 => 3, 21 => 9]);

        self::assertSame($expected, $result['html']);
        self::assertSame(1, $result['lists']);
    }

    public function testAListInTheRightOrderIsNotCounted(): void
    {
        $html   = GuideFixture::fieldList([17, 18, 19, 20], '');
        $result = (new ListReorderer())->reorder($html, [17 => 0, 18 => 1, 19 => 2, 20 => 3]);

        self::assertSame($html, $result['html']);
        self::assertSame(0, $result['lists']);
    }

    public function testAListWithAnEntryOutsideTheMenuIsLeftAlone(): void
    {
        $html = '<ul>' . self::li(2, 'B') . '<li><a href="https://example.org">X</a></li>' . self::li(1, 'A') . '</ul>';

        self::assertSame(0, (new ListReorderer())->reorder($html, [1 => 0, 2 => 1])['lists']);
    }

    public function testAListWithTextBetweenItsEntriesIsLeftAlone(): void
    {
        $html = '<ul>' . self::li(2, 'B') . ' stray ' . self::li(1, 'A') . '</ul>';

        self::assertSame(0, (new ListReorderer())->reorder($html, [1 => 0, 2 => 1])['lists']);
    }

    public function testANestedListIsLeftAlone(): void
    {
        $html = '<ul><li><a href="index.php?Itemid=2">B</a><ul><li>x</li></ul></li>' . self::li(1, 'A') . '</ul>';

        self::assertSame($html, (new ListReorderer())->reorder($html, [1 => 0, 2 => 1])['html']);
    }

    public function testEntriesOfEqualRankKeepTheirOrder(): void
    {
        $html   = '<ul>' . self::li(5, 'E') . self::li(4, 'D') . self::li(1, 'A') . '</ul>';
        $result = (new ListReorderer())->reorder($html, [1 => 0, 4 => 1, 5 => 1]);

        self::assertSame('<ul>' . self::li(1, 'A') . self::li(5, 'E') . self::li(4, 'D') . '</ul>', $result['html']);
    }

    public function testAttributesOnTheListAndItsEntriesStay(): void
    {
        $b      = '<li id="b"><a href="index.php?option=com_content&amp;Itemid=2">B</a></li>';
        $html   = '<ul class="x">' . $b . self::li(1, 'A') . '</ul>';
        $result = (new ListReorderer())->reorder($html, [1 => 0, 2 => 1]);

        self::assertSame('<ul class="x">' . self::li(1, 'A') . $b . '</ul>', $result['html']);
    }

    public function testItemid12IsNotItemid1(): void
    {
        $html = '<ul>' . self::li(12, 'L') . self::li(1, 'A') . '</ul>';

        self::assertSame(0, (new ListReorderer())->reorder($html, [1 => 1, 2 => 0])['lists']);
    }
}

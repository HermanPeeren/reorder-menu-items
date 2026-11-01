<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Unit\Menu;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuItem;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;
use Yepr\Plugin\Task\ReorderMenuItems\Tests\Support\TreeBuilder;

final class MenuTreeTest extends TestCase
{
    /**
     * root 1
     *   a 2 (menu x)
     *     a1 4
     *     a2 5
     *       a21 8
     *   b 3 (menu y)
     *   c 6 (menu x)
     *     c1 7
     */
    private function tree(): MenuTree
    {
        return TreeBuilder::fromOutline([
            [2, 'a', 'x', [
                [4, 'a1', 'x', []],
                [5, 'a2', 'x', [
                    [8, 'a21', 'x', []],
                ]],
            ]],
            [3, 'b', 'y', []],
            [6, 'c', 'x', [
                [7, 'c1', 'x', []],
            ]],
        ]);
    }

    public function testChildrenComeInTheOrderOfTheirLft(): void
    {
        $tree = $this->tree();

        self::assertSame([2, 3, 6], array_map(static fn (MenuItem $i): int => $i->id, $tree->children(1)));
        self::assertSame([4, 5], array_map(static fn (MenuItem $i): int => $i->id, $tree->children(2)));
        self::assertSame([], $tree->children(4));
    }

    public function testAMenuIsItsItemsInTreeOrder(): void
    {
        self::assertSame([2, 4, 5, 8, 6, 7], array_map(static fn (MenuItem $i): int => $i->id, $this->tree()->menu('x')));
    }

    public function testAConsistentTreeNeedsNoRenumbering(): void
    {
        $tree = $this->tree();

        self::assertSame([], $tree->renumber([]));
        self::assertSame(0, $tree->inconsistencies());
    }

    public function testKeepingTheOrderChangesNothing(): void
    {
        self::assertSame([], $this->tree()->renumber([1 => [2, 3, 6], 2 => [4, 5]]));
    }

    public function testSwappingTwoLeavesRenumbersOnlyThem(): void
    {
        // a1 is 2-3 and a2 (with a21) is 4-7; after the swap a2 is 2-5, a21 3-4, a1 6-7.
        self::assertEquals(
            [5 => ['lft' => 2, 'rgt' => 5], 8 => ['lft' => 3, 'rgt' => 4], 4 => ['lft' => 6, 'rgt' => 7]],
            $this->tree()->renumber([2 => [5, 4]])
        );
    }

    public function testMovingAGroupToTheFrontMovesItsWholeSubtree(): void
    {
        $tree    = $this->tree();
        $changed = $tree->renumber([1 => [6, 2, 3]]);

        $after = TreeBuilder::apply($tree, $changed);

        self::assertSame([6, 2, 3], array_map(static fn (MenuItem $i): int => $i->id, $after->children(1)));
        self::assertSame([7], array_map(static fn (MenuItem $i): int => $i->id, $after->children(6)));
        self::assertSame([4, 5], array_map(static fn (MenuItem $i): int => $i->id, $after->children(2)));
        self::assertSame(0, $after->inconsistencies());
        self::assertSame(['lft' => 1, 'rgt' => 4], $changed[6]);
        self::assertSame(['lft' => 2, 'rgt' => 3], $changed[7]);
        // The root spans the same range: nothing is added or lost.
        self::assertArrayNotHasKey(1, $changed);
    }

    public function testAnOrderThatIsNotThePermutationOfTheChildrenIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tree()->renumber([2 => [4]]);
    }

    public function testAnOrderWithAStrangerIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tree()->renumber([2 => [4, 7]]);
    }

    public function testBrokenNumbersAreCountedAndRepaired(): void
    {
        $tree = new MenuTree([
            new MenuItem(1, 0, 0, 9),
            new MenuItem(2, 1, 1, 2),
            new MenuItem(3, 1, 5, 6),
        ]);

        self::assertSame(2, $tree->inconsistencies());
        self::assertEquals([3 => ['lft' => 3, 'rgt' => 4], 1 => ['lft' => 0, 'rgt' => 5]], $tree->renumber([]));
    }

    public function testAnOrphanIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('parent 99');

        new MenuTree([new MenuItem(1, 0, 0, 3), new MenuItem(2, 99, 1, 2)]);
    }

    public function testTwoRootsAreRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        new MenuTree([new MenuItem(1, 0, 0, 1), new MenuItem(2, 0, 2, 3)]);
    }

    public function testALoopIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be reached');

        new MenuTree([new MenuItem(1, 0, 0, 1), new MenuItem(2, 3, 2, 3), new MenuItem(3, 2, 4, 5)]);
    }
}

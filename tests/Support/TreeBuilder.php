<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\ReorderMenuItems\Tests\Support;

use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuItem;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;

/**
 * Menu trees for tests, written as an outline instead of lft and rgt by hand.
 *
 * An outline is a list of [id, title, menutype, children], optionally followed by
 * the language and the link; the root is id 1.
 */
final class TreeBuilder
{
    /**
     * @param list<array{0: int, 1: string, 2: string, 3: array<mixed>, 4?: string, 5?: string}> $outline
     */
    public static function fromOutline(array $outline): MenuTree
    {
        $items = [];
        $rgt   = self::add($outline, 1, 1, $items);

        $items[] = new MenuItem(1, 0, 0, $rgt, '', 'ROOT', 'root');

        return new MenuTree($items);
    }

    /**
     * The tree after writing renumber()'s result, as the database would hold it.
     *
     * @param array<int, array{lft: int, rgt: int}> $changed
     */
    public static function apply(MenuTree $tree, array $changed): MenuTree
    {
        $items = [];
        $all   = [$tree->item($tree->rootId())];

        for ($i = 0; $i < \count($all); $i++) {
            foreach ($tree->children($all[$i]->id) as $child) {
                $all[] = $child;
            }
        }

        foreach ($all as $item) {
            $pair    = $changed[$item->id] ?? ['lft' => $item->lft, 'rgt' => $item->rgt];
            $items[] = new MenuItem(
                $item->id,
                $item->parentId,
                $pair['lft'],
                $pair['rgt'],
                $item->menutype,
                $item->title,
                $item->alias,
                $item->path,
                $item->language,
                $item->link
            );
        }

        return new MenuTree($items);
    }

    /**
     * @param list<array{0: int, 1: string, 2: string, 3: array<mixed>, 4?: string, 5?: string}> $outline
     * @param list<MenuItem>                                                         $items
     *
     * @return int The rgt of the parent.
     */
    private static function add(array $outline, int $parentId, int $lft, array &$items, string $parentPath = ''): int
    {
        foreach ($outline as $node) {
            [$id, $title, $menutype, $children] = $node;
            $alias                              = strtolower(str_replace(' ', '-', $title));
            $path                               = $parentPath === '' ? $alias : $parentPath . '/' . $alias;
            /** @var list<array{0: int, 1: string, 2: string, 3: array<mixed>, 4?: string, 5?: string}> $children */
            $rgt      = self::add($children, $id, $lft + 1, $items, $path);
            $language = $node[4] ?? '*';
            $link     = $node[5] ?? '';
            $items[]  = new MenuItem($id, $parentId, $lft, $rgt, $menutype, $title, $alias, $path, $language, $link);
            $lft      = $rgt + 1;
        }

        return $lft;
    }
}

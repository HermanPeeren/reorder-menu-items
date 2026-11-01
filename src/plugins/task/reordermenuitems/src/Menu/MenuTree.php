<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Menu;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The whole of #__menu as a tree: site and administrator menus, every menu type, under
 * the one root.
 *
 * #__menu is a nested set. The order of siblings is the order of their lft values, and
 * moving one item means moving the lft and rgt of everything after it. So a new order is
 * not written item by item: renumber() takes the new order of some sibling groups, walks
 * the whole tree with every other group in its present order, and gives back the lft and
 * rgt each item gets. Only items whose numbers change need writing.
 */
final class MenuTree
{
	/**
	 * @var array<int, MenuItem>
	 */
	private array $items = [];

	/**
	 * Child ids per parent id, in their present order.
	 *
	 * @var array<int, list<int>>
	 */
	private array $children = [];

	private int $rootId;

	/**
	 * @param   iterable<MenuItem>  $items  Every row of #__menu.
	 *
	 * @throws  \RuntimeException  When the rows are not one tree: no root or several,
	 *                             an item whose parent is missing, or a loop.
	 */
	public function __construct(iterable $items)
	{
		$roots = [];

		foreach ($items as $item) {
			$this->items[$item->id] = $item;

			if ($item->parentId === 0) {
				$roots[] = $item->id;
			}
		}

		if (\count($roots) !== 1) {
			throw new \RuntimeException(\sprintf('The menu table has %d root items; it should have exactly one.', \count($roots)));
		}

		$this->rootId = $roots[0];

		foreach ($this->items as $item) {
			if ($item->id === $this->rootId) {
				continue;
			}

			if (!isset($this->items[$item->parentId])) {
				throw new \RuntimeException(\sprintf('Menu item %d has parent %d, which does not exist.', $item->id, $item->parentId));
			}

			$this->children[$item->parentId][] = $item->id;
		}

		foreach ($this->children as &$ids) {
			usort($ids, fn (int $a, int $b): int => [$this->items[$a]->lft, $a] <=> [$this->items[$b]->lft, $b]);
		}

		unset($ids);

		// Every item must hang from the root; a loop of parents does not.
		$reached = 0;
		$this->walk($this->rootId, static function () use (&$reached): void {
			$reached++;
		});

		if ($reached !== \count($this->items)) {
			throw new \RuntimeException(\sprintf('%d menu items cannot be reached from the root.', \count($this->items) - $reached));
		}
	}

	public function rootId(): int
	{
		return $this->rootId;
	}

	public function has(int $id): bool
	{
		return isset($this->items[$id]);
	}

	public function item(int $id): MenuItem
	{
		return $this->items[$id] ?? throw new \OutOfBoundsException(\sprintf('No menu item %d.', $id));
	}

	/**
	 * @return  list<MenuItem>  The children of an item, in their present order.
	 */
	public function children(int $parentId): array
	{
		return array_map(fn (int $id): MenuItem => $this->items[$id], $this->children[$parentId] ?? []);
	}

	/**
	 * @return  list<MenuItem>  The items of one menu type, in tree order.
	 */
	public function menu(string $menutype): array
	{
		$items = [];

		$this->walk($this->rootId, function (int $id) use ($menutype, &$items): void {
			if ($this->items[$id]->menutype === $menutype) {
				$items[] = $this->items[$id];
			}
		});

		return $items;
	}

	/**
	 * @return  list<string>  The menu types that have items, in the order their first item comes.
	 */
	public function menutypes(): array
	{
		$menutypes = [];

		$this->walk($this->rootId, function (int $id) use (&$menutypes): void {
			$menutypes[$this->items[$id]->menutype] = true;
		});

		unset($menutypes['']);

		return array_map('strval', array_keys($menutypes));
	}

	/**
	 * Apply new orders of the children of some parents, given per parent for the
	 * children of one menu type only, to the full lists of children.
	 *
	 * The items of the menu type take the places its items held; the children of other
	 * menu types (at the root, the top level of every other menu) stay where they are.
	 *
	 * @param   array<int, list<int>>  $orders  The full orders so far, per parent id.
	 * @param   array<int, list<int>>  $group   Per parent id, a new order of some of its children.
	 *
	 * @return  array<int, list<int>>  The full orders with these applied.
	 */
	public function merge(array $orders, array $group): array
	{
		foreach ($group as $parentId => $ids) {
			$full    = $orders[$parentId] ?? $this->children[$parentId] ?? [];
			$members = array_flip($ids);
			$next    = 0;

			foreach ($full as $index => $id) {
				if (isset($members[$id])) {
					$full[$index] = $ids[$next++];
				}
			}

			if ($next !== \count($ids)) {
				throw new \InvalidArgumentException(
					\sprintf('The new order for the children of menu item %d names items that are not its children.', $parentId)
				);
			}

			$orders[$parentId] = $full;
		}

		return $orders;
	}

	/**
	 * The lft and rgt every item gets when some sibling groups are put in a new order.
	 *
	 * @param   array<int, list<int>>  $orders  Per parent id, the ids of all its children
	 *                                          in their new order.
	 *
	 * @return  array<int, array{lft: int, rgt: int}>  Per item id, its new numbers; only
	 *                                                 the items whose numbers change.
	 *
	 * @throws  \InvalidArgumentException  When an order is not exactly the parent's children.
	 */
	public function renumber(array $orders): array
	{
		foreach ($orders as $parentId => $ids) {
			$present = $this->children[$parentId] ?? [];
			$given   = $ids;

			sort($present);
			sort($given);

			if ($present !== $given) {
				throw new \InvalidArgumentException(
					\sprintf('The new order for the children of menu item %d is not the same set of items.', $parentId)
				);
			}
		}

		$numbers = [];
		$this->number($this->rootId, $this->items[$this->rootId]->lft, $orders, $numbers);

		$changed = [];

		foreach ($numbers as $id => $pair) {
			if ($pair['lft'] !== $this->items[$id]->lft || $pair['rgt'] !== $this->items[$id]->rgt) {
				$changed[$id] = $pair;
			}
		}

		return $changed;
	}

	/**
	 * Whether the stored lft and rgt are those of the tree: what renumber([]) changes.
	 *
	 * @return  integer  The number of items whose stored lft or rgt is off.
	 */
	public function inconsistencies(): int
	{
		return \count($this->renumber([]));
	}

	/**
	 * @param   array<int, list<int>>                  $orders
	 * @param   array<int, array{lft: int, rgt: int}>  $numbers
	 *
	 * @return  integer  The rgt of the item.
	 */
	private function number(int $id, int $lft, array $orders, array &$numbers): int
	{
		$next = $lft + 1;

		foreach ($orders[$id] ?? $this->children[$id] ?? [] as $childId) {
			$next = $this->number($childId, $next, $orders, $numbers) + 1;
		}

		$numbers[$id] = ['lft' => $lft, 'rgt' => $next];

		return $next;
	}

	/**
	 * Depth first, parents before children, siblings in their present order.
	 */
	private function walk(int $id, callable $visit): void
	{
		$visit($id);

		foreach ($this->children[$id] ?? [] as $childId) {
			$this->walk($childId, $visit);
		}
	}
}

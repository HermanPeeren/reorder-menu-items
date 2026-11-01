<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Order;

use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuItem;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Works out the order a menu should have.
 *
 * A translated menu follows the source menu: under every parent, its items come in the
 * order of the source items they are associated with. An item without one keeps its
 * place relative to the other such items, after those that have one.
 *
 * Under the parent of an AlphabeticalRule the children are in alphabetical order of
 * their own titles instead, in the menu's language: the sticky one first, then the
 * sorted ones in the places the sorted ones held. That goes for the source menu too.
 *
 * Only a menu's own items are ordered. Under the root, where the top level of every
 * menu hangs, the items of other menus are left out of the plan.
 */
final class MenuOrderPlanner
{
	/**
	 * @param   MenuTree               $tree
	 * @param   string                 $sourceMenutype  The menu the others follow, "mainmenu".
	 * @param   array<int, int>        $sourceOf        Per translated item id, the associated item in the
	 *                                                  source menu.
	 * @param   list<AlphabeticalRule> $rules
	 */
	public function __construct(
		private readonly MenuTree $tree,
		private readonly string $sourceMenutype,
		private readonly array $sourceOf,
		private readonly array $rules
	) {
	}

	public function plan(string $menutype, TitleCollator $collator): MenuPlan
	{
		$isSource   = $menutype === $this->sourceMenutype;
		$orders     = [];
		$listOrders = [];
		$unmatched  = [];
		$parents    = [$this->tree->rootId()];

		foreach ($this->tree->menu($menutype) as $item) {
			$parents[] = $item->id;
		}

		foreach ($parents as $parentId) {
			$group = array_values(array_filter(
				$this->tree->children($parentId),
				static fn (MenuItem $child): bool => $child->menutype === $menutype
			));

			if ($group === []) {
				continue;
			}

			$present = array_map(static fn (MenuItem $child): int => $child->id, $group);

			if ($isSource) {
				$order = $present;
			} else {
				[$order, $missing] = $this->followSource($group);
				array_push($unmatched, ...$missing);
			}

			$rule = $this->ruleFor($parentId, $isSource);

			if ($rule !== null) {
				$order = $this->alphabetical($order, $rule, $collator, $isSource);

				if ($rule->sortArticleList) {
					$listOrders[$parentId] = $order;
				}
			}

			if ($order !== $present) {
				$orders[$parentId] = $order;
			}
		}

		return new MenuPlan($menutype, $orders, $listOrders, $unmatched);
	}

	/**
	 * The source item of an item of the menu being planned.
	 */
	private function source(int $id, bool $isSource): ?MenuItem
	{
		$sourceId = $isSource ? $id : ($this->sourceOf[$id] ?? null);

		if ($sourceId === null || !$this->tree->has($sourceId)) {
			return null;
		}

		$source = $this->tree->item($sourceId);

		return $source->menutype === $this->sourceMenutype ? $source : null;
	}

	/**
	 * @param   list<MenuItem>  $group
	 *
	 * @return  array{0: list<int>, 1: list<int>}  The order, and the items without a source.
	 */
	private function followSource(array $group): array
	{
		$matched = [];
		$missing = [];

		foreach ($group as $index => $item) {
			$source = $this->source($item->id, false);

			if ($source === null) {
				$missing[] = $item->id;
			} else {
				$matched[] = ['id' => $item->id, 'key' => [$source->lft, $index]];
			}
		}

		usort($matched, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);

		return [array_merge(array_column($matched, 'id'), $missing), $missing];
	}

	private function ruleFor(int $parentId, bool $isSource): ?AlphabeticalRule
	{
		if ($parentId === $this->tree->rootId()) {
			return null;
		}

		$source = $this->source($parentId, $isSource);

		if ($source === null) {
			return null;
		}

		foreach ($this->rules as $rule) {
			if ($rule->parentPath === $source->path) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * @param   list<int>  $order
	 *
	 * @return  list<int>
	 */
	private function alphabetical(array $order, AlphabeticalRule $rule, TitleCollator $collator, bool $isSource): array
	{
		// The sticky item first.
		foreach ($order as $index => $id) {
			if ($rule->stickyPath !== null && $this->source($id, $isSource)?->path === $rule->stickyPath) {
				unset($order[$index]);
				array_unshift($order, $id);
				break;
			}
		}

		$order = array_values($order);
		$slots = [];

		foreach ($order as $index => $id) {
			$source = $this->source($id, $isSource);

			if ($source === null || $source->path === $rule->stickyPath) {
				continue;
			}

			if ($rule->onlyMatching !== null && !preg_match($rule->onlyMatching, $source->title)) {
				continue;
			}

			$slots[$index] = $id;
		}

		$sorted = array_values($slots);
		usort($sorted, fn (int $a, int $b): int => $collator->compare($this->tree->item($a)->title, $this->tree->item($b)->title));

		foreach (array_keys($slots) as $n => $index) {
			$order[$index] = $sorted[$n];
		}

		return array_values($order);
	}
}

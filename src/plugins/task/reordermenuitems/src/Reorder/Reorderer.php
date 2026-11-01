<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Reorder;

use Yepr\Plugin\Task\ReorderMenuItems\Html\ListReorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuItem;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;
use Yepr\Plugin\Task\ReorderMenuItems\Order\AlphabeticalRule;
use Yepr\Plugin\Task\ReorderMenuItems\Order\MenuOrderPlanner;
use Yepr\Plugin\Task\ReorderMenuItems\Order\MenuPlan;
use Yepr\Plugin\Task\ReorderMenuItems\Order\TitleCollator;
use Yepr\Plugin\Task\ReorderMenuItems\Store\MenuStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One run: plan the order of every main menu, write it, and order the lists in the
 * articles that go with an alphabetical rule.
 *
 * The translated main menus are the menu types "<source>-<language>", as Translator
 * Feedback names them ("mainmenu-de-de"). Every main menu is planned before anything
 * is written, and the whole new order is written in one transaction, then read back
 * and checked.
 */
final class Reorderer
{
	/**
	 * @var callable(string, string): void
	 */
	private $log;

	/**
	 * @param   list<AlphabeticalRule>          $rules
	 * @param   callable(string, string): void  $log  Message and priority ("info", "warning", "error").
	 */
	public function __construct(
		private readonly MenuStore $store,
		private readonly ListReorderer $lists,
		private readonly array $rules,
		callable $log,
		private readonly string $sourceMenutype = 'mainmenu'
	) {
		$this->log = $log;
	}

	public function run(bool $dryRun, bool $includeSource, int $userId): Outcome
	{
		$tree = $this->store->tree();

		$broken = $tree->inconsistencies();

		if ($broken > 0) {
			$this->say(\sprintf('%d menu items have lft/rgt values that do not match the tree; they are renumbered too.', $broken), 'warning');
		}

		$menutypes = $this->translatedMenutypes($tree);

		if ($includeSource) {
			array_unshift($menutypes, $this->sourceMenutype);
		}

		$sourceOf = $this->sourceOf($tree);
		$planner  = new MenuOrderPlanner($tree, $this->sourceMenutype, $sourceOf, $this->rules);
		$plans   = [];
		$orders  = [];
		$groups  = 0;

		foreach ($menutypes as $menutype) {
			$plan    = $planner->plan($menutype, new TitleCollator($this->languageOf($tree, $menutype)));
			$plans[] = $plan;
			$orders  = $tree->merge($orders, $plan->orders);
			$groups += \count($plan->orders);

			$this->report($tree, $plan);
		}

		$numbers = $tree->renumber($orders);

		if (!$dryRun && $numbers !== []) {
			$this->store->writeNumbers($numbers);
			$this->verify($orders);
		}

		$articles = 0;

		foreach ($plans as $plan) {
			$articles += $this->orderArticleLists($tree, $plan, $sourceOf, $dryRun, $userId);
		}

		$outcome = new Outcome(\count($menutypes), $groups, \count($numbers), $articles);

		$this->say(\sprintf(
			'%s %d menus: %d groups of menu items %s a new order (%d rows of #__menu), %d articles %s a list in a new order.',
			$dryRun ? 'Dry run, nothing saved. Checked' : 'Done. Checked',
			$outcome->menus,
			$outcome->groups,
			$dryRun ? 'would get' : 'got',
			$outcome->rows,
			$outcome->articles,
			$dryRun ? 'would get' : 'got'
		), 'info');

		return $outcome;
	}

	/**
	 * @return  list<string>
	 */
	private function translatedMenutypes(MenuTree $tree): array
	{
		$pattern = '/^' . preg_quote($this->sourceMenutype, '/') . '-[a-z]{2,3}-[a-z0-9]{2,4}$/';

		return array_values(array_filter($tree->menutypes(), static fn (string $menutype): bool => (bool) preg_match($pattern, $menutype)));
	}

	/**
	 * Per translated item, the item of the source menu in its association group.
	 *
	 * @return  array<int, int>
	 */
	private function sourceOf(MenuTree $tree): array
	{
		$sourceOf = [];

		foreach ($this->store->menuAssociations() as $group) {
			$sources = array_values(array_filter(
				$group,
				fn (int $id): bool => $tree->has($id) && $tree->item($id)->menutype === $this->sourceMenutype
			));

			if (\count($sources) !== 1) {
				continue;
			}

			foreach ($group as $id) {
				if ($id !== $sources[0]) {
					$sourceOf[$id] = $sources[0];
				}
			}
		}

		return $sourceOf;
	}

	private function languageOf(MenuTree $tree, string $menutype): string
	{
		foreach ($tree->menu($menutype) as $item) {
			if ($item->language !== '*') {
				return $item->language;
			}
		}

		// "mainmenu-de-de" is German.
		$suffix = substr($menutype, \strlen($this->sourceMenutype) + 1);

		return $suffix === '' ? 'en-GB' : strtolower(substr($suffix, 0, 2)) . '-' . strtoupper(substr($suffix, 3));
	}

	private function report(MenuTree $tree, MenuPlan $plan): void
	{
		foreach ($plan->orders as $parentId => $ids) {
			$parent = $parentId === $tree->rootId() ? '(top level)' : $tree->item($parentId)->title;
			$titles = array_map(static fn (int $id): string => $tree->item($id)->title, $ids);

			$this->say(\sprintf('%s, %s: %s', $plan->menutype, $parent, implode(' | ', $titles)), 'info');
		}

		foreach ($plan->unmatched as $id) {
			$item = $tree->item($id);

			$this->say(\sprintf(
				'%s: "%s" (%d) is associated with no item of %s; it stays after its siblings that are.',
				$plan->menutype,
				$item->title,
				$id,
				$this->sourceMenutype
			), 'warning');
		}
	}

	/**
	 * Read the menus back: the tree is whole and every group is in its new order.
	 *
	 * @param   array<int, list<int>>  $orders
	 *
	 * @throws  \RuntimeException
	 */
	private function verify(array $orders): void
	{
		$tree = $this->store->tree();

		if ($tree->inconsistencies() !== 0) {
			throw new \RuntimeException('after writing, the menu tree is not consistent; rebuild it in Menus > All Menu Items.');
		}

		foreach ($orders as $parentId => $ids) {
			if (array_map(static fn (MenuItem $item): int => $item->id, $tree->children($parentId)) !== $ids) {
				throw new \RuntimeException(\sprintf('after writing, the children of menu item %d are not in the new order.', $parentId));
			}
		}
	}

	/**
	 * @param   array<int, int>  $sourceOf
	 *
	 * @return  integer  The number of articles whose list (would have) changed.
	 */
	private function orderArticleLists(MenuTree $tree, MenuPlan $plan, array $sourceOf, bool $dryRun, int $userId): int
	{
		$changed = 0;

		foreach ($plan->listOrders as $parentId => $ids) {
			$parent = $tree->item($parentId);

			$opensArticle = preg_match('/[?&]option=com_content\b/', $parent->link) && preg_match('/[?&]view=article\b/', $parent->link);

			if (!$opensArticle || !preg_match('/[?&]id=(\d+)/', $parent->link, $match)) {
				$this->say(\sprintf('%s: "%s" opens no article, so there is no list to order.', $plan->menutype, $parent->title), 'warning');

				continue;
			}

			$article = $this->store->article((int) $match[1]);

			if ($article === null) {
				$this->say(\sprintf('%s: "%s" opens article %d, which does not exist.', $plan->menutype, $parent->title, (int) $match[1]), 'warning');

				continue;
			}

			// A link in a translation can still point at the source menu's item.
			$rank = [];

			foreach ($ids as $index => $id) {
				$rank[$id] = $index;

				if (isset($sourceOf[$id]) && !isset($rank[$sourceOf[$id]])) {
					$rank[$sourceOf[$id]] = $index;
				}
			}

			$intro = $this->lists->reorder($article->introtext, $rank);
			$full  = $this->lists->reorder($article->fulltext, $rank);

			if ($intro['lists'] + $full['lists'] === 0) {
				continue;
			}

			$this->say(\sprintf(
				'%s: article "%s" (%d, %s): %d list(s) %s in the order of the menu.',
				$plan->menutype,
				$article->title,
				$article->id,
				$article->language,
				$intro['lists'] + $full['lists'],
				$dryRun ? 'would be put' : 'put'
			), 'info');

			if ($dryRun) {
				$changed++;

				continue;
			}

			if ($article->checkedOut) {
				$this->say(\sprintf(
					'Article "%s" (%d) is checked out, so it is not changed; check it in and run the task again.',
					$article->title,
					$article->id
				), 'warning');

				continue;
			}

			$this->store->saveArticle($article, $intro['html'], $full['html'], $userId, 'Reorder menu items: list in the order of the menu');
			$changed++;
		}

		return $changed;
	}

	private function say(string $message, string $priority): void
	{
		($this->log)($message, $priority);
	}
}

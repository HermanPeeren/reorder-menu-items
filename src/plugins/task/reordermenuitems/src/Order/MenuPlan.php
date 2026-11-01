<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Order;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The order one menu should have.
 */
final readonly class MenuPlan
{
	/**
	 * @param   string                 $menutype
	 * @param   array<int, list<int>>  $orders      Per parent id, the menu's own children under it in
	 *                                              their new order; only the parents where it changes.
	 * @param   array<int, list<int>>  $listOrders  Per parent id of a rule that sorts its article's list,
	 *                                              its children in their new order (changed or not).
	 * @param   list<int>              $unmatched   Items without an associated source item, left after
	 *                                              their siblings that have one.
	 */
	public function __construct(
		public string $menutype,
		public array $orders,
		public array $listOrders,
		public array $unmatched
	) {
	}
}

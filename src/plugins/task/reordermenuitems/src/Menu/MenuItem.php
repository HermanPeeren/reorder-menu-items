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
 * One row of #__menu, as far as its place in the tree goes.
 */
final readonly class MenuItem
{
	public function __construct(
		public int $id,
		public int $parentId,
		public int $lft,
		public int $rgt,
		public string $menutype = '',
		public string $title = '',
		public string $alias = '',
		public string $path = '',
		public string $language = '*',
		public string $link = ''
	) {
	}
}

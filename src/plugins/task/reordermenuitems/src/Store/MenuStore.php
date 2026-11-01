<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Store;

use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What the reordering reads from and writes to the site.
 */
interface MenuStore
{
	/**
	 * The whole of #__menu.
	 */
	public function tree(): MenuTree;

	/**
	 * @return  list<list<int>>  The association groups of menu items: per group, its item ids.
	 */
	public function menuAssociations(): array;

	/**
	 * Write new lft and rgt values, all or none.
	 *
	 * @param   array<int, array{lft: int, rgt: int}>  $numbers  Per menu item id.
	 *
	 * @throws  \RuntimeException  When the write fails; nothing is changed then.
	 */
	public function writeNumbers(array $numbers): void;

	public function article(int $id): ?Article;

	/**
	 * Save an article's new text, with a version in its history.
	 *
	 * @throws  \RuntimeException  When the article is not saved.
	 */
	public function saveArticle(Article $article, string $introtext, string $fulltext, int $userId, string $note): void;
}

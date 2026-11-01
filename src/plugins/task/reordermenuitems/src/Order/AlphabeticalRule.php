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
 * A menu item whose children are in alphabetical order, in every language, instead of
 * in the order of the source menu.
 *
 * Only the children directly under it, not theirs. Items are named by their path in
 * the source menu, so a rule holds for the translations of that item too.
 */
final readonly class AlphabeticalRule
{
	/**
	 * @param   string       $parentPath   The path of the item in the source menu.
	 * @param   string|null  $stickyPath   The path of a child that stays on top, before the sorted ones.
	 * @param   string|null  $onlyMatching A pattern for the source titles of the children that are
	 *                                     sorted; the others keep their place. Null sorts all.
	 * @param   bool         $sortArticleList  Whether the list of these children in the article the
	 *                                         item opens follows the same order.
	 */
	public function __construct(
		public string $parentPath,
		public ?string $stickyPath = null,
		public ?string $onlyMatching = null,
		public bool $sortArticleList = false
	) {
	}

	/**
	 * The rules of the User Guide (guide.joomla.org).
	 *
	 * @return  list<self>
	 */
	public static function userGuide(): array
	{
		return [
			// User Guide / Explanations, with Getting Started on top.
			new self('user-guide', 'user-guide/getting-started'),
			// Admin Pages References, with Help Screens on top.
			new self('help-pages-references', 'help-pages-references/help-screens'),
			// The field types under Fields (Calendar Field ... Usergroup Field); the pages
			// before and after them stay where they are. The overview lists them too.
			new self('user-guide/fields', null, '/\sField$/', true),
		];
	}
}

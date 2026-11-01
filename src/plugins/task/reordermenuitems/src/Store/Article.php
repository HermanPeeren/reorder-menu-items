<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Store;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * An article, as far as its text goes.
 */
final readonly class Article
{
	public function __construct(
		public int $id,
		public string $title,
		public string $language,
		public string $introtext,
		public string $fulltext,
		public bool $checkedOut = false
	) {
	}
}

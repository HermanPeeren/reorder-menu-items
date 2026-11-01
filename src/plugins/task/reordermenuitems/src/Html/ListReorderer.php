<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Html;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Puts the entries of a list of menu item links in the order of those menu items.
 *
 * A list qualifies when each of its entries links to a menu item that has a rank
 * (index.php?Itemid=N), and it has at least two. Only the order of the <li> elements
 * changes: each moves as it is, with everything in it, and the whitespace between them
 * stays where it was. A list with anything else between its entries, or with a list
 * inside it, is left alone.
 */
final class ListReorderer
{
	/**
	 * @param   array<int, int>  $rank  Per menu item id, its place in the new order.
	 *
	 * @return  array{html: string, lists: int}  The HTML, and how many lists changed.
	 */
	public function reorder(string $html, array $rank): array
	{
		$lists = 0;

		$result = preg_replace_callback(
			'#(<ul\b[^>]*>)(.*?)(</ul>)#is',
			function (array $match) use ($rank, &$lists): string {
				$inner = $this->reorderEntries($match[2], $rank);

				if ($inner === null) {
					return $match[0];
				}

				$lists++;

				return $match[1] . $inner . $match[3];
			},
			$html
		);

		return ['html' => $result ?? $html, 'lists' => $lists];
	}

	/**
	 * @param   array<int, int>  $rank
	 *
	 * @return  string|null  The entries in their new order; null when the list does not
	 *                       qualify or is in that order already.
	 */
	private function reorderEntries(string $inner, array $rank): ?string
	{
		if (preg_match('#<(ul|ol)\b#i', $inner)) {
			return null;
		}

		$parts = preg_split('#(<li\b[^>]*>.*?</li>)#is', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);

		if ($parts === false) {
			return null;
		}

		$entries = [];

		foreach ($parts as $index => $part) {
			// Odd parts are the entries, even parts what is between them.
			if ($index % 2 === 0) {
				if (trim($part) !== '') {
					return null;
				}

				continue;
			}

			if (!preg_match('#\bItemid=(\d+)#', $part, $itemid) || !isset($rank[(int) $itemid[1]])) {
				return null;
			}

			$entries[$index] = ['html' => $part, 'rank' => $rank[(int) $itemid[1]], 'at' => $index];
		}

		if (\count($entries) < 2) {
			return null;
		}

		$sorted = array_values($entries);
		usort($sorted, static fn (array $a, array $b): int => [$a['rank'], $a['at']] <=> [$b['rank'], $b['at']]);

		if (array_column($sorted, 'at') === array_keys($entries)) {
			return null;
		}

		foreach (array_keys($entries) as $n => $index) {
			$parts[$index] = $sorted[$n]['html'];
		}

		return implode('', $parts);
	}
}

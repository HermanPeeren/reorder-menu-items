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
 * Compares titles alphabetically, the way a reader of the language expects.
 *
 * With PHP's intl extension, by the rules of the language (Ä beside A in German, Persian
 * letters in Persian order). Without it, case and accents are ignored and the rest is
 * compared by code point, which is right for most Latin and Cyrillic titles.
 *
 * Quotation marks do not count, in any language: "Campo 'Editor'" sorts as "Campo
 * Editor", "Поле «Календарь»" as "Поле Календарь". Spaces do count, so "Text Field"
 * comes before "Textarea Field".
 */
final class TitleCollator
{
	/**
	 * Quotation marks of the languages of the User Guide, ASCII and typographic.
	 */
	private const QUOTES = ['"', "'", '`', '«', '»', '‹', '›', '„', '‚', '“', '”', '‘', '’'];

	private ?\Collator $collator = null;

	public function __construct(string $language, bool $useIntl = true)
	{
		if ($useIntl && class_exists(\Collator::class)) {
			$collator = new \Collator(str_replace('-', '_', $language));
			$collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
			$this->collator = $collator;
		}
	}

	public function compare(string $a, string $b): int
	{
		$keyA = self::unquote($a);
		$keyB = self::unquote($b);

		if ($this->collator !== null) {
			$result = $this->collator->compare($keyA, $keyB);

			if (\is_int($result) && $result !== 0) {
				return $result;
			}
		} else {
			$result = strcmp(self::fold($keyA), self::fold($keyB));

			if ($result !== 0) {
				return $result <=> 0;
			}
		}

		// Equal for a reader: the exact strings decide, so the order is always the same.
		return strcmp($a, $b) <=> 0;
	}

	private static function unquote(string $title): string
	{
		return trim((string) preg_replace('/\s+/u', ' ', str_replace(self::QUOTES, '', $title)));
	}

	private static function fold(string $title): string
	{
		$title = mb_strtolower(trim($title));

		if (class_exists(\Normalizer::class)) {
			$decomposed = \Normalizer::normalize($title, \Normalizer::FORM_D);

			if (\is_string($decomposed)) {
				$title = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
			}
		}

		return strtr($title, ['ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'đ' => 'd', 'ł' => 'l']);
	}
}

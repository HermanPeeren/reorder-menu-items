<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Store;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Yepr\Plugin\Task\ReorderMenuItems\Content\AdminModelSaver;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuItem;
use Yepr\Plugin\Task\ReorderMenuItems\Menu\MenuTree;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The menus on #__menu and #__associations, articles on #__content.
 *
 * The new lft and rgt values are written straight into #__menu, in one transaction:
 * the menu item model can only move one item at a time, and every move renumbers half
 * the table. Articles are saved through com_content's model, so they get a version.
 */
final class DatabaseMenuStore implements MenuStore
{
	public function __construct(
		private readonly DatabaseInterface $db,
		private readonly AdminModelSaver $saver
	) {
	}

	public function tree(): MenuTree
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'parent_id', 'lft', 'rgt', 'menutype', 'title', 'alias', 'path', 'language', 'link']))
			->from($db->quoteName('#__menu'));

		$items = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$items[] = new MenuItem(
				(int) $row['id'],
				(int) $row['parent_id'],
				(int) $row['lft'],
				(int) $row['rgt'],
				(string) $row['menutype'],
				(string) $row['title'],
				(string) $row['alias'],
				(string) $row['path'],
				(string) $row['language'],
				(string) $row['link']
			);
		}

		return new MenuTree($items);
	}

	public function menuAssociations(): array
	{
		$db      = $this->db;
		$context = 'com_menus.item';
		$query   = $db->getQuery(true)
			->select($db->quoteName(['id', 'key']))
			->from($db->quoteName('#__associations'))
			->where($db->quoteName('context') . ' = :context')
			->bind(':context', $context);

		$groups = [];

		foreach ($db->setQuery($query)->loadAssocList() as $row) {
			$groups[(string) $row['key']][] = (int) $row['id'];
		}

		return array_values($groups);
	}

	public function writeNumbers(array $numbers): void
	{
		if ($numbers === []) {
			return;
		}

		$db = $this->db;
		$db->transactionStart();

		try {
			foreach ($numbers as $id => $pair) {
				$lft   = $pair['lft'];
				$rgt   = $pair['rgt'];
				$query = $db->getQuery(true)
					->update($db->quoteName('#__menu'))
					->set($db->quoteName('lft') . ' = :lft')
					->set($db->quoteName('rgt') . ' = :rgt')
					->where($db->quoteName('id') . ' = :id')
					->bind(':lft', $lft, ParameterType::INTEGER)
					->bind(':rgt', $rgt, ParameterType::INTEGER)
					->bind(':id', $id, ParameterType::INTEGER);

				$db->setQuery($query)->execute();
			}

			$db->transactionCommit();
		} catch (\Throwable $e) {
			$db->transactionRollback();

			throw new \RuntimeException('writing the new menu order failed, nothing was changed: ' . $e->getMessage(), 0, $e);
		}
	}

	public function article(int $id): ?Article
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'title', 'language', 'introtext', 'fulltext', 'checked_out']))
			->from($db->quoteName('#__content'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$row = $db->setQuery($query)->loadAssoc();

		if (!\is_array($row)) {
			return null;
		}

		return new Article(
			(int) $row['id'],
			(string) $row['title'],
			(string) $row['language'],
			(string) $row['introtext'],
			(string) $row['fulltext'],
			(int) $row['checked_out'] > 0
		);
	}

	public function saveArticle(Article $article, string $introtext, string $fulltext, int $userId, string $note): void
	{
		$db    = $this->db;
		$id    = $article->id;
		$query = $db->getQuery(true)
			->select($db->quoteName('catid'))
			->from($db->quoteName('#__content'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		// The model reads the category on every save, so it has to be sent along.
		$this->saver->save('com_content', 'Article', [
			'id'           => $id,
			'catid'        => (int) $db->setQuery($query)->loadResult(),
			'introtext'    => $introtext,
			'fulltext'     => $fulltext,
			'version_note' => $note,
		], $userId);
	}
}

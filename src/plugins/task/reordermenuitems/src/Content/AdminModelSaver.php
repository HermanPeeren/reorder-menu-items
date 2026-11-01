<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Content;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\User\UserFactoryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Saves through a component's own administrator model, a fresh one per save.
 *
 * Through the component's own MVC factory, so the model is wired exactly as when its
 * editor saves: content plugins, workflow, Smart Search and version history take part.
 * A fresh model per save, because a model keeps state (the item id) from the last one.
 */
final class AdminModelSaver
{
	public function __construct(
		private readonly CMSApplicationInterface $app,
		private readonly UserFactoryInterface $users
	) {
	}

	/**
	 * @param   string                               $component  For example "com_content".
	 * @param   string                               $name       The model, for example "Article".
	 * @param   array<string, mixed>                 $data       What the editor's form would send.
	 * @param   int                                  $userId     The user the save is recorded for; 0 for none.
	 * @param   (callable(AdminModel): void)|null    $prepare    Sets up the model before saving.
	 *
	 * @throws  \RuntimeException  When the model is not there, or does not save.
	 */
	public function save(string $component, string $name, array $data, int $userId, ?callable $prepare = null): void
	{
		$booted = $this->app->bootComponent($component);

		if (!$booted instanceof MVCFactoryServiceInterface) {
			throw new \RuntimeException($component . ' has no MVC factory.');
		}

		$model = $booted->getMVCFactory()->createModel($name, 'Administrator', ['ignore_request' => true]);

		if (!$model instanceof AdminModel) {
			throw new \RuntimeException(\sprintf('%s has no %s model.', $component, $name));
		}

		if ($userId > 0) {
			$user = $this->users->loadUserById($userId);

			if ((int) $user->id !== $userId) {
				throw new \RuntimeException(\sprintf('There is no user with id %d.', $userId));
			}

			$model->setCurrentUser($user);
		}

		if ($prepare !== null) {
			$prepare($model);
		}

		// The model returns false for a save that its own checks or a plugin refused, and
		// throws on a database error. Joomla 6 deprecates asking a model for the reason
		// (getError), so a refusal is reported without one.
		try {
			$saved = $model->save($data);
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), 0, $e);
		}

		if (!$saved) {
			throw new \RuntimeException(\sprintf('the %s model refused to save', strtolower($name)));
		}
	}
}

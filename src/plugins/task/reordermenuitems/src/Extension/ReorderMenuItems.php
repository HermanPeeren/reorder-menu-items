<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\ReorderMenuItems\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Yepr\Plugin\Task\ReorderMenuItems\Html\ListReorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Order\AlphabeticalRule;
use Yepr\Plugin\Task\ReorderMenuItems\Reorder\Reorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Store\MenuStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Offers the task type "Reorder menu items: translated main menus" to the Task Scheduler.
 *
 * The work is in Reorderer; this class hands it the task's parameters and turns the
 * outcome into an exit code. A run takes a few seconds even for a dozen menus of
 * hundreds of items, so it is done in one go.
 */
final class ReorderMenuItems extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var array<string, array<string, string>>
	 */
	protected const TASKS_MAP = [
		'reordermenuitems.reorder' => [
			'langConstPrefix' => 'PLG_TASK_REORDERMENUITEMS_REORDER',
			'form'            => 'reorder',
			'method'          => 'reorder',
		],
	];

	/**
	 * @var boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * @param   array<string, mixed>  $config  The plugin's row, from PluginHelper::getPlugin().
	 */
	public function __construct(
		array $config,
		private readonly MenuStore $store,
		private readonly ListReorderer $lists
	) {
		parent::__construct($config);
	}

	/**
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The reorder routine.
	 *
	 * @return  integer  A TaskStatus exit code.
	 */
	protected function reorder(ExecuteTaskEvent $event): int
	{
		$params = $event->getArgument('params');
		$params = \is_object($params) ? $params : (object) [];

		$reorderer = new Reorderer(
			$this->store,
			$this->lists,
			AlphabeticalRule::userGuide(),
			function (string $message, string $priority): void {
				$this->logTask($message, $priority);
			}
		);

		try {
			$reorderer->run(
				(bool) ($params->dry_run ?? true),
				(bool) ($params->source_menu ?? true),
				(int) ($params->as_user ?? 0)
			);
		} catch (\RuntimeException $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_REORDERMENUITEMS_LOG_FAILED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}

		return TaskStatus::OK;
	}
}

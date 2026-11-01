<?php

/**
 * @package     ReorderMenuItems
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Plugin\Task\ReorderMenuItems\Content\AdminModelSaver;
use Yepr\Plugin\Task\ReorderMenuItems\Extension\ReorderMenuItems;
use Yepr\Plugin\Task\ReorderMenuItems\Html\ListReorderer;
use Yepr\Plugin\Task\ReorderMenuItems\Store\DatabaseMenuStore;
use Yepr\Plugin\Task\ReorderMenuItems\Store\MenuStore;

/**
 * The composition root: the only place the plugin's services are put together.
 *
 * Every service is shared and built on first use, and the plugin itself is a lazy
 * proxy (on PHP 8.4 and later): a page that runs no task builds none of it.
 */
return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->share(
			MenuStore::class,
			static fn (Container $container): MenuStore => new DatabaseMenuStore(
				$container->get(DatabaseInterface::class),
				new AdminModelSaver(Factory::getApplication(), $container->get(UserFactoryInterface::class))
			)
		);

		$container->share(ListReorderer::class, static fn (): ListReorderer => new ListReorderer());

		$container->set(
			PluginInterface::class,
			$container->lazy(ReorderMenuItems::class, function (Container $container) {
				$plugin = new ReorderMenuItems(
					(array) PluginHelper::getPlugin('task', 'reordermenuitems'),
					$container->get(MenuStore::class),
					$container->get(ListReorderer::class)
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			})
		);
	}
};

<?php
/**
 * @copyright Copyright (c) 2020
 *
 * @author Josua Hunziker <der@digitalwerker.ch>
 *
 * Based on the work of Ján Stibila <nextcloud@stibila.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\AutoGroups\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;

use OCA\AutoGroups\AutoGroupsManager;
use OCA\AutoGroups\Listener\AutoGroupsListener;

class Application extends App implements IBootstrap
{
	public function __construct()
	{
		parent::__construct('auto_groups');
	}

	public function register(IRegistrationContext $context): void
	{
		$context->registerEventListener(UserCreatedEvent::class, AutoGroupsListener::class);
		$context->registerEventListener(UserFirstTimeLoggedInEvent::class, AutoGroupsListener::class);
		$context->registerEventListener(UserAddedEvent::class, AutoGroupsListener::class);
		$context->registerEventListener(UserRemovedEvent::class, AutoGroupsListener::class);
		$context->registerEventListener(PostLoginEvent::class, AutoGroupsListener::class);
		$context->registerEventListener(UserLoggedInEvent::class, AutoGroupsListener::class);
		$context->registerEventListener(BeforeGroupDeletedEvent::class, AutoGroupsListener::class);
	}

	public function boot(IBootContext $context): void
	{
		// Instantiate AutoGroupsManager to trigger legacy config migration
		$context->getAppContainer()->query(AutoGroupsManager::class);
	}
}

<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020
 *
 * @author Josua Hunziker <josh@o23.ch>
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

namespace OCA\AutoGroups\Listener;

use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;

use OCA\AutoGroups\AutoGroupsManager;

/** @template-implements IEventListener<BeforeUserDeletedEvent|UserCreatedEvent|UserFirstTimeLoggedInEvent|UserAddedEvent|UserRemovedEvent|PostLoginEvent|UserLoggedInEvent|BeforeGroupDeletedEvent> */
class AutoGroupsListener implements IEventListener
{
    public function __construct(
        private readonly AutoGroupsManager $manager,
        private readonly IAppConfig $appConfig
    ) {}

	#[\Override]
    public function handle(Event $event): void
    {
        if ($event instanceof BeforeUserDeletedEvent) {
            // Fired before the deletion removes the user from their groups. Without
            // this the removals fire UserRemovedEvent, the modification hook puts
            // the user straight back into the auto groups, and the row survives the
            // user record — see AutoGroupsManager::addAndRemoveAutoGroups().
            $this->manager->markUserAsDeleting($event->getUser()->getUID());
        } elseif ($event instanceof UserCreatedEvent || $event instanceof UserFirstTimeLoggedInEvent) {
            if ($this->appConfig->getAppValueBool('creation_hook', true)) {
                $this->manager->addAndRemoveAutoGroups($event);
            }
        } elseif ($event instanceof UserAddedEvent || $event instanceof UserRemovedEvent) {
			if ($this->appConfig->getAppValueBool('modification_hook', true)) {
                $this->manager->addAndRemoveAutoGroups($event);
            }
        } elseif ($event instanceof PostLoginEvent || $event instanceof UserLoggedInEvent) {
			if ($this->appConfig->getAppValueBool('login_hook')) {
                $this->manager->addAndRemoveAutoGroups($event);
            }
        } elseif ($event instanceof BeforeGroupDeletedEvent) {
            $this->manager->handleGroupDeletion($event);
        }
    }
}

<?php

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

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\IConfig;

use OCA\AutoGroups\AutoGroupsManager;

/** @template-implements IEventListener<Event> */
class AutoGroupsListener implements IEventListener
{
    public function __construct(
        private AutoGroupsManager $manager,
        private IConfig $config
    ) {}

    public function handle(Event $event): void
    {
        if ($event instanceof UserCreatedEvent || $event instanceof UserFirstTimeLoggedInEvent) {
            if (filter_var($this->config->getAppValue('auto_groups', 'creation_hook', 'true'), FILTER_VALIDATE_BOOLEAN)) {
                $this->manager->addAndRemoveAutoGroups($event);
            }
        } elseif ($event instanceof UserAddedEvent || $event instanceof UserRemovedEvent) {
            if (filter_var($this->config->getAppValue('auto_groups', 'modification_hook', 'true'), FILTER_VALIDATE_BOOLEAN)) {
                $this->manager->addAndRemoveAutoGroups($event);
            }
        } elseif ($event instanceof PostLoginEvent || $event instanceof UserLoggedInEvent) {
            if (filter_var($this->config->getAppValue('auto_groups', 'login_hook', 'false'), FILTER_VALIDATE_BOOLEAN)) {
                $this->manager->addAndRemoveAutoGroups($event);
            }
        } elseif ($event instanceof BeforeGroupDeletedEvent) {
            $this->manager->handleGroupDeletion($event);
        }
    }
}

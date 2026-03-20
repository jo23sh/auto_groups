<?php

/**
 * @copyright Copyright (c) 2020
 *
 * @author Josua Hunziker <josh@o23.ch>
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

namespace OCA\AutoGroups;

use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\OCS\OCSBadRequestException;

use Psr\Log\LoggerInterface;

class AutoGroupsManager
{
    private $groupManager;
    private $logger;
    private $config;
    private $l;

    /**
     * AutoGroupsManager constructor.
     */
    public function __construct(IGroupManager $groupManager, IConfig $config, LoggerInterface $logger, IL10N $l)
    {
        $this->groupManager = $groupManager;
        $this->logger = $logger;
        $this->config = $config;
        $this->l = $l;

        // Migrate old config if necessary
        $creationOnly = $this->config->getAppValue("AutoGroups", "creation_only");
        if ($creationOnly !== '') {
            $this->config->setAppValue("auto_groups", "modification_hook", ($creationOnly === 'true' ? 'false' : 'true'));
            $this->config->deleteAppValue("AutoGroups", "creation_only");
        }
        $oldCreationHook = $this->config->getAppValue("AutoGroups", "creation_hook");
        if ($oldCreationHook !== '') {
            $this->logger->info('Migrating legacy AutoGroups settings to correct App ID (GitHub issue #82)...');

            $this->config->setAppValue("auto_groups", "creation_hook", $oldCreationHook);
            $this->config->deleteAppValue("AutoGroups", "creation_hook");

            $oldModificationHook = $this->config->getAppValue("AutoGroups", "modification_hook");
            if ($oldModificationHook !== '') {
                $this->config->setAppValue("auto_groups", "modification_hook", $oldModificationHook);
                $this->config->deleteAppValue("AutoGroups", "modification_hook");
            }

            $oldLoginHook = $this->config->getAppValue("AutoGroups", "login_hook");
            if ($oldLoginHook !== '') {
                $this->config->setAppValue("auto_groups", "login_hook", $oldLoginHook);
                $this->config->deleteAppValue("AutoGroups", "login_hook");
            }

            $oldAutoGroups = $this->config->getAppValue("AutoGroups", "auto_groups");
            if ($oldAutoGroups !== '') {
                $this->config->setAppValue("auto_groups", "auto_groups", $oldAutoGroups);
                $this->config->deleteAppValue("AutoGroups", "auto_groups");
            }

            $oldOverrideGroups = $this->config->getAppValue("AutoGroups", "override_groups");
            if ($oldOverrideGroups !== '') {
                $this->config->setAppValue("auto_groups", "override_groups", $oldOverrideGroups);
                $this->config->deleteAppValue("AutoGroups", "override_groups");
            }
        }
    }

    /**
     * The event handler to check group assignment for a user
     */
    public function addAndRemoveAutoGroups($event)
    {
        // Get configuration
        $groupNames = json_decode($this->config->getAppValue("auto_groups", "auto_groups", '[]'));
        $overrideGroupNames = json_decode($this->config->getAppValue("auto_groups", "override_groups", '[]'));

        // Get user information
        $user = $event->getUser();
        $userGroupNames = array_keys($this->groupManager->getUserGroups($user));

        // Notice message for Auto Group Hook Execution
        $this->logger->debug('AutoGroups hook triggered for user ' . $user->getDisplayName());

        // Check if user belongs to any of the ignored groups
        $userInOverrideGroups = array_intersect($overrideGroupNames, $userGroupNames);
        $add = empty($userInOverrideGroups);

        // Add to / remove from auto groups
        foreach ($groupNames as $groupName) {
            $groups = $this->groupManager->search($groupName);
            foreach ($groups as $group) {
                if ($group->getGID() === $groupName) {
                    if ($add && !$group->inGroup($user)) {
                        $this->logger->notice('Add user ' . $user->getDisplayName() . ' to auto group ' . $groupName);
                        $group->addUser($user);
                    } else if (!$add && $group->inGroup($user)) {
                        $this->logger->notice('Remove user ' . $user->getDisplayName() . ' from auto group ' . $groupName);
                        $group->removeUser($user);
                    }
                }
            }
        }
    }

    /**
     * The event handler to handle group deletions
     *
     * @throws OCSBadRequestException
     *
     */
    public function handleGroupDeletion($event)
    {
        // Get all group names
        $groupNames = json_decode($this->config->getAppValue("auto_groups", "auto_groups", '[]'));
        $overrideGroupNames = json_decode($this->config->getAppValue("auto_groups", "override_groups", '[]'));

        $allGroupNames = array_merge($groupNames, $overrideGroupNames);

        // Get group name of group to delete
        $groupNameToDelete = $event->getGroup()->getGID();

        // Prevent deletion if group to delete is configured in AutoGroups
        if (in_array($groupNameToDelete, $allGroupNames)) {
            throw new OCSBadRequestException($this->l->t('Group "%1$s" is used in the Auto Groups App and cannot be deleted.', [$groupNameToDelete]));
        }
    }
}

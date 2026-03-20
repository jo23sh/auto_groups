<?php

/**
 * @copyright Copyright (c) 2020
 *
 * @author Josua Hunziker <josh@o23.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\AutoGroups\Tests\Integration;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\PostLoginEvent;

use OCP\AppFramework\OCS\OCSBadRequestException;

use Test\TestCase;
use OCA\AutoGroups\AppInfo\Application;

/**
 * @group DB
 */
class EventsTest extends TestCase
{
    private $app;
    private $container;

    private $userManager;
    private $groupManager;
    private $config;
    private $eventDispatcher;

    private $backend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application();

        $this->container = $this->app->getContainer();
        $this->groupManager = $this->container->query(IGroupManager::class);
        $this->userManager = $this->container->query(IUserManager::class);
        $this->config = $this->container->query(IConfig::class);
        $this->eventDispatcher = $this->container->query(IEventDispatcher::class);

        $this->backend = $this->groupManager->getBackends()[0];

        // Create the groups
        $this->groupManager->createGroup('autogroup1');
        $this->groupManager->createGroup('autogroup2');
        $this->groupManager->createGroup('overridegroup1');
        $this->groupManager->createGroup('overridegroup2');

        // Enable the login hook
        $this->config->setAppValue("auto_groups", "login_hook", 'true');
    }

    public function testCreateHook()
    {
        $this->config->setAppValue("auto_groups", "auto_groups", '["autogroup1"]');
        $this->config->setAppValue("auto_groups", "override_groups", '[]');
        $this->config->setAppValue("auto_groups", "login_hook", 'true');
        $this->config->setAppValue("auto_groups", "creation_hook", 'true');
        $this->config->setAppValue("auto_groups", "modification_hook", 'true');

        // Creating a user should immediately add them to auto groups (creation_hook=true)
        $this->userManager->createUser('testuser', 'testPassword');
        $testUser = $this->userManager->get('testuser');

        $autogroup = $this->groupManager->search('autogroup1')[0];
        $this->assertTrue($autogroup->inGroup($testUser));
    }

    public function testAddHook()
    {
        $this->config->setAppValue("auto_groups", "auto_groups", '["autogroup1"]');
        $this->config->setAppValue("auto_groups", "override_groups", '["overridegroup1"]');
        $this->config->setAppValue("auto_groups", "login_hook", 'true');
        $this->config->setAppValue("auto_groups", "creation_hook", 'true');
        $this->config->setAppValue("auto_groups", "modification_hook", 'true');

        $testUser = $this->userManager->get('testuser');
        $overridegroup = $this->groupManager->search('overridegroup1')[0];
        $autogroup = $this->groupManager->search('autogroup1')[0];

        // Adding user to an override group should remove them from auto groups (modification_hook=true)
        $overridegroup->addUser($testUser);

        $this->assertNotTrue($autogroup->inGroup($testUser));
    }

    public function testRemoveHook()
    {
        $this->config->setAppValue("auto_groups", "auto_groups", '["autogroup1", "autogroup2"]');
        $this->config->setAppValue("auto_groups", "override_groups", '["overridegroup1"]');
        $this->config->setAppValue("auto_groups", "login_hook", 'true');
        $this->config->setAppValue("auto_groups", "creation_hook", 'true');
        $this->config->setAppValue("auto_groups", "modification_hook", 'true');

        $testUser = $this->userManager->get('testuser');
        $overridegroup = $this->groupManager->search('overridegroup1')[0];
        $autogroup1 = $this->groupManager->search('autogroup1')[0];
        $autogroup2 = $this->groupManager->search('autogroup2')[0];

        // Removing user from the override group should re-add them to all auto groups (modification_hook=true)
        $overridegroup->removeUser($testUser);

        $this->assertTrue($autogroup1->inGroup($testUser) && $autogroup2->inGroup($testUser));
    }

    public function testLoginHook()
    {
        $this->config->setAppValue("auto_groups", "auto_groups", '["autogroup1", "autogroup2"]');
        $this->config->setAppValue("auto_groups", "override_groups", '["overridegroup1"]');
        $this->config->setAppValue("auto_groups", "login_hook", 'true');
        $this->config->setAppValue("auto_groups", "creation_hook", 'false');
        $this->config->setAppValue("auto_groups", "modification_hook", 'false');

        // Use a dedicated user for this test to avoid state from other tests.
        // IUserSession::login() cannot be used in CLI test context (no HTTP session),
        // so we dispatch PostLoginEvent via the Nextcloud event dispatcher directly —
        // the same mechanism Nextcloud uses internally for all other events in this test suite.
        $loginUser = $this->userManager->createUser('loginuser', 'testPassword');
        $autogroup1 = $this->groupManager->search('autogroup1')[0];
        $autogroup2 = $this->groupManager->search('autogroup2')[0];
        $overridegroup = $this->groupManager->search('overridegroup1')[0];

        // Phase 1: user is not in override group → login should ADD them to auto groups
        $this->assertFalse($autogroup1->inGroup($loginUser));
        $this->eventDispatcher->dispatchTyped(new PostLoginEvent($loginUser, 'loginuser', 'testPassword', false));
        $this->assertTrue($autogroup1->inGroup($loginUser) && $autogroup2->inGroup($loginUser));

        // Phase 2: add user to override group (modification_hook=false so no auto-trigger),
        // then login should REMOVE them from auto groups.
        // Re-fetch group objects to avoid stale per-Group $users cache from Phase 1.
        $overridegroup->addUser($loginUser);
        $this->eventDispatcher->dispatchTyped(new PostLoginEvent($loginUser, 'loginuser', 'testPassword', false));
        $freshGroup1 = $this->groupManager->get('autogroup1');
        $freshGroup2 = $this->groupManager->get('autogroup2');
        $this->assertFalse($freshGroup1->inGroup($loginUser) || $freshGroup2->inGroup($loginUser));
    }


    public function testBeforeGroupDeletionHook()
    {
        $this->config->setAppValue("auto_groups", "auto_groups", '["autogroup1", "autogroup2"]');
        $this->config->setAppValue("auto_groups", "override_groups", '["overridegroup1"]');
        $this->config->setAppValue("auto_groups", "login_hook", 'true');
        $this->config->setAppValue("auto_groups", "creation_hook", 'true');
        $this->config->setAppValue("auto_groups", "modification_hook", 'true');

        $autogroup = $this->groupManager->search('autogroup1')[0];

        // Deleting a group that is configured as an auto group should be prevented
        $this->expectException(OCSBadRequestException::class);
        $autogroup->delete();
    }
}

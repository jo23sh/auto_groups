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

namespace OCA\AutoGroups\Tests\Unit;

use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\User\Events\UserCreatedEvent;

use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\OCS\OCSBadRequestException;

use OCP\IUser;
use OCP\IGroup;

use OCA\AutoGroups\AutoGroupsManager;

use Psr\Log\LoggerInterface;

use Test\TestCase;


class AutoGroupsManagerTest extends TestCase
{
    private $groupManager;
    private $config;
    private $logger;
    private $il10n;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->il10n = $this->createMock(IL10N::class);

        $this->testUser = $this->createMock(IUser::class);
        $this->testUser->expects($this->any())
            ->method('getDisplayName')
            ->willReturn('Test User');
    }

    private function createAutoGroupsManager($auto_groups = [], $override_groups = [])
    {
        $this->config->method('getAppValue')
            ->willReturnCallback(function ($app, $key, $default = '') use ($auto_groups, $override_groups) {
                if ($app === 'AutoGroups') {
                    return ''; // no migration needed
                }
                if ($app === 'auto_groups' && $key === 'auto_groups') {
                    return json_encode($auto_groups);
                }
                if ($app === 'auto_groups' && $key === 'override_groups') {
                    return json_encode($override_groups);
                }
                return $default;
            });

        return new AutoGroupsManager($this->groupManager, $this->config, $this->logger, $this->il10n);
    }

    private function configMigrationTestImpl($creationOnly, $expectedModification)
    {
        $this->config->expects($this->exactly(2))
            ->method('getAppValue')
            ->withConsecutive(
                ['AutoGroups', 'creation_only'],
                ['AutoGroups', 'creation_hook'],
            )
            ->willReturnOnConsecutiveCalls($creationOnly, '');

        $this->config->expects($this->exactly(1))
            ->method('setAppValue')
            ->with('auto_groups', 'modification_hook', $expectedModification);

        $this->config->expects($this->exactly(1))
            ->method('deleteAppValue')
            ->with('AutoGroups', 'creation_only');

        return new AutoGroupsManager($this->groupManager, $this->config, $this->logger, $this->il10n);
    }

    public function testAddingToAutoGroups()
    {
        $event = $this->createMock(UserCreatedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // User belongs to no groups, so they should be added to the auto group
        $this->groupManager->expects($this->once())
            ->method('getUserGroups')
            ->with($this->testUser)
            ->willReturn([]);

        $autogroup = $this->createMock(IGroup::class);
        $autogroup->expects($this->once())->method('getGID')->willReturn('autogroup');
        $autogroup->expects($this->once())->method('inGroup')->with($this->testUser)->willReturn(false);
        $autogroup->expects($this->once())->method('addUser')->with($this->testUser);

        $this->groupManager->expects($this->once())
            ->method('search')
            ->with('autogroup')
            ->willReturn([$autogroup]);

        $agm = $this->createAutoGroupsManager(['autogroup']);
        $agm->addAndRemoveAutoGroups($event);
    }

    public function testAddingNotRequired()
    {
        $event = $this->createMock(UserCreatedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // User is already in the auto group, so addUser should never be called
        $this->groupManager->expects($this->once())
            ->method('getUserGroups')
            ->with($this->testUser)
            ->willReturn(['autogroup' => []]);

        $autogroup = $this->createMock(IGroup::class);
        $autogroup->expects($this->once())->method('getGID')->willReturn('autogroup');
        $autogroup->expects($this->once())->method('inGroup')->with($this->testUser)->willReturn(true);
        $autogroup->expects($this->never())->method('addUser');

        $this->groupManager->expects($this->once())
            ->method('search')
            ->with('autogroup')
            ->willReturn([$autogroup]);

        $agm = $this->createAutoGroupsManager(['autogroup']);
        $agm->addAndRemoveAutoGroups($event);
    }

    public function testRemoveUserFromAutoGroups()
    {
        $event = $this->createMock(UserCreatedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // User belongs to an override group, so they should be removed from all auto groups
        $this->groupManager->expects($this->once())
            ->method('getUserGroups')
            ->with($this->testUser)
            ->willReturn(['autogroup1' => [], 'overridegroup1' => [], 'autogroup2' => []]);

        $groupMock = $this->createMock(IGroup::class);
        $groupMock->expects($this->exactly(2))->method('getGID')->willReturnOnConsecutiveCalls('autogroup1', 'autogroup2');
        $groupMock->expects($this->exactly(2))->method('inGroup')->with($this->testUser)->willReturn(true);
        $groupMock->expects($this->exactly(2))->method('removeUser')->with($this->testUser);

        $this->groupManager->expects($this->exactly(2))
            ->method('search')
            ->withConsecutive(['autogroup1'], ['autogroup2'])
            ->willReturnOnConsecutiveCalls([$groupMock], [$groupMock]);

        $agm = $this->createAutoGroupsManager(['autogroup1', 'autogroup2'], ['overridegroup1', 'overridegroup2']);
        $agm->addAndRemoveAutoGroups($event);
    }

    public function testRemoveNotRequired()
    {
        $event = $this->createMock(UserCreatedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // User is in an override group but not in any auto group, so removeUser should never be called
        $this->groupManager->expects($this->once())
            ->method('getUserGroups')
            ->with($this->testUser)
            ->willReturn(['overridegroup1' => []]);

        $groupMock = $this->createMock(IGroup::class);
        $groupMock->expects($this->exactly(2))->method('getGID')->willReturnOnConsecutiveCalls('autogroup1', 'autogroup2');
        $groupMock->expects($this->exactly(2))->method('inGroup')->with($this->testUser)->willReturn(false);
        $groupMock->expects($this->never())->method('removeUser');

        $this->groupManager->expects($this->exactly(2))
            ->method('search')
            ->withConsecutive(['autogroup1'], ['autogroup2'])
            ->willReturnOnConsecutiveCalls([$groupMock], [$groupMock]);

        $agm = $this->createAutoGroupsManager(['autogroup1', 'autogroup2'], ['overridegroup1', 'overridegroup2']);
        $agm->addAndRemoveAutoGroups($event);
    }

    public function testGroupDeletionPrevented()
    {
        $groupMock = $this->createMock(IGroup::class);
        $groupMock->expects($this->any())
            ->method('getGID')
            ->willReturn('autogroup2');

        $event = $this->createMock(BeforeGroupDeletedEvent::class);
        $event->expects($this->once())
            ->method('getGroup')
            ->willReturn($groupMock);

        // autogroup2 is configured as an auto group, so deletion must be prevented
        $this->expectException(OCSBadRequestException::class);

        $agm = $this->createAutoGroupsManager(['autogroup1', 'autogroup2'], ['overridegroup1', 'overridegroup2']);
        $agm->handleGroupDeletion($event);
    }

    public function testGroupDeletionPreventionNotNeeded()
    {
        $groupMock = $this->createMock(IGroup::class);
        $groupMock->expects($this->any())
            ->method('getGID')
            ->willReturn('some other group');

        $event = $this->createMock(BeforeGroupDeletedEvent::class);
        $event->expects($this->once())
            ->method('getGroup')
            ->willReturn($groupMock);

        // 'some other group' is not referenced in config, so deletion should be allowed
        $agm = $this->createAutoGroupsManager(['autogroup1', 'autogroup2'], ['overridegroup1', 'overridegroup2']);
        $agm->handleGroupDeletion($event);
    }

    public function testConfigMigrationForCreationOnlyTrue()
    {
        // Legacy creation_only=true means modification_hook should be migrated to false
        $agm = $this->configMigrationTestImpl('true', 'false');
    }

    public function testConfigMigrationForCreationOnlyFalse()
    {
        // Legacy creation_only=false means modification_hook should be migrated to true
        $agm = $this->configMigrationTestImpl('false', 'true');
    }
}

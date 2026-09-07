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
use OCP\Group\Events\UserRemovedEvent;
use OCP\IUserManager;
use OCP\User\Events\UserCreatedEvent;

use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IL10N;

use OCP\AppFramework\OCS\OCSBadRequestException;

use OCP\IUser;
use OCP\IGroup;

use OCA\AutoGroups\AutoGroupsManager;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;


class AutoGroupsManagerTest extends TestCase
{
    private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private IL10N&MockObject $il10n;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->il10n = $this->createMock(IL10N::class);

        $this->testUser = $this->createMock(IUser::class);
        $this->testUser->expects($this->any())
            ->method('getUID')
            ->willReturn('testuser');
        $this->testUser->expects($this->any())
            ->method('getDisplayName')
            ->willReturn('Test User');
    }

    /**
     * The event handler guards against users that are already gone from the
     * backend, so every hook invocation looks the user up by their uid.
     */
    private function expectUserExistsCheck(bool $exists): void
    {
        $this->userManager->expects($this->once())
            ->method('userExists')
            ->with('testuser')
            ->willReturn($exists);
    }

    private function createAutoGroupsManager($auto_groups = [], $override_groups = []): AutoGroupsManager
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

        return new AutoGroupsManager($this->groupManager, $this->userManager, $this->config, $this->logger, $this->il10n);
    }

    private function configMigrationTestImpl($creationOnly, $expectedModification): AutoGroupsManager
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

        return new AutoGroupsManager($this->groupManager, $this->userManager, $this->config, $this->logger, $this->il10n);
    }

    public function testAddingToAutoGroups()
    {
        $event = $this->createMock(UserCreatedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        $this->expectUserExistsCheck(true);

        // User belongs to no groups, so they should be added to the auto group
        $this->groupManager->expects($this->once())
            ->method('getUserGroupIds')
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

        $this->expectUserExistsCheck(true);

        // User is already in the auto group, so addUser should never be called
        $this->groupManager->expects($this->once())
            ->method('getUserGroupIds')
            ->with($this->testUser)
            ->willReturn(['autogroup']);

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

        $this->expectUserExistsCheck(true);

        // User belongs to an override group, so they should be removed from all auto groups
        $this->groupManager->expects($this->once())
            ->method('getUserGroupIds')
            ->with($this->testUser)
            ->willReturn(['autogroup1', 'overridegroup1', 'autogroup2']);

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

        $this->expectUserExistsCheck(true);

        // User is in an override group but not in any auto group, so removeUser should never be called
        $this->groupManager->expects($this->once())
            ->method('getUserGroupIds')
            ->with($this->testUser)
            ->willReturn(['overridegroup1']);

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

    public function testDeletedUserIsIgnored()
    {
        $event = $this->createMock(UserCreatedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // The user is already gone from the backend, which is the situation
        // OC\User\BackgroundJobs\CleanupDeletedUsers creates when it removes a
        // partially deleted user from their groups. Re-adding them to the auto
        // groups at that point would resurrect the user, so the hook has to bail
        // out before touching any group.
        $this->expectUserExistsCheck(false);

        $this->groupManager->expects($this->never())->method('getUserGroupIds');
        $this->groupManager->expects($this->never())->method('search');

        $agm = $this->createAutoGroupsManager(['autogroup1', 'autogroup2'], ['overridegroup1']);
        $agm->addAndRemoveAutoGroups($event);
    }

    public function testUserBeingDeletedIsIgnoredWhileStillPresent()
    {
        $event = $this->createMock(UserRemovedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // The case testDeletedUserIsIgnored() cannot cover. Deleting a user removes
        // them from every group *before* deleting the user record, so each removal
        // fires UserRemovedEvent while userExists() is still true — re-adding them
        // here leaves a group membership behind for a user that then ceases to
        // exist. Knowing the deletion is in flight is the only thing that
        // distinguishes this from an admin removing someone by hand, which the
        // modification hook is supposed to undo.
        $this->userManager->expects($this->never())->method('userExists');
        $this->groupManager->expects($this->never())->method('getUserGroupIds');
        $this->groupManager->expects($this->never())->method('search');

        $agm = $this->createAutoGroupsManager(['autogroup1', 'autogroup2'], ['overridegroup1']);
        $agm->markUserAsDeleting('testuser');
        $agm->addAndRemoveAutoGroups($event);
    }

    public function testOtherUsersAreUnaffectedByAPendingDeletion()
    {
        $event = $this->createMock(UserRemovedEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->testUser);

        // The flag is per uid, not a global "a deletion is happening" switch: one
        // user being deleted must not stop the hooks working for everyone else in
        // the same request.
        $this->expectUserExistsCheck(true);
        $this->groupManager->expects($this->once())
            ->method('getUserGroupIds')
            ->willReturn(['autogroup1']);
        $this->groupManager->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $agm = $this->createAutoGroupsManager(['autogroup1'], ['overridegroup1']);
        $agm->markUserAsDeleting('someone.else');
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

    public function testConfigMigrationFromLegacyAppId()
    {
        // Every key still stored under the old `AutoGroups` app id is copied to
        // `auto_groups` and then dropped (GitHub issue #82). `creation_hook`
        // being set is what marks a config as needing the migration at all.
        $this->config->expects($this->exactly(6))
            ->method('getAppValue')
            ->withConsecutive(
                ['AutoGroups', 'creation_only'],
                ['AutoGroups', 'creation_hook'],
                ['AutoGroups', 'modification_hook'],
                ['AutoGroups', 'login_hook'],
                ['AutoGroups', 'auto_groups'],
                ['AutoGroups', 'override_groups'],
            )
            ->willReturnOnConsecutiveCalls(
                '', // no creation_only, so that migration is skipped
                'true',
                'false',
                'true',
                '["autogroup1"]',
                '["overridegroup1"]',
            );

        $this->config->expects($this->exactly(5))
            ->method('setAppValue')
            ->withConsecutive(
                ['auto_groups', 'creation_hook', 'true'],
                ['auto_groups', 'modification_hook', 'false'],
                ['auto_groups', 'login_hook', 'true'],
                ['auto_groups', 'auto_groups', '["autogroup1"]'],
                ['auto_groups', 'override_groups', '["overridegroup1"]'],
            );

        $this->config->expects($this->exactly(5))
            ->method('deleteAppValue')
            ->withConsecutive(
                ['AutoGroups', 'creation_hook'],
                ['AutoGroups', 'modification_hook'],
                ['AutoGroups', 'login_hook'],
                ['AutoGroups', 'auto_groups'],
                ['AutoGroups', 'override_groups'],
            );

        new AutoGroupsManager($this->groupManager, $this->userManager, $this->config, $this->logger, $this->il10n);
    }

    public function testConfigMigrationSkipsKeysThatAreNotSet()
    {
        // A config that only ever set creation_hook migrates that one key and
        // leaves the others alone, rather than writing empty values across.
        $this->config->expects($this->exactly(6))
            ->method('getAppValue')
            ->willReturnOnConsecutiveCalls('', 'false', '', '', '', '');

        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('auto_groups', 'creation_hook', 'false');

        $this->config->expects($this->once())
            ->method('deleteAppValue')
            ->with('AutoGroups', 'creation_hook');

        new AutoGroupsManager($this->groupManager, $this->userManager, $this->config, $this->logger, $this->il10n);
    }
}

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

use OCP\AppFramework\Services\IAppConfig;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IUser;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\UserLoggedInEvent;

use OCA\AutoGroups\AutoGroupsManager;
use OCA\AutoGroups\Listener\AutoGroupsListener;

use PHPUnit\Framework\MockObject\MockObject;

use Test\TestCase;

/**
 * The listener is the routing table between Nextcloud's events and the manager:
 * which event reaches which method, and which config flag gates it.
 */
class AutoGroupsListenerTest extends TestCase
{
    private AutoGroupsManager&MockObject $manager;
    private IAppConfig&MockObject $appConfig;
    private AutoGroupsListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->createMock(AutoGroupsManager::class);
        $this->appConfig = $this->createMock(IAppConfig::class);

        $this->listener = new AutoGroupsListener($this->manager, $this->appConfig);
    }

    public function testUserDeletionIsFlaggedBeforeTheGroupsAreEmptied()
    {
        $user = $this->createMock(IUser::class);
        $user->expects($this->once())->method('getUID')->willReturn('testuser');

        $event = $this->createMock(BeforeUserDeletedEvent::class);
        $event->expects($this->once())->method('getUser')->willReturn($user);

        // No config flag guards this one: the deletion marker has to be set
        // whatever the hooks are configured to do, or the removals that follow
        // are undone and leave an orphaned group membership behind.
        $this->appConfig->expects($this->never())->method('getAppValueBool');
        $this->manager->expects($this->once())->method('markUserAsDeleting')->with('testuser');
        $this->manager->expects($this->never())->method('addAndRemoveAutoGroups');

        $this->listener->handle($event);
    }

    public function testCreationHookRunsWhenEnabled()
    {
        $event = $this->createMock(UserCreatedEvent::class);

        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('creation_hook', true)
            ->willReturn(true);

        $this->manager->expects($this->once())->method('addAndRemoveAutoGroups')->with($event);

        $this->listener->handle($event);
    }

    public function testCreationHookIsSkippedWhenDisabled()
    {
        $event = $this->createMock(UserCreatedEvent::class);

        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('creation_hook', true)
            ->willReturn(false);

        $this->manager->expects($this->never())->method('addAndRemoveAutoGroups');

        $this->listener->handle($event);
    }

    public function testFirstTimeLoginIsTreatedAsCreation()
    {
        // External user backends never fire UserCreatedEvent, so the creation
        // hook has to cover the first login too.
        $event = $this->createMock(UserFirstTimeLoggedInEvent::class);

        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('creation_hook', true)
            ->willReturn(true);

        $this->manager->expects($this->once())->method('addAndRemoveAutoGroups')->with($event);

        $this->listener->handle($event);
    }

    public function testModificationHookRunsOnGroupAdd()
    {
        $event = $this->createMock(UserAddedEvent::class);

        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('modification_hook', true)
            ->willReturn(true);

        $this->manager->expects($this->once())->method('addAndRemoveAutoGroups')->with($event);

        $this->listener->handle($event);
    }

    public function testModificationHookIsSkippedOnGroupRemoveWhenDisabled()
    {
        $event = $this->createMock(UserRemovedEvent::class);

        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('modification_hook', true)
            ->willReturn(false);

        $this->manager->expects($this->never())->method('addAndRemoveAutoGroups');

        $this->listener->handle($event);
    }

    public function testLoginHookRunsWhenEnabled()
    {
        $event = $this->createMock(PostLoginEvent::class);

        // Unlike the other two this one defaults to off, so no default is passed.
        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('login_hook')
            ->willReturn(true);

        $this->manager->expects($this->once())->method('addAndRemoveAutoGroups')->with($event);

        $this->listener->handle($event);
    }

    public function testLoginHookIsSkippedWhenDisabled()
    {
        $event = $this->createMock(UserLoggedInEvent::class);

        $this->appConfig->expects($this->once())
            ->method('getAppValueBool')
            ->with('login_hook')
            ->willReturn(false);

        $this->manager->expects($this->never())->method('addAndRemoveAutoGroups');

        $this->listener->handle($event);
    }

    public function testGroupDeletionIsDelegatedUnconditionally()
    {
        $event = $this->createMock(BeforeGroupDeletedEvent::class);

        // Deletion protection is not one of the configurable hooks.
        $this->appConfig->expects($this->never())->method('getAppValueBool');
        $this->manager->expects($this->once())->method('handleGroupDeletion')->with($event);

        $this->listener->handle($event);
    }
}

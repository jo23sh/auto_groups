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

use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\UserLoggedInEvent;

use OCA\AutoGroups\AppInfo\Application;
use OCA\AutoGroups\AutoGroupsManager;
use OCA\AutoGroups\Listener\AutoGroupsListener;
use OCA\AutoGroups\Settings\Admin;

use Test\TestCase;

/**
 * Nextcloud runs register() and boot() during server bootstrap, long before a
 * test starts, so nothing here is exercised by the integration suite. Both are
 * pure wiring, and a missed entry in either is silent: the app simply stops
 * reacting to an event.
 */
class ApplicationTest extends TestCase
{
    public function testEveryHandledEventIsRegistered()
    {
        $context = $this->createMock(IRegistrationContext::class);

        $context->expects($this->exactly(8))
            ->method('registerEventListener')
            ->withConsecutive(
                [BeforeUserDeletedEvent::class, AutoGroupsListener::class],
                [UserCreatedEvent::class, AutoGroupsListener::class],
                [UserFirstTimeLoggedInEvent::class, AutoGroupsListener::class],
                [UserAddedEvent::class, AutoGroupsListener::class],
                [UserRemovedEvent::class, AutoGroupsListener::class],
                [PostLoginEvent::class, AutoGroupsListener::class],
                [UserLoggedInEvent::class, AutoGroupsListener::class],
                [BeforeGroupDeletedEvent::class, AutoGroupsListener::class],
            );

        // The admin form is registered here rather than in info.xml, which is
        // what a declarative settings form requires.
        $context->expects($this->once())
            ->method('registerDeclarativeSettings')
            ->with(Admin::class);

        (new Application())->register($context);
    }

    public function testBootResolvesTheManagerToRunTheConfigMigration()
    {
        $container = $this->createMock(IAppContainer::class);
        $container->expects($this->once())
            ->method('query')
            ->with(AutoGroupsManager::class);

        $context = $this->createMock(IBootContext::class);
        $context->expects($this->once())
            ->method('getAppContainer')
            ->willReturn($container);

        (new Application())->boot($context);
    }
}

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

use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\Settings\DeclarativeSettingsTypes;

use OCA\AutoGroups\Settings\Admin;

use Test\TestCase;

class AdminSettingsTest extends TestCase
{
    private $config;
    private $groupManager;
    private $user;
    private $adminSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(IConfig::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->user = $this->createMock(IUser::class);

        $l = $this->createMock(IL10N::class);
        $l->method('t')->willReturnArgument(0);

        $this->groupManager->method('search')->with('')->willReturn([
            $this->mockGroup('admin', 'Administrators'),
            $this->mockGroup('staff', 'Staff'),
        ]);

        $this->adminSettings = new Admin($this->config, $this->groupManager, $l);
    }

    private function mockGroup(string $gid, string $displayName): IGroup
    {
        $group = $this->createMock(IGroup::class);
        $group->method('getGID')->willReturn($gid);
        $group->method('getDisplayName')->willReturn($displayName);

        return $group;
    }

    public function testSchema()
    {
        // The form must land in the 'additional' admin section, below the core forms,
        // and store its values through this class rather than through Nextcloud
        $schema = $this->adminSettings->getSchema();

        $this->assertEquals('auto_groups', $schema['id']);
        $this->assertEquals(100, $schema['priority']);
        $this->assertEquals(DeclarativeSettingsTypes::SECTION_TYPE_ADMIN, $schema['section_type']);
        $this->assertEquals('additional', $schema['section_id']);
        $this->assertEquals(DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL, $schema['storage_type']);
    }

    public function testSchemaFields()
    {
        // Every field id doubles as the config key it is stored under
        $fields = [];
        foreach ($this->adminSettings->getSchema()['fields'] as $field) {
            $fields[$field['id']] = $field['type'];
        }

        $this->assertEquals([
            'auto_groups' => DeclarativeSettingsTypes::MULTI_SELECT,
            'override_groups' => DeclarativeSettingsTypes::MULTI_SELECT,
            'creation_hook' => DeclarativeSettingsTypes::CHECKBOX,
            'modification_hook' => DeclarativeSettingsTypes::CHECKBOX,
            'login_hook' => DeclarativeSettingsTypes::CHECKBOX,
        ], $fields);
    }

    public function testAdminGroupIsNotOfferedAsAutoGroup()
    {
        $fields = [];
        foreach ($this->adminSettings->getSchema()['fields'] as $field) {
            $fields[$field['id']] = $field;
        }

        // Adding every user to the admin group would make everyone an administrator
        $this->assertSame(['staff'], $fields['auto_groups']['options']);

        // Override Groups are about exempting users, so the admin group is fine there
        $this->assertSame(['admin', 'staff'], $fields['override_groups']['options']);
    }

    public function testGetValueReadsGroupsAsJsonString()
    {
        // A multi-select field is read as a JSON string: the frontend JSON.parse()s it
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('auto_groups', 'auto_groups', '[]')
            ->willReturn(json_encode(['auto1', 'auto2']));

        $this->assertSame('["auto1","auto2"]', $this->adminSettings->getValue('auto_groups', $this->user));
    }

    public function testGetValueSurvivesUnparsableGroups()
    {
        // Anything the frontend cannot parse breaks the whole settings page, so a
        // damaged config value must still come back as valid JSON
        $this->config->method('getAppValue')->willReturn('not json');

        $this->assertSame('[]', $this->adminSettings->getValue('override_groups', $this->user));
    }

    /**
     * @dataProvider hookDefaultProvider
     */
    public function testGetValueUsesHookDefaults(string $fieldId, string $default, bool $expected)
    {
        // The stored value is the 'true'/'false' string the rest of the app writes
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('auto_groups', $fieldId, $default)
            ->willReturn($default);

        $this->assertSame($expected, $this->adminSettings->getValue($fieldId, $this->user));
    }

    public function hookDefaultProvider(): array
    {
        return [
            'creation hook defaults to on' => ['creation_hook', 'true', true],
            'modification hook defaults to on' => ['modification_hook', 'true', true],
            'login hook defaults to off' => ['login_hook', 'false', false],
        ];
    }

    /**
     * The multi-select posts a JSON string; an event-based handler would pass an array
     *
     * @dataProvider groupInputProvider
     */
    public function testSetValueStoresGroupsAsJsonList($sent)
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('auto_groups', 'override_groups', '["override1","override2"]');

        $this->adminSettings->setValue('override_groups', $sent, $this->user);
    }

    public function groupInputProvider(): array
    {
        return [
            'JSON string, as the form posts it' => ['["override1","override2"]'],
            'plain array' => [['override1', 'override2']],
        ];
    }

    public function testSetValueSurvivesUnparsableGroups()
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('auto_groups', 'auto_groups', '[]');

        $this->adminSettings->setValue('auto_groups', 'not json', $this->user);
    }

    public function testSetValueKeepsGroupIdsOnly()
    {
        // Guards against a select handing back option objects instead of plain IDs
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('auto_groups', 'auto_groups', '["staff"]');

        $this->adminSettings->setValue('auto_groups', [['label' => 'Staff', 'value' => 'staff']], $this->user);
    }

    public function testSetValueStoresHooksAsStrings()
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('auto_groups', 'login_hook', 'true');

        $this->adminSettings->setValue('login_hook', true, $this->user);
    }
}

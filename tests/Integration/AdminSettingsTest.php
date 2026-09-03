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

use OCP\IConfig;
use OCP\IUser;

use Test\TestCase;
use OCA\AutoGroups\AppInfo\Application;
use OCA\AutoGroups\Settings\Admin;

/**
* @group DB
*/
class AdminSettingsTest extends TestCase
{

    private $app;
    private $container;
    private $config;
    private $adminSettings;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application();
        $this->container = $this->app->getContainer();

        $this->config = $this->container->query(IConfig::class);
        $this->adminSettings = $this->container->query(Admin::class);
        $this->user = $this->createMock(IUser::class);
    }

    public function testSchemaIsUsable()
    {
        // The container must be able to build the form with its real dependencies,
        // and every field must carry what Nextcloud requires to render it
        $schema = $this->adminSettings->getSchema();

        $this->assertEquals('additional', $schema['section_id']);
        $this->assertNotEmpty($schema['title']);
        $this->assertCount(5, $schema['fields']);

        foreach ($schema['fields'] as $field) {
            $this->assertNotEmpty($field['id']);
            $this->assertNotEmpty($field['title']);
            $this->assertNotEmpty($field['type']);
            $this->assertArrayHasKey('default', $field);
        }
    }

    public function testGroupOptionsComeFromTheGroupManager()
    {
        $fields = [];
        foreach ($this->adminSettings->getSchema()['fields'] as $field) {
            $fields[$field['id']] = $field;
        }

        // Both group pickers must offer plain group IDs: the select posts back
        // whatever it was given, so an option object would be stored as the group
        foreach (['auto_groups', 'override_groups'] as $fieldId) {
            $this->assertIsArray($fields[$fieldId]['options']);

            foreach ($fields[$fieldId]['options'] as $option) {
                $this->assertIsString($option);
            }
        }
    }

    /**
     * The form is written with the value the frontend sends (an array for the group
     * pickers, a boolean for the hooks) and read back in the shape it expects (a JSON
     * string, a boolean) — while the config keeps the format AutoGroupsManager reads.
     *
     * @dataProvider roundTripProvider
     */
    public function testValuesRoundTripThroughTheConfig(string $fieldId, $sent, string $stored, $read)
    {
        $previous = $this->config->getAppValue('auto_groups', $fieldId, null);

        try {
            $this->adminSettings->setValue($fieldId, $sent, $this->user);

            $this->assertSame($stored, $this->config->getAppValue('auto_groups', $fieldId));
            $this->assertSame($read, $this->adminSettings->getValue($fieldId, $this->user));
        } finally {
            if ($previous === null) {
                $this->config->deleteAppValue('auto_groups', $fieldId);
            } else {
                $this->config->setAppValue('auto_groups', $fieldId, $previous);
            }
        }
    }

    public function roundTripProvider(): array
    {
        return [
            'groups as the form posts them' => ['auto_groups', '["group1","group2"]', '["group1","group2"]', '["group1","group2"]'],
            'groups as an array' => ['auto_groups', ['group1', 'group2'], '["group1","group2"]', '["group1","group2"]'],
            'override groups' => ['override_groups', ['group3'], '["group3"]', '["group3"]'],
            'no groups' => ['auto_groups', [], '[]', '[]'],
            'hook enabled' => ['login_hook', true, 'true', true],
            'hook disabled' => ['creation_hook', false, 'false', false],
        ];
    }
}

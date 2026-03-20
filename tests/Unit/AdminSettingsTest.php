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

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;

use OCA\AutoGroups\Settings\Admin;

use Test\TestCase;

// Mock Functions
function script($script, $scope)
{
    print ('<SCRIPT>' . $script . '</SCRIPT><SCOPE>' . $scope . '</SCOPE>');
}

function style($style, $scope)
{
    print ('<STYLE>' . $script . '</STYLE><SCOPE>' . $scope . '</SCOPE>');
}

function p($string)
{
    print ($string);
}

class Language
{
    public function t($string)
    {
        return $string;
    }
}

// The actual test class
class AdminSettingsTest extends TestCase
{
    private $config;
    private $adminSettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(IConfig::class);

        $this->adminSettings = new Admin($this->config);
    }

    public function testSection()
    {
        // Settings must appear in the 'additional' admin section
        $this->assertEquals('additional', $this->adminSettings->getSection());
    }

    public function testPriority()
    {
        // Priority 100 places the form towards the bottom of the section
        $this->assertEquals(100, $this->adminSettings->getPriority());
    }

    public function testForm()
    {
        // getForm() must read all five config values and pass them to the template as parameters
        $this->config->expects($this->exactly(5))
            ->method('getAppValue')
            ->withConsecutive(
                ['auto_groups', 'auto_groups', '[]'],
                ['auto_groups', 'override_groups', '[]'],
                ['auto_groups', 'creation_hook', 'true'],
                ['auto_groups', 'modification_hook', 'true'],
                ['auto_groups', 'login_hook', 'false']
            )
            ->willReturnOnConsecutiveCalls(json_encode(['auto1', 'auto2']), json_encode(['override1', 'override2']), true, true);

        $response = $this->adminSettings->getForm();

        $this->assertInstanceOf(TemplateResponse::class, $response);
        $this->assertEquals('admin', $response->getTemplateName());

        $params = $response->getParams();
        $this->assertIsArray($params);
        $this->assertEquals(true, $params['creation_hook']);
        $this->assertEquals(true, $params['modification_hook']);
        $this->assertEquals(false, $params['login_hook']);
        $this->assertEquals('auto1|auto2', $params['auto_groups']);
        $this->assertEquals('override1|override2', $params['override_groups']);
    }

    /*public function testTemplate() {
        // We're basically mocking https://github.com/nextcloud/server/blob/master/lib/private/Template/Base.php here

        $l = new Language();
        $_ = array('auto_groups' => 'autogroup1|autogroup2', 'override_groups' => 'override1|override2', 'login_hook' => 'true');

       ob_start();
       include 'templates/admin.php';
       $html = ob_get_contents();
       @ob_end_clean();
        
        $this->assertIsString($html);
        $this->assertStringContainsString('<p class="auto_groups_settings_section">', $html);
        $this->assertStringContainsString('<input name="auto_groups" id="auto_groups" value="autogroup1|autogroup2"', $html);
        $this->assertStringContainsString('<input name="auto_groups_override" id="auto_groups_override" value="override1|override2"', $html);
        $this->assertStringContainsString('<input name="auto_groups_login_hook" id="auto_groups_login_hook" type="checkbox" class="checkbox" checked', $html);
    }*/
}

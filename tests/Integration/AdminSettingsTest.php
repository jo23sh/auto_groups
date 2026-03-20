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

use OCP\Settings\IManager;
use OCP\AppFramework\Http\TemplateResponse;

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
    private $settingsManager;


    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application();
        $this->container = $this->app->getContainer();

        $this->settingsManager = $this->container->query(IManager::class);
    }

    public function testAppSettingsExist()
    {
        $settings = $this->settingsManager->getAdminSettings('additional');

        // The app must register its Admin settings class at priority 100 in the 'additional' section
        $this->assertArrayHasKey(100, $settings);
        $this->assertIsArray($settings[100]);
        $adminSettings = $settings[100][0];
        $this->assertInstanceOf(Admin::class, $adminSettings);
    }

    public function testFormRender()
    {
        $appSettings = $this->settingsManager->getAdminSettings('additional')[100][0];

        // getForm() must return a TemplateResponse (the actual template rendering is not tested here)
        $templateResponse = $appSettings->getForm();
        $this->assertInstanceOf(TemplateResponse::class, $templateResponse);

        /*$html = $templateResponse->render();
        $this->assertIsString($html);
        $this->assertStringContainsString('<div id="auto_groups_options" class="section">', $html);
        $this->assertStringContainsString('<p class="auto_groups_settings_section">', $html);*/
    }
}

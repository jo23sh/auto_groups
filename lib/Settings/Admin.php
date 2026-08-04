<?php

declare(strict_types=1);

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

namespace OCA\AutoGroups\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings
{
        public function __construct(
			private readonly IAppConfig $appConfig,
		)
        {
        }

	#[\Override]
        public function getForm(): TemplateResponse
        {
                $autoGroups = $this->appConfig->getAppValueArray("auto_groups");
                $overrideGroups = $this->appConfig->getAppValueArray("override_groups");
                $creationHook = $this->appConfig->getAppValueBool("creation_hook", true);
                $modificationHook = $this->appConfig->getAppValueBool("modification_hook", true);
                $loginHook = $this->appConfig->getAppValueBool("login_hook");

                $parameters = [
                        'auto_groups' => implode('|', $autoGroups),
                        'override_groups' => implode('|', $overrideGroups),
                        'login_hook' => $loginHook,
                        'creation_hook' => $creationHook,
                        'modification_hook' => $modificationHook
                ];

                return new TemplateResponse('auto_groups', 'admin', $parameters);
        }

		#[\Override]
        public function getSection(): string
        {
                return 'additional';
        }

		#[\Override]
        public function getPriority(): int
        {
                return 100;
        }

}

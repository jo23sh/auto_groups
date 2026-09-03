<?php
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

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsFormWithHandlers;

/**
 * The admin form is declarative: Nextcloud renders it from the schema below and calls
 * getValue()/setValue() for each field, so the app ships no template and no JavaScript.
 * The handlers keep the config format the rest of the app reads — JSON arrays of group
 * IDs, 'true'/'false' strings for the hooks.
 */
class Admin implements IDeclarativeSettingsFormWithHandlers
{

    private const APP_ID = 'auto_groups';

    /** Fields holding a list of group IDs. Every other field is a hook toggle. */
    private const GROUP_FIELDS = ['auto_groups', 'override_groups'];

    private const HOOK_DEFAULTS = [
        'creation_hook' => 'true',
        'modification_hook' => 'true',
        'login_hook' => 'false',
    ];

    /** @var IConfig */
    private $config;

    /** @var IGroupManager */
    private $groupManager;

    /** @var IL10N */
    private $l;

    public function __construct(IConfig $config, IGroupManager $groupManager, IL10N $l)
    {
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->l = $l;
    }

    public function getSchema(): array
    {
        return [
            'id' => 'auto_groups',
            'priority' => 100,
            'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
            'section_id' => 'additional',
            'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL,
            'title' => $this->l->t('Auto Groups'),
            'fields' => [
                [
                    'id' => 'auto_groups',
                    'title' => $this->l->t('Auto Groups'),
                    'description' => $this->l->t('Automatically add all users to these groups.'),
                    'type' => DeclarativeSettingsTypes::MULTI_SELECT,
                    'options' => $this->getGroupOptions(true),
                    'default' => [],
                ],
                [
                    'id' => 'override_groups',
                    'title' => $this->l->t('Override Groups'),
                    'description' => $this->l->t('Users which are member of at least one of these groups are removed from the auto groups. This is also the case if the user is added to one of these groups after creation, i.e., membership in the override groups is checked after each group modification.'),
                    'type' => DeclarativeSettingsTypes::MULTI_SELECT,
                    'options' => $this->getGroupOptions(false),
                    'default' => [],
                ],
                [
                    'id' => 'creation_hook',
                    'title' => $this->l->t('Set Auto Group membership on user creation.'),
                    'description' => $this->l->t('If checked, Auto Group membership will be enforced on user creation.'),
                    'type' => DeclarativeSettingsTypes::CHECKBOX,
                    'default' => true,
                ],
                [
                    'id' => 'modification_hook',
                    'title' => $this->l->t('Check Auto Group membership on modification of a user\'s groups.'),
                    'description' => $this->l->t('If checked, Auto Group membership will be re-enforced for a user account every time it is added to or removed from a group.'),
                    'type' => DeclarativeSettingsTypes::CHECKBOX,
                    'default' => true,
                ],
                [
                    'id' => 'login_hook',
                    'title' => $this->l->t('Check for correct Auto Group membership on every login.'),
                    'description' => $this->l->t('Enable this setting to enforce proper Auto Group membership on every successful login. This is useful if either users are not created in Nextcloud (e.g., with external user backends) or to enforce correct group membership for all users when the Auto Groups / Override Groups have changed.'),
                    'type' => DeclarativeSettingsTypes::CHECKBOX,
                    'default' => false,
                ],
            ],
        ];
    }

    public function getValue(string $fieldId, IUser $user): mixed
    {
        if (in_array($fieldId, self::GROUP_FIELDS, true)) {
            $groups = json_decode($this->config->getAppValue(self::APP_ID, $fieldId, '[]'), true);

            // A multi-select is read as a JSON string and written back as a plain array
            return json_encode(is_array($groups) ? array_values($groups) : []);
        }

        $default = self::HOOK_DEFAULTS[$fieldId] ?? 'false';

        return filter_var($this->config->getAppValue(self::APP_ID, $fieldId, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function setValue(string $fieldId, mixed $value, IUser $user): void
    {
        if (in_array($fieldId, self::GROUP_FIELDS, true)) {
            // The multi-select posts its value JSON-encoded, but the event-based
            // handlers are documented to pass a plain array — accept either
            $groups = is_string($value) ? json_decode($value, true) : $value;
            $groups = is_array($groups) ? $groups : [];

            // Keep only group IDs, whatever shape the select hands back
            $groups = array_values(array_filter(array_map(
                static fn ($group) => is_array($group) ? ($group['value'] ?? null) : $group,
                $groups
            ), static fn ($group): bool => is_string($group) && $group !== ''));

            $this->config->setAppValue(self::APP_ID, $fieldId, json_encode($groups));

            return;
        }

        $this->config->setAppValue(
            self::APP_ID,
            $fieldId,
            filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'
        );
    }

    /**
     * Group IDs as plain strings: the multi-select posts back whatever it was given,
     * so an option object would be stored in place of the group ID.
     *
     * The admin group is never offered as an Auto Group — adding every user to it
     * would make everyone an administrator.
     *
     * @return list<string>
     */
    private function getGroupOptions(bool $excludeAdmins): array
    {
        $options = [];

        foreach ($this->groupManager->search('') as $group) {
            if ($excludeAdmins && $group->getGID() === 'admin') {
                continue;
            }

            $options[] = $group->getGID();
        }

        return $options;
    }

}

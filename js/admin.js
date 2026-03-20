/**
 * @copyright Copyright (c) 2020
 *
 * @author Josua Hunziker <josh@o23.ch>
 *
 * Based on the work of Ján Stibila <nextcloud@stibila.eu> and Lukas Reschke <lukas@owncloud.com>
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

$(document).ready(function () {
  setTimeout(function () {
    var $autoGroups = $('#auto_groups')
    var $overrideGroups = $('#auto_groups_override')
    var $creationHook = $('#auto_groups_creation_hook')
    var $modificationHook = $('#auto_groups_modification_hook')
    var $loginHook = $('#auto_groups_login_hook')

    OC.Settings.setupGroupsSelect($autoGroups, null, {
      excludeAdmins: true,
    })
    $autoGroups.change(function (ev) {
      var groups = ev.val || []
      OCP.AppConfig.setValue(
        'auto_groups',
        'auto_groups',
        JSON.stringify(groups)
      )
    })
    $('#auto_groups .icon-info').tooltip({
      placement: 'right',
    })

    OC.Settings.setupGroupsSelect($overrideGroups, null, {
      excludeAdmins: false,
    })
    $overrideGroups.change(function (ev) {
      var groups = ev.val || []
      OCP.AppConfig.setValue(
        'auto_groups',
        'override_groups',
        JSON.stringify(groups)
      )
    })
    $('#auto_groups_override .icon-info').tooltip({
      placement: 'right',
    })

    $creationHook.change(function (ev) {
      OCP.AppConfig.setValue('auto_groups', 'creation_hook', this.checked)
    })

    $modificationHook.change(function (ev) {
      OCP.AppConfig.setValue('auto_groups', 'modification_hook', this.checked)
    })

    $loginHook.change(function (ev) {
      OCP.AppConfig.setValue('auto_groups', 'login_hook', this.checked)
    })
  }, 0)
})

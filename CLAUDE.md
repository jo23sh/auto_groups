# Auto Groups - Nextcloud App

## Overview

A Nextcloud app (v1.7.0, AGPL-3.0) that automatically adds users to configured groups ("Auto Groups"), with optional exemptions for users in "Override Groups". A modernized fork of the abandoned [defaultgroup](https://github.com/bodangren/defaultgroup) app.

- **Nextcloud compatibility**: 31–34
- **PHP**: 8.2, 8.3
- **App ID**: `auto_groups` (note: older config used `AutoGroups` — migration logic exists)

## Architecture

Single-class app with minimal footprint:

- `lib/AutoGroupsManager.php` — core logic; registers event listeners and handles group assignment/deletion
- `lib/AppInfo/Application.php` — bootstraps the app via Nextcloud's DI container
- `lib/Settings/Admin.php` — admin settings page
- `appinfo/routes.php` — routes
- `templates/admin.php` + `css/admin.css` + `js/admin.js` — admin UI

## Key Behavior

**Event hooks** (configurable):
- `creation_hook` (default: on) — fires on `UserCreatedEvent` and `UserFirstTimeLoggedInEvent`
- `modification_hook` (default: on) — fires on `UserAddedEvent` and `UserRemovedEvent`
- `login_hook` (default: off) — fires on `PostLoginEvent` and `UserLoggedInEvent`; useful for external user backends

**Group assignment logic** (`addAndRemoveAutoGroups`):
- If user belongs to any Override Group → remove from all Auto Groups
- If user belongs to no Override Group → add to all Auto Groups

**Group deletion protection**: throws `OCSBadRequestException` if trying to delete a group referenced as an Auto Group or Override Group.

## Config Keys

Stored via Nextcloud's `IConfig` under app `auto_groups`:
- `auto_groups` — JSON array of group IDs
- `override_groups` — JSON array of group IDs
- `creation_hook` — `'true'`/`'false'`
- `modification_hook` — `'true'`/`'false'`
- `login_hook` — `'true'`/`'false'`

## Testing

- Unit tests: `tests/Unit/` (uses PHPUnit mocks, extends Nextcloud's `Test\TestCase`)
- Integration tests: `tests/Integration/`
- Manual testing: `tests/Docker/run-docker-test-instance.sh` spins up a Docker instance on port 8080
- Lint: `composer run lint` (runs `php -l` on all PHP files)

## Release Process

Releases are handled by `.github/workflows/release.yml` on GitHub release publication:
1. Verifies `appinfo/info.xml` version matches the git tag
2. Verifies `CHANGELOG.md` has an entry for the version
3. Packages and uploads to GitHub Releases
4. Submits to Nextcloud App Store (requires `AUTO_GROUPS_SIGNING_KEY` and `APP_STORE_API_TOKEN` secrets)

## Noteworthy

- **Config namespace migration**: The app previously used `AutoGroups` as the config namespace instead of `auto_groups`. Migration code in `AutoGroupsManager::__construct` handles upgrading old configs (see GitHub issue #82).
- **l10n**: Translations managed via Transifex (`.tx/config`); many languages supported.
- No Composer dependencies beyond dev tooling — the app relies entirely on Nextcloud's built-in OCP APIs.

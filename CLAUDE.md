# Auto Groups - Nextcloud App

## Overview

A Nextcloud app (v1.8.0, AGPL-3.0) that automatically adds users to configured groups ("Auto Groups"), with optional exemptions for users in "Override Groups". A modernized fork of the abandoned [defaultgroup](https://github.com/bodangren/defaultgroup) app.

- **Nextcloud compatibility**: 32–36
- **PHP**: 8.2, 8.3, 8.4 (NC35 requires 8.3, so the CI matrix excludes 8.2 there)
- **App ID**: `auto_groups` (note: older config used `AutoGroups` — migration logic exists)

## Architecture

Single-class app with minimal footprint:

- `lib/AutoGroupsManager.php` — core logic; registers event listeners and handles group assignment/deletion
- `lib/AppInfo/Application.php` — bootstraps the app via Nextcloud's DI container
- `lib/Settings/Admin.php` — admin settings, as a declarative settings form
  (`IDeclarativeSettingsFormWithHandlers`): Nextcloud renders the form from `getSchema()`
  and reads/writes each field through `getValue()`/`setValue()`, which keep the config
  format below. Registered in `Application::register()`, **not** in `info.xml`. The app
  therefore ships no template, stylesheet or JavaScript — do not add any back: NC34
  dropped jQuery and `OC.Settings.setupGroupsSelect`, which is what killed the old form.
- `appinfo/routes.php` — routes

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

### Coverage

Every matrix leg uploads `tests/coverage.xml` to Codecov via
`codecov/codecov-action`, flagged with its `server-versions` value. Codecov
merges the flags into the single number behind the README badge. Nothing in the
workflow names a specific server branch, so a compatibility bump needs no edit
here.

The upload requires the `CODECOV_TOKEN` repository secret. Do not switch it to a
tokenless upload — that path is rate-limited and drops reports without failing
the step. Forks cannot read the secret, so `fail_ci_if_error` is off for them.

## Release Process

To cut a release (on `master`, once the changes to ship are merged):

1. Bump `<version>` in `appinfo/info.xml`.
2. In `CHANGELOG.md`, retitle `## [Unreleased]` as `## <version> - <YYYY-MM-DD>` and
   leave a fresh empty `## [Unreleased]` above it.
3. Update the version in this file's Overview. If the release changed the supported
   Nextcloud or PHP range, work the checklist below instead — it covers more places.
4. Commit and push to `master`.
5. `gh release create v<version> --title "Release <version>" --generate-notes` —
   the tag is `v`-prefixed, the release title is not, and the body is GitHub's
   generated notes.

Publishing the release triggers `.github/workflows/release.yml`, which:
1. Verifies `appinfo/info.xml` version matches the git tag
2. Verifies `CHANGELOG.md` has an entry for the version
3. Packages and uploads to GitHub Releases
4. Submits to Nextcloud App Store (requires `AUTO_GROUPS_SIGNING_KEY` and `APP_STORE_API_TOKEN` secrets)

## Bumping Nextcloud Compatibility

The first sign that a bump is due is the `server-versions: master` leg failing with
"cannot be installed because it is not compatible with this version of the server":
server master has moved past `max-version`. That leg is `experimental: true` and
`continue-on-error`, so it never blocks a merge — it is a signal, not a gate. Keep it
that way.

Do a compatibility bump as its own PR, separate from any behaviour change, and touch
all four places — they drift apart otherwise:

1. `appinfo/info.xml` — `<nextcloud min-version max-version>`.
2. `.github/workflows/tests.yml` — add the new `stableNN` to `server-versions`.
   Check the PHP bounds the new branch declares before assuming the matrix fits:
   ```bash
   curl -s https://raw.githubusercontent.com/nextcloud/server/stableNN/lib/versioncheck.php \
     | grep -oE 'PHP_VERSION_ID [<>]=? [0-9]+'
   ```
   `>= 80300` means PHP 8.3 is the floor, so an `exclude:` is needed for every older
   PHP in the matrix — a combination that cannot install fails the whole job.
3. `README.md` — the test-status table needs a row per `stableNN`, and the master row
   names the version master currently is. The PHP sentence under it lists the matrix.
4. This file — the compatibility and PHP lines in the Overview.

Dropping an EOL version is the same list in reverse, and belongs in the same PR
(`min-version`, the matrix entry, its README row, the Overview). Say so in the
CHANGELOG the way 1.7.2 did: "Compatibility up to NC35, drop EOL version NC31".

## Noteworthy

- **Config namespace migration**: The app previously used `AutoGroups` as the config namespace instead of `auto_groups`. Migration code in `AutoGroupsManager::__construct` handles upgrading old configs (see GitHub issue #82).
- **l10n**: Translations managed via Transifex (`.tx/config`); many languages supported.
- No Composer dependencies beyond dev tooling — the app relies entirely on Nextcloud's built-in OCP APIs.

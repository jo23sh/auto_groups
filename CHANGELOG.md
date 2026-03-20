# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.7.0 - 2026-03-20

### Changed

- Migrate event listener registration to `IBootstrap`/`registerEventListener()` for compatibility with NC34, replacing the deprecated `IEventDispatcher::addListener()` approach
- Compatibility up to NC34

### Fixed

- Restore login hook test which was previously broken due to hook config being read at app instantiation time rather than at event dispatch time

## 1.6.2 - 2025-03-03

### Fixed

- Fix app ID for settings for compatibility with NC 30+ (#82)

## 1.6.1 - 2025-02-28

### Changed

- Use PSR Logger Interface (#79)
- Compatibility up to NC32

## 1.6.0 - 2024-09-21

### Changed

- Support SAML Logins (#77) - shout out to @elainabialkowski
- Compatibility up to Nextcloud 30, drop versions 22-25

## 1.5.3 - 2024-04-04

### Changed

- Compatibility with Nextcloud 28 [#71](https://github.com/stjosh/auto_groups/issues/72)

## 1.5.2 - 2023-06-24

### Changed

- Compatibility with Nextcloud 27 [#67](https://github.com/stjosh/auto_groups/issues/67)

## 1.5.1 - 2023-01-16

### Fixed

- Fix admin settings display issue in NC25 and Firefox [#65](https://github.com/stjosh/auto_groups/issues/65)

## 1.5.0 - 2022-12-12

### Changed

- Fixed deprecation message [#61](https://github.com/stjosh/auto_groups/issues/61)
- More precise description for the "Groups Modification"-Setting [#60](https://github.com/stjosh/auto_groups/issues/60)

## 1.4.1 - 2021-11-18

### Changed

- Update path in admin settings [#62](https://github.com/stjosh/auto_groups/pull/62)

## 1.4.0 - 2021-11-18

### Changed

- Refactor hooks to be non-exclusive and separate the "creation" from the "modification" hook [#57](https://github.com/stjosh/auto_groups/issues/57)

## 1.3.1 - 2021-04-16

### Fixed

- Delay JS initialization in the admin settings to make sure that OC.Settings is defined before the script is executed

## 1.3.0 - 2021-02-27

### Changed

- Add "creation only" mode which allows to opt-out of group membership enforcement on subsequent user modifications

## 1.2.1 - 2021-02-23

### Changed

- Update translations and ensure compatibility with latest Nextcloud versions

## 1.2.0 - 2020-05-19

### Changed

- Move to UserLoggedInEvent for login checks, enabling compatibility with SAML login [#42](https://github.com/stjosh/auto_groups/issues/42)

## 1.1.1 - 2020-05-08

### Changed

- Translation Updates

## 1.1.0 - 2020-05-08

### Added

- Prevent Group deletion [#28](https://github.com/stjosh/auto_groups/issues/28)
- Automate release publishing [#9](https://github.com/stjosh/auto_groups/issues/9)
- Added Changelog [#27](https://github.com/stjosh/auto_groups/issues/27)

### Changed

- Updated documentation

## 1.0.2 - 2020-04-23

### Fixed

- Broken release 1.0.1 which could not be installed [#26](https://github.com/stjosh/auto_groups/issues/26)

## 1.0.1 - 2020-04-23

### Changed

- Documentation update to point out differences to similar apps
- Translation Updates

## 1.0.0 - 2020-04-23

### Added

- Auto Groups
- Override Groups
- Remove users from Auto Groups when added to Override Group
- Login Hook
- Community Translations
- Automated Tests

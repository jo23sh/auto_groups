# Nextcloud Auto Groups

Automatically add users to specified Auto Groups, except for those belonging to one of the specified Override Groups.

## Test Status

[![Code Coverage](https://img.shields.io/codecov/c/github/jo23sh/auto_groups/master?label=Code%20Coverage)](https://codecov.io/gh/jo23sh/auto_groups)

| Nextcloud Server Branch                                       |                                                 Unit & Integration Tests                                                  |
| ------------------------------------------------------------- | :-----------------------------------------------------------------------------------------------------------------------: |
| [stable32](https://github.com/nextcloud/server/tree/stable32) | ![Unit and Integration Tests](https://github.com/jo23sh/auto_groups/workflows/Unit%20and%20Integration%20Tests/badge.svg) |
| [stable33](https://github.com/nextcloud/server/tree/stable33) | ![Unit and Integration Tests](https://github.com/jo23sh/auto_groups/workflows/Unit%20and%20Integration%20Tests/badge.svg) |
| [stable34](https://github.com/nextcloud/server/tree/stable34) | ![Unit and Integration Tests](https://github.com/jo23sh/auto_groups/workflows/Unit%20and%20Integration%20Tests/badge.svg) |
| [stable35](https://github.com/nextcloud/server/tree/stable35) | ![Unit and Integration Tests](https://github.com/jo23sh/auto_groups/workflows/Unit%20and%20Integration%20Tests/badge.svg) |
| [master](https://github.com/nextcloud/server/tree/master) (NC36, experimental) | ![Unit and Integration Tests](https://github.com/jo23sh/auto_groups/workflows/Unit%20and%20Integration%20Tests/badge.svg) |

Unit and Integration Tests are executed with PHP v8.2, v8.3 and v8.4, except for
NC35, which requires at least v8.3. The experimental master branch runs v8.4.

## Usage

- Install and enable the App
- Go to "Settings > Administration > Additional settings" to configure the Auto Groups, Override Groups and further behavior.

Note that this app prevents group deletions for groups referenced as Auto Groups or Override Groups.

## Manual Testing

To manually test the app, an automatic script is provided. You need to have Docker installed and running to execute it. Simply go for

```bash
$ ./tests/Docker/run-docker-test-instance.sh
```

and then access your test instance on http://localhost:8080. The `auto_groups` app is automatically available, but not activated - this needs to be done manually.

## Comparison to similar Apps

- [Everyone Group](https://apps.nextcloud.com/apps/group_everyone): The "Everyone Group" app adds a virtual Group Backend, always returning all users. In contrast, "Auto Groups" operates on "real" groups in your normal Group Backend. Additionally, it is possible to specify Override Groups which will prevent users from being added to the Auto Group(s).
- [Default Group](https://apps.nextcloud.com/apps/defaultgroup): "Auto Groups" is actually a modernized and maintaned fork of "Default Group", which seems to be abandoned since NC12 or so. In terms of functionality, they are almost identical.

## Issue Tracker / Contributions

Contributions are welcome on [GitHub](https://github.com/jo23sh/auto_groups/issues).

## Acknowledgements

This app is based on the seemingly no-longer maintained [defaultgroup app](https://github.com/bodangren/defaultgroup), which is only verified to work up to NC14 and uses the deprecated Hooks mechanism instead of the now recommended OCP Event Dispatcher.

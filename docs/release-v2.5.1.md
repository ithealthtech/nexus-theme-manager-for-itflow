# Nexus Theme Manager 2.5.1

Nexus 2.5.1 is a focused hotfix for GUI updater service setup on systemd hosts.

## Fixed

- Corrected the generated `.path` unit's `PathExists=` directive. Version 2.5.0 quoted the value, causing systemd to treat the quote as part of the path and reject it as non-absolute.
- Kept quoting where systemd expects word parsing, including `ExecStart=` and `ReadWritePaths=`, while rendering the single path condition as an unquoted absolute value.
- Added safe systemd specifier escaping and control-character rejection for generated paths.

## Validation

- The updater test suite now checks the exact regression.
- Linux CI passes the generated `.service` and `.path` files through `systemd-analyze verify`.
- Existing release, archive, checksum, manifest, no-shell, and rollback protections remain active.

## Compatibility

- Manager 2.5.1 / theme payload 26.08.6
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer
- GUI update service: Linux with systemd, curl, and unzip

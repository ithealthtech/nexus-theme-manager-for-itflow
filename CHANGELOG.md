# Changelog

All notable changes to this project are documented here.

## [2.2.0] - 2026-08-09

### Changed

- Rebased the compatibility baseline and themed templates onto ITFlow commit `89b080b430aaafba5d520c4e52c57b28a9559085`.
- Preserved upstream query optimizations in the five client and navigation templates changed since the previous baseline.
- Updated package metadata, verification documentation, and the sanitized preview for the new compatibility release.

## [2.1.0] - 2026-08-09

### Added

- Added `install-latest.sh` to securely download, verify, extract, preflight, install, and verify the latest GitHub release on Debian/Ubuntu systems.

## [2.0.0] - 2026-08-08

### Changed

- Renamed the project and package to Nexus Theme Manager for IT Flow.
- Removed organization-specific names, domains, navigation links, and footer links from every managed surface.
- Replaced the stylesheet, CSS namespace, package ID, temporary-file prefix, and state paths with Nexus identifiers.
- Added a documented uninstall/reinstall migration from version 1.0.0.

### Compatibility

- Remains pinned to ITFlow 26.08 at commit `ccaa45b0ae9900ad731a6491559f65ff8d87a8f3`.
- Version 1.0.0 must be uninstalled before installing 2.0.0.

## [1.0.0] - 2026-08-08

### Added

- Standalone lifecycle manager with `doctor`, `install`, `adopt`, `status`, `verify`, `disable`, `enable`, and `uninstall` commands.
- Exact compatibility checks for ITFlow 26.08 at commit `ccaa45b0ae9900ad731a6491559f65ff8d87a8f3`.
- Immutable payload verification, protected backups, atomic activation, rollback, drift detection, and operation locking.
- Customer, technician, authentication, and recovery-page theme templates.
- Automated lifecycle tests and multi-version PHP continuous integration.

[1.0.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v1.0.0
[2.0.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.0.0
[2.1.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.1.0
[2.2.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.2.0

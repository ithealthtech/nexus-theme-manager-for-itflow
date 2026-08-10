# Changelog

All notable changes to this project are documented here.

## [2.5.1] - 2026-08-10

### Fixed

- Fixed the generated `PathExists=` value in the systemd path unit. Version 2.5.0 incorrectly surrounded this directive with quotes, which systemd treated as part of the path and rejected as non-absolute.
- Centralized service/path unit rendering and escaped systemd specifiers without quoting the single path-directive value.

### Validation

- Added regression coverage for the exact unquoted absolute `PathExists=` syntax.
- Added Linux CI validation of both generated units with `systemd-analyze verify`.

## [2.5.0] - 2026-08-09

### Added

- Added **Check for updates** and **Install version** controls with live progress and result reporting to Theme Studio.
- Added a per-instance systemd path service and root-owned updater helper for privileged update execution outside the web process.
- Added strict release-tag, asset-name, SHA-256, archive-layout, and package-manifest validation.
- Added automatic restoration and verification of the previous Nexus release when new-version activation fails.
- Added a focused updater security suite alongside expanded lifecycle coverage for the web request bridge.

### Security

- The browser may queue only fixed `check` or `update` actions; it cannot provide a repository, URL, command, path, version, or lifecycle argument.
- The helper accepts only the latest published release from the fixed Nexus GitHub repository and refuses unexpected archive content.
- The root service uses a private temporary directory, protected home directories, a strict filesystem view, and explicit writable paths.

### Changed

- The standard latest-release installer now enables GUI updates automatically on supported systemd Linux hosts; `--no-gui-updater` opts out.
- Bumped the manager to 2.5.0 and the theme payload to 26.08.6 without changing the pinned ITFlow 26.08 baseline.

## [2.4.0] - 2026-08-09

### Added

- Rebuilt the administration manager as a full Theme Studio with live authentication preview.
- Added secure PNG, JPEG, and WebP logo upload, placement controls, branding fallback, and logo removal.
- Added editable brand identity, login copy, and client-portal copy.
- Added five curated color presets, seven editable palette roles, and live accessibility contrast feedback.
- Added corner style, content density, interface scale, and reduced-motion controls.
- Added validated configuration import, client-side JSON export, and one-click defaults reset.
- Added a CSP-compatible same-origin generated stylesheet for validated custom properties.

### Security

- Customization continues to require ITFlow administrator access, CSRF validation, and audit logging.
- Settings are allow-listed, size-limited, and written atomically; uploaded images are inspected by content and restricted to safe raster formats.
- Arbitrary CSS, SVG, executable uploads, lifecycle shell access, and PHP file replacement remain unavailable to the web service.

### Changed

- Expanded the managed payload from 17 to 18 files while retaining the same verified ITFlow 26.08 baseline.
- Preserved customization and uploaded branding across CLI disable, uninstall, and package upgrades.

## [2.3.0] - 2026-08-09

### Added

- Added a dedicated **Administration → NEXUS → Theme Manager** page with package, payload, compatibility, and core-asset status.
- Added an administrator-only, CSRF-protected control for activating or pausing the Nexus visual layer without granting web access to package lifecycle operations.
- Added audit and application logging for in-app theme state changes.
- Added an isolated administration preview and README screenshot.

### Changed

- Expanded the managed payload from 13 to 17 files and the verified ITFlow baseline from 12 to 13 templates.
- Made shared theme loading conditional on the administration-controlled presentation state.
- Updated lifecycle ordering so dependent navigation and templates are restored before shared Nexus assets are removed.

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
[2.3.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.3.0
[2.4.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.4.0
[2.5.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.5.0
[2.5.1]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.5.1

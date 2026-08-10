# Changelog

All notable changes to this project are documented here.

## [3.0.1] - 2026-08-10

### Fixed

- Connected content density to the live navigation preview and made layout changes automatically reveal the affected preview surface.
- Made compact, comfortable, and spacious content and menu density states visibly distinct in both Theme Studio and the installed ITFlow interface.
- Strengthened solid, gradient, and glass header treatments so each selection has an immediately recognizable result.
- Applied rail and outline active-navigation treatments to nested links as well as top-level items, with sufficient specificity to override the base active state.
- Made compact-sidebar mode visibly condense sidebar labels, icons, section headings, spacing, and preview width.

### Validation

- Added lifecycle assertions for every repaired layout control and browser-tested computed style changes for content density, menu density, header treatment, active navigation, and compact sidebar mode.
- Refreshed the Theme Studio screenshot from an isolated local preview using the release stylesheet.

## [3.0.0] - 2026-08-10

### Added

- Added an interactive sidebar and header preview alongside the existing authentication preview in Theme Studio.
- Added separate light and dark logos, automatic contrast selection, logo sizing and alignment, a custom favicon, browser title, and configurable login background imagery.
- Added animated GIF support for both logo variants, preserving embedded playback timing including 24fps animations in Theme Studio and rendered ITFlow surfaces.
- Added sidebar width and compact-mode controls, independent menu density, header colors and solid/gradient/glass treatments, and pill/rail/outline active navigation styles.
- Added named saved presets with validated JSON import/export and a 20-preset safety limit.
- Added future theme activation or pause scheduling that runs safely on the first ITFlow request at or after the selected time.
- Added a reversible one-click rollback that atomically swaps the active and immediately previous design.

### Security and compatibility

- New images retain the existing content inspection, MIME verification, dimension limits, fixed filenames, and same-origin storage model.
- Schedules, presets, active settings, and rollback snapshots are size-limited, validated on every read, and written atomically inside ITFlow's uploads directory.
- Existing single-logo settings migrate automatically to the light-logo slot without requiring a reset.

### Validation

- Added lifecycle coverage for new defaults, allow-listing, layout classes, sidebar clamping, presets, scheduling, and reversible rollback.
- Parser-validated every PHP file and browser-validated the Theme Studio at 1440px and 390px with no horizontal overflow.

## [2.6.0] - 2026-08-10

### Added

- Added Subtle, Fluid, and Snappy motion profiles for modal windows, dropdown menus, tooltips, popovers, Select2 menus, date pickers, alerts, toasts, and floating notifications.
- Added an interactive Theme Studio motion selector and preview modal so administrators can compare profiles before saving.
- Added softened modal backdrops, spatial window transitions, and reveal treatments that preserve Popper-positioned element transforms.
- Connected the Theme Studio hero background and design-system mark to the selected palette, including immediate color-picker and preset previews.

### Accessibility

- Existing Theme Studio reduced-motion controls now suppress the new animation system as well as hover motion.
- Operating-system `prefers-reduced-motion` remains authoritative across every animated surface.
- Animations are short, finite, and decorative; modal focus management and Bootstrap interaction behavior remain unchanged.

### Validation

- Added lifecycle coverage for motion-profile defaults, allow-list validation, generated body classes, animation assets, and reduced-motion safeguards.
- Browser-validated modal timing, backdrop treatment, floating-menu positioning, reduced-motion duration, and responsive dialog bounds.

## [2.5.4] - 2026-08-10

### Added

- Added an independent Theme Studio placement control for showing the uploaded logo in the technician navigation header.
- Existing saved configurations inherit technician-navigation logo placement on upgrade without requiring a reset.

### Fixed

- Replaced the clipped technician-header wordmark with the configured custom logo while preserving the existing dashboard link and an accessible text name.
- Added a polished fallback mark, bounded fallback title, compact collapsed state, and rounded navigation toggle so long company names no longer collide with the header controls.

### Validation

- Added lifecycle coverage for the placement default, legacy-settings upgrade path, generated logo CSS, accessible text treatment, and independent opt-out.
- Browser-validated the expanded and narrow technician navigation layouts against the release stylesheet.

## [2.5.3] - 2026-08-10

### Fixed

- Fixed GUI update activation when systemd's private temporary directory is on a different filesystem from `/opt`. Verified releases are now downloaded and extracted inside a root-only staging directory on the `/opt` filesystem before the final atomic rename.
- Replaced the generic `/opt` move failure with an activation error tied to the protected package root.

### Added

- Added a `repair-service` updater command that refreshes only the privileged updater helper while preserving the active theme version and package registration.
- Added `install-latest.sh --repair-gui-updater` as a verified recovery path for affected 2.5.0–2.5.2 installations.

### Validation

- Added regression coverage proving the update workspace shares the package activation filesystem.
- Added coverage for the verified repair bootstrap and unchanged updater security constraints.

## [2.5.2] - 2026-08-10

### Fixed

- Replaced the administration sidebar's oversized rectangular return header with a compact, rounded back control that blends with the Nexus navigation.
- Made image and text branding explicitly mutually exclusive on the main login, password-reset, and MFA-enforcement pages.
- Suppressed stray title nodes inside logo-mode authentication headers so a custom logo cannot be followed by duplicate company-name text.

### Changed

- The administration navigation badge now reports the installed manager version instead of a stale hard-coded value.
- Bumped the theme payload to 26.08.7 without changing the pinned ITFlow 26.08 baseline.

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
[2.6.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.6.0
[2.5.4]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.5.4
[2.5.3]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.5.3
[2.5.2]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.5.2
[2.5.1]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v2.5.1
[3.0.1]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.0.1
[3.0.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.0.0

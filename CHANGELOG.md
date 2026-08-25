# Changelog

All notable changes to this project are documented here.

## [3.6.0] - 2026-08-25

### Added

- Added a live accessibility inspector for body and primary-action contrast, reduced-motion state, branding alternatives, login copy, and compact-navigation label hygiene.
- Added desktop, tablet, mobile, and custom-width responsive testing directly around the exact runtime preview.
- Added an adjustable viewport-width control with active preset feedback so administrators can inspect each supported surface without leaving Theme Studio.

### Changed

- Runtime preview frames now use shared width and scale variables, keeping all four preview surfaces consistent while the responsive tester changes viewport size.
- Updated the README, compatibility table, installation examples, release screenshot, and release documentation for v3.6.0 / payload 26.08.20.

### Validation

- Added lifecycle assertions for the accessibility inspector, responsive presets, custom-width control, and shared preview sizing variables.
- Re-ran the complete PHP, lifecycle, updater, Linux activation, shell, checksum, and release-package validation workflows.

## [3.5.0] - 2026-08-20

### Added

- Added a private draft workspace that keeps every saved design change off live authentication, technician, client, and guest surfaces until an administrator explicitly publishes it.
- Added named, atomic publications and durable history for up to 50 revisions, including actor, timestamp, settings hash, exact field-level comparison, and restore-to-draft controls.
- Added filesystem-serialized draft-version checks so a stale administrator tab or simultaneous request cannot silently overwrite newer Theme Studio work.
- Added exact sandboxed previews for authentication, technician, authenticated-client, and guest-invoice surfaces.

### Changed

- Preview documents and live pages now derive identity, body classes, logo selection, and layout state from the same validated presentation model and load the same Nexus and custom stylesheets.
- Uploaded branding now uses immutable filenames so draft media, published media, and revision restores cannot collide in browser or proxy caches.
- Preset application, configuration import, asset removal, and reset now update the private draft and require the same concurrency token as primary design saves.
- Replaced the legacy simulated preview rules with server-rendered runtime snapshots and a clear stale-preview message while form edits remain unsaved.

### Validation

- Added lifecycle coverage for draft isolation, exact comparisons, stale-write rejection, immutable asset cleanup, four-surface preview generation, atomic publication, revision capture, restoration, and discard behavior.
- Browser-validated preview switching and revision comparison at desktop width, then verified responsive controls and zero page-level horizontal overflow at 390px.

## [3.4.1] - 2026-08-20

### Changed

- Empty branding fields and Theme Studio previews now use the neutral `Nexus MSP` placeholder instead of exposing the configured ITFlow company name.
- Leaving the display-name field blank still preserves ITFlow's native company identity on live pages.

### Fixed

- Theme Studio no longer creates document-level horizontal overflow at phone and tablet widths; its seven-section submenu remains independently swipeable with scroll snapping.
- Mobile save actions no longer stick over form controls, and preview, upload, scheduling, updater, configuration, and modal actions now stack into touch-friendly layouts.
- The technician Ticket queue now uses a clean vertical heading and compact one-column metric layout on phones while retaining two columns on tablets.

## [3.4.0] - 2026-08-10

### Added

- The real technician Tickets page now includes the four-card Ticket queue pulse previously shown only in the Theme Studio preview.
- Open, waiting-on-client, High/Urgent, and median first-response values are calculated from live ITFlow ticket data within the signed-in technician's client-access scope.
- The queue row adapts from four columns to two and then one on smaller screens.

### Lifecycle

- Web Pause skips both the queue queries and its markup immediately.
- CLI Disable and Uninstall restore the pinned ITFlow `agent/tickets.php` baseline byte-for-byte; Enable reapplies the managed page.

### Validation

- Added source assertions for live priority, reply-direction, response-time, access-state, and responsive styling behavior.
- Re-ran PHP syntax, lifecycle, updater, manifest, package, and GitHub Actions checks for the expanded managed-file set.

## [3.3.0] - 2026-08-10

### Added

- Theme Studio now uses persistent sub-navigation with focused sections for Branding, Colors, Layout, Motion, Content, Presets & Scheduling, and Updates & System.
- Section URLs are refresh-stable, making it possible to return directly to a specific settings area without losing the single-page editing workflow.
- Added a dedicated motion workspace with its own accessibility guidance instead of crowding animation controls into Layout.

### Changed

- Live preview appears only while editing design settings; presets, schedules, recovery, updates, import/export, and reset now open in their own full-width workspaces.
- The desktop section rail becomes a compact horizontally scrolling submenu on smaller screens.
- Save and apply remains available across every design section while management actions keep their existing isolated forms and protected lifecycle behavior.

### Validation

- Browser-validated Branding, Presets & Scheduling, and Updates & System section switching in an isolated local Theme Studio fixture.
- Added lifecycle assertions for the seven-section navigation, hash persistence, motion separation, hidden management panels, and responsive navigation treatment.
- Captured and reviewed the updated Theme Studio screenshot used by this README.

## [3.2.0] - 2026-08-10

### Added

- Added a dedicated URL-key-validated Nexus invoice PDF endpoint using ITFlow's bundled TCPDF runtime.
- Downloaded invoices now use the active Nexus brand name, tagline, light logo, palette, status, billing hierarchy, item table, notes, totals, and footer.
- Browser printing now preserves a compact paper-safe Nexus masthead with a dark-logo or brand-text fallback for reliable contrast.

### Lifecycle

- The original guest invoice template is now an exact managed baseline so disable and uninstall restore ITFlow's original download route byte-for-byte.
- The PDF builder and endpoint are theme-owned files removed by CLI disable or uninstall.
- Pausing Nexus from Theme Studio redirects the managed download link to ITFlow's original PDF renderer, so presentation deactivation removes the PDF customization without requiring a CLI operation.

### Validation

- Rendered the TCPDF output to PNG with Poppler and visually checked its one-page layout, headings, tables, totals, spacing, and footer.
- Added lifecycle coverage for the managed download route, safe content escaping, print masthead, pause fallback, install, disable, enable, and uninstall behavior.

## [3.1.1] - 2026-08-10

### Fixed

- The client portal navigation now renders the configured light/dark logo instead of always showing the decorative cyan brand marker.
- Portal logo visibility, size controls, accessible alternative text, and upload cache revisions now apply directly to the navigation brand on desktop and mobile.
- The welcome panel no longer duplicates the custom logo after it moves into the persistent portal navigation.
- Every Nexus surface now versions the static theme stylesheet, preventing new markup from rendering against stale cached CSS after an update.
- Guest invoice mastheads now receive their compact branded layout immediately after an update instead of exposing the logo at its intrinsic dimensions.

### Validation

- Added lifecycle assertions for the portal navigation logo markup, text fallback, and decorative-marker suppression.
- Added coverage that requires static stylesheet cache invalidation on login, recovery, MFA, technician, authenticated-client, and guest surfaces.
- Browser-validated the branded navigation image, responsive bounds, marker suppression, and absence of horizontal overflow.

## [3.1.0] - 2026-08-10

### Added

- Added the shared guest header to the managed overlay so public invoice URLs use the active Nexus logo, brand name, tagline, browser title, favicon, palette, and layout settings.
- Added a responsive guest billing masthead and invoice document treatment that visually bridges authentication, client portal, and public billing surfaces while preserving print output.

### Fixed

- Forced authentication titles such as **Welcome back** to use readable light text on the dark login card, independent of the light document text palette.
- Restored the configured brand tagline beneath login, password recovery, and MFA enrollment cards.
- Improved authentication supporting-copy, field-label, and primary-button contrast.
- Guest invoice company details and invoice metadata now stack cleanly on small screens without horizontal page overflow.

### Validation

- Browser-validated the guest invoice at 1440px and 390px, including computed colors, responsive stacking, and zero horizontal page overflow.
- Added lifecycle assertions for authentication title contrast, tagline rendering, branded guest integration, and the new pinned guest-header baseline.

## [3.0.2] - 2026-08-10

### Fixed

- Replacing a light or dark logo now rotates a validated asset revision and immediately changes every rendered logo URL, even when the filename and all other theme settings stay the same.
- Applied the same cache-busting behavior to technician navigation CSS, login and recovery pages, MFA enrollment, client navigation, Theme Studio previews, favicons, and login backgrounds.
- Asset removal and design rollback now rotate the revision as well, preventing an older browser-cached image from reappearing.
- Native ITFlow assets outside the protected Nexus upload directory remain untouched.

### Validation

- Added lifecycle coverage for revision generation, URL versioning, native-asset isolation, rendered surface integration, generated CSS, and rollback behavior.

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
[3.1.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.1.0
[3.1.1]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.1.1
[3.2.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.2.0
[3.4.1]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.4.1
[3.4.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.4.0
[3.3.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.3.0
[3.0.2]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.0.2
[3.0.1]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.0.1
[3.0.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.0.0
[3.5.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.5.0
[3.6.0]: https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v3.6.0

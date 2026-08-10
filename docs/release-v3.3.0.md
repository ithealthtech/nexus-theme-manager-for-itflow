# Nexus Theme Manager 3.3.0

This release reorganizes Theme Studio into a focused, navigable workspace so administrators can work on one part of the design system at a time.

## Added

- Persistent Theme Studio sub-navigation for Branding, Colors, Layout, Motion, Content, Presets & Scheduling, and Updates & System.
- Refresh-stable section URLs for returning directly to a specific manager workspace.
- A dedicated motion section with reduced-motion guidance.
- Responsive navigation that changes from a desktop rail to a compact horizontal submenu.

## Improved

- Design operations and protected system tools no longer compete with the editor and live preview for screen space.
- Presets and scheduling open as a focused full-width panel.
- Updates, configuration import/export, and reset share a separate system workspace.
- The live preview remains visible only where it helps evaluate design changes.
- Save and apply remains a single atomic action across all design sections.

## Lifecycle behavior

- No new ITFlow baseline files are modified by this release.
- Theme Studio remains a Nexus-owned file and is removed by CLI disable or uninstall with the rest of the manager overlay.
- All public, technician, portal, guest, print, and PDF customization behavior remains governed by the existing pause, disable, enable, rollback, and uninstall boundaries.

## Validation

- PHP 8.4 syntax validation and lifecycle source assertions
- Browser interaction checks for design, presets/scheduling, and system workspaces
- Desktop visual review using the release stylesheet and sanitized local data
- Package manifest, payload, and SHA-256 verification
- GitHub Actions validation on PHP 8.2, 8.3, and 8.4

Manager 3.3.0 ships theme payload 26.08.16 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

# Nexus Theme Manager 3.9.0

Version 3.9.0 turns Theme Studio into a broader visual design system and makes GUI updating a clear, observable workflow while preserving the protected service boundary introduced in earlier releases.

## Highlights

- Theme Studio displays release check, download, verification, protection, installation, and health-check stages.
- Status updates in place without reloading the administrator workspace.
- Each protected service status includes a bounded completion percentage and a clear phase label.
- Failed updates show actionable recovery guidance and can retry only the previous allow-listed check or update action.
- Automatic rollback reports whether the previous version was restored and verified or whether manual recovery is required.
- The updater distinguishes package staging, clean transition, application verification, and updater-registration failures.
- Progress, recovery, and action controls remain readable and touch-friendly on phones.

## Design system

- Five per-surface profiles independently override the shared technician, client portal, authentication, guest invoice, and print defaults.
- Preview and live ticket-queue metrics use the same server-rendered component, and every preview is shown in a side-by-side desktop/phone comparison.
- The navigation builder independently orders desktop and mobile items, changes labels and approved icons, hides items, and applies administrator/technician visibility.
- Automatic dark mode supports operating-system preference, forced light/dark, overnight schedules, user choice, a dedicated dark palette, and separate light/dark logo assets.
- The asset manager supports validated pixel crop and resize, dimension/size warnings, favicon preview, GIF frame-rate inspection including 24fps playback, and automatic WebP companions when PHP image support is available.
- Recovery mode can immediately bypass every Nexus customization, validate required managed files and CSS structure, or restore the latest pinned known-good revision after taking a safety snapshot.
- Runtime health gating falls back to native ITFlow presentation when required Nexus presentation files or CSS are incomplete, keeping login and administrator access available.

## Compatibility

- Nexus manager: 3.9.0
- Nexus payload: 26.08.23
- ITFlow release: 26.08
- Exact ITFlow commit: `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP: 8.1 or newer

## Security model

The administrator page remains read-only with respect to privileged execution. It polls a sanitized status file and queues only `check` or `update`; the root-owned systemd service still performs fixed-repository downloads, SHA-256 validation, bounded extraction, lifecycle verification, health checks, and rollback.

## Validation

- PHP syntax checks for every managed and baseline PHP file
- 272 lifecycle assertions, including profiles, navigation safety, dark-mode scheduling, shared components, asset processing, and recovery
- 35 GUI updater security assertions
- Linux systemd and same-filesystem activation checks in CI
- Manifest and complete package checksum verification
- Desktop and 390px phone visual verification with no horizontal overflow

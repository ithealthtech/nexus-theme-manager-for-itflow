# Nexus Theme Manager 3.9.0

Version 3.9.0 turns GUI updating into a clear, observable workflow while preserving the protected service boundary introduced in earlier releases.

## Highlights

- Theme Studio displays release check, download, verification, protection, installation, and health-check stages.
- Status updates in place without reloading the administrator workspace.
- Each protected service status includes a bounded completion percentage and a clear phase label.
- Failed updates show actionable recovery guidance and can retry only the previous allow-listed check or update action.
- Automatic rollback reports whether the previous version was restored and verified or whether manual recovery is required.
- The updater distinguishes package staging, clean transition, application verification, and updater-registration failures.
- Progress, recovery, and action controls remain readable and touch-friendly on phones.

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
- 257 lifecycle assertions
- 35 GUI updater security assertions
- Linux systemd and same-filesystem activation checks in CI
- Manifest and complete package checksum verification
- Desktop and 390px phone visual verification with no horizontal overflow

Version 3.8.0 remains the published stable release until this development version completes the normal pull-request and release workflow.

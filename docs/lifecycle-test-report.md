# Lifecycle test report

- Date: 2026-08-25
- Manager version: 3.7.0
- Payload version: 26.08.21
- PHP runtime: 8.4.24 CLI

## Syntax validation

- `manager.php`: Pass
- `updater.php`: Pass
- `tests/lifecycle.php`: Pass
- `tests/updater.php`: Pass
- Payload PHP files: 22/22 pass
- Baseline PHP files: 16/16 pass
- Total PHP files: 43/43 pass
- Shell entrypoints: 3/3 syntax checks pass

## Automated lifecycle simulation

Result: **246 assertions passed, 0 failed**

The test creates isolated initialized ITFlow fixtures and state roots, then runs the manager as external CLI processes.

| Area | Result |
|---|---|
| Non-mutating `doctor` | Pass |
| Admin permission, CSRF, no-shell, and navigation invariants | Pass |
| Exact 23-file installation | Pass |
| Post-install checksum verification | Pass |
| Installed PHP lint | Pass |
| Healthy enabled status | Pass |
| Duplicate install refusal | Pass |
| Invalid enable/disable state refusal | Pass |
| Web control defaults active, pauses, and reactivates | Pass |
| Settings validation, atomic create/replace, and safe CSS generation | Pass |
| Text sanitization, palette enforcement, scale limits, and contrast derivation | Pass |
| Private draft isolation, exact field comparison, and optimistic stale-write rejection | Pass |
| Eight-surface runtime preview generation from the shared live presentation model | Pass |
| Server-side quality audit, direct remediation targets, and safe draft corrections | Pass |
| Atomic named publication, revision capture, protected known-good designs, restore-to-draft, and discard | Pass |
| Automatic pre-operation snapshots and sanitized diagnostics | Pass |
| Immutable draft branding assets and orphan cleanup | Pass |
| Customization survives CLI disable | Pass |
| GUI updater setup requirement, allow-listed request queue, status sanitization, and busy-state refusal | Pass |
| Stale updater progress becomes a recoverable failure state | Pass |
| CLI disable clears the web presentation-state marker | Pass |
| Disable restores 16 originals | Pass |
| Disable removes seven theme-owned files | Pass |
| Enable reapplies and verifies payload | Pass |
| Post-install drift detection | Pass |
| Uninstall refuses to overwrite drift | Pass |
| Normal uninstall restores originals | Pass |
| Normal uninstall archives recovery state | Pass |
| Purge uninstall removes active state | Pass |
| Incompatible baseline refusal without mutation | Pass |
| Payload checksum tamper detection | Pass |
| Exact existing-payload adoption | Pass |
| Adoption preserves web presentation state and uninstall clears it | Pass |
| Adopted installation verification | Pass |
| Adopted-install uninstall/restore | Pass |
| Non-exact adoption refusal | Pass |

## Platform coverage

The lifecycle suite ran on Windows to exercise cross-platform path and replacement behavior. GitHub CI repeats linting and lifecycle tests on PHP 8.2, 8.3, and 8.4 under Linux. Run `doctor`, take verified backups, and perform a staging migration before promoting the package to production.

## GUI updater security suite

Result: **32 assertions passed, 0 failed**

The focused suite validates strict semantic versions, fixed request fields, unsafe request rejection, archive-root and traversal checks, checksum and checksum-filename enforcement, package manifest/version requirements, CLI argument allow-listing, web-entrypoint refusal, shell-free process execution, systemd filesystem hardening, generated unit syntax through `systemd-analyze verify` in Linux CI, and the automatic rollback path.

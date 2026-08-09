# Lifecycle test report

- Date: 2026-08-09
- Manager version: 2.3.0
- Payload version: 26.08.4
- PHP runtime: 8.4.15 CLI

## Syntax validation

- `manager.php`: Pass
- `tests/lifecycle.php`: Pass
- Payload PHP files: 16/16 pass
- Baseline PHP files: 13/13 pass
- Total PHP files: 31/31 pass
- Shell entrypoints: 3/3 syntax checks pass

## Automated lifecycle simulation

Result: **78 assertions passed, 0 failed**

The test creates isolated initialized ITFlow fixtures and state roots, then runs the manager as external CLI processes.

| Area | Result |
|---|---|
| Non-mutating `doctor` | Pass |
| Admin permission, CSRF, no-shell, and navigation invariants | Pass |
| Exact 17-file installation | Pass |
| Post-install checksum verification | Pass |
| Installed PHP lint | Pass |
| Healthy enabled status | Pass |
| Duplicate install refusal | Pass |
| Invalid enable/disable state refusal | Pass |
| Web control defaults active, pauses, and reactivates | Pass |
| CLI disable clears the web presentation-state marker | Pass |
| Disable restores 13 originals | Pass |
| Disable removes four theme-owned files | Pass |
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

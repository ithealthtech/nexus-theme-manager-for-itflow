# Lifecycle test report

- Date: 2026-08-08
- Manager version: 1.0.0
- Payload version: 26.08.1
- PHP runtime: 8.4.16 CLI

## Syntax validation

- `manager.php`: Pass
- `tests/lifecycle.php`: Pass
- Payload PHP files: 12/12 pass
- Baseline PHP files: 12/12 pass

## Automated lifecycle simulation

Result: **55 assertions passed, 0 failed**

The test creates isolated initialized ITFlow fixtures and state roots, then runs the manager as external CLI processes.

| Area | Result |
|---|---|
| Non-mutating `doctor` | Pass |
| Exact 13-file installation | Pass |
| Post-install checksum verification | Pass |
| Installed PHP lint | Pass |
| Healthy enabled status | Pass |
| Duplicate install refusal | Pass |
| Invalid enable/disable state refusal | Pass |
| Disable restores 12 originals | Pass |
| Disable removes theme-owned CSS | Pass |
| Enable reapplies and verifies payload | Pass |
| Post-install drift detection | Pass |
| Uninstall refuses to overwrite drift | Pass |
| Normal uninstall restores originals | Pass |
| Normal uninstall archives recovery state | Pass |
| Purge uninstall removes active state | Pass |
| Incompatible baseline refusal | Pass |
| Incompatible baseline remains unmodified | Pass |
| Payload checksum tamper detection | Pass |
| Exact existing-payload adoption | Pass |
| Adopted installation verification | Pass |
| Adopted-install uninstall/restore | Pass |
| Non-exact adoption refusal | Pass |

## Existing theme QA retained

- Static request/markup/theme checks: 110 passed, 0 failed
- Responsive preview matrix: 21/21 page/viewport combinations passed
- Production PHP lint during the original deployment: 12/12 before and after installation
- Production installed-file integrity during the original deployment: 13/13

## Platform coverage

The automated lifecycle suite ran on Windows to exercise cross-platform path and replacement behavior. The payload itself previously passed PHP 8.4.16 lint and live activation on Debian 12 with Apache. A final operator should still run `doctor`, take an application backup, and perform a staging install on each new server before production promotion.

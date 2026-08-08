# Nexus Theme Manager for IT Flow Verification Report

- Date: 2026-08-08
- Package version: 2.0.0
- Payload version: 26.08.2
- Baseline: ITFlow 26.08 at `ccaa45b0ae9900ad731a6491559f65ff8d87a8f3`
- Test runtime: PHP 8.4.24 CLI

## Release checks

| Check | Result |
|---|---|
| `manager.php` syntax | Pass |
| Lifecycle test syntax | Pass |
| 12 payload PHP templates | Pass |
| 12 baseline PHP templates | Pass |
| Lifecycle simulation | 55 passed, 0 failed |
| Manifest payload hashes | 13/13 verified |
| Manifest baseline hashes | 12/12 verified |
| Old organization name/domain/internal namespace scan | No matches |
| Package-level SHA-256 manifest | Verified |

## Lifecycle coverage

The automated suite creates isolated ITFlow fixtures and protected state roots, then invokes the manager through external PHP CLI processes.

| Behavior | Result |
|---|---|
| Non-mutating compatibility preflight | Pass |
| Exact 13-file install and checksum verification | Pass |
| Duplicate install refusal | Pass |
| Disable restores 12 originals and removes the Nexus stylesheet | Pass |
| Enable reapplies the exact payload | Pass |
| Post-install drift detection | Pass |
| Conflict-safe uninstall refusal | Pass |
| Normal uninstall and recovery-state archive | Pass |
| Purge uninstall | Pass |
| Incompatible baseline refusal without mutation | Pass |
| Exact existing-payload adoption | Pass |
| Non-exact adoption refusal | Pass |
| Package tamper detection | Pass |

## Rebrand verification

- Public name: `Nexus Theme Manager for IT Flow`.
- Package ID: `org.nexus-theme-manager.itflow`.
- Stylesheet: `payload/css/nexus-theme.css`.
- CSS scope and tokens: `.nexus-*` and `--nexus-*`.
- Default Linux state root: `/var/lib/nexus-itflow-theme`.
- Windows fallback state: `.nexus-theme-manager-state` beneath the fixture root.
- No company-specific domain, navigation destination, footer destination, package identifier, CSS prefix, or temporary-file prefix remains in the current tree.
- Company names, logos, permissions, modules, and white-label behavior remain driven by ITFlow configuration and session data.

## Accessibility and responsive invariants

The rebrand preserves the previously designed color values and layout behavior while renaming their selectors. Static inspection confirms that the payload still contains:

- persistent labels and explicit authentication field IDs;
- visible `:focus-visible` styling;
- reduced-motion handling;
- print normalization;
- contained table overflow at narrow widths;
- semantic status text in addition to color;
- no remote font, stylesheet, tracker, or hotlinked image dependency.

## Deployment acceptance

The automated package is ready for a staging migration. A production operator must still:

1. Back up the ITFlow application and database.
2. Verify and uninstall version 1.0.0 with its original manager.
3. Run the Nexus 2.0.0 `doctor` command.
4. Install Nexus and reload the PHP/web service gracefully.
5. Smoke-test login, MFA, password recovery, customer tickets, technician navigation, administration, and configured integrations.
6. Retain the archived 1.0.0 recovery state until acceptance is complete.

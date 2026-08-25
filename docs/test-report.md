# Nexus Theme Manager for IT Flow Verification Report

- Date: 2026-08-25
- Package version: 3.9.0
- Payload version: 26.08.23
- Baseline: ITFlow 26.08 at `89b080b430aaafba5d520c4e52c57b28a9559085`
- Test runtime: PHP 8.3.33 CLI

## Release checks

| Check | Result |
|---|---|
| `manager.php` syntax | Pass |
| `updater.php` syntax | Pass |
| Lifecycle test syntax | Pass |
| Updater security test syntax | Pass |
| 22 payload PHP files | Pass |
| 16 baseline PHP templates | Pass |
| Three shell entrypoints | Syntax pass |
| Lifecycle simulation | 272 passed, 0 failed |
| GUI updater security suite | 35 passed, 0 failed |
| Responsive browser checks | Side-by-side desktop/390px preview passed; 1280px shell measured 1265px with no page-level overflow |
| Manifest payload hashes | 23/23 verified |
| Manifest baseline hashes | 16/16 verified |
| Old organization name/domain/internal namespace scan | No matches |
| Package-level SHA-256 manifest | Verified |

## Lifecycle coverage

The automated suite creates isolated ITFlow fixtures and protected state roots, then invokes the manager through external PHP CLI processes.

| Behavior | Result |
|---|---|
| Non-mutating compatibility preflight | Pass |
| Administration permission, CSRF, no-shell, and menu invariants | Pass |
| Exact 23-file install and checksum verification | Pass |
| Duplicate install refusal | Pass |
| Administrator web control pause/reactivate | Pass |
| Customization validation, draft isolation, atomic publication, revision history, CSS generation, presets, and upgrade preservation | Pass |
| Eight-surface runtime preview parity and sandbox isolation | Pass |
| Responsive section picker, guided navigation, preview visibility, and compact surface picker | Pass |
| Login initialization order and native-interface failure fallback | Pass |
| Accessibility/responsive quality audit and safe private-draft corrections | Pass |
| Protected known-good revisions, automatic snapshots, and sanitized diagnostics | Pass |
| Optimistic concurrency and immutable draft-asset retention | Pass |
| Raster logo upload acceptance and non-image rejection through isolated HTTP runtime | Pass |
| Disable restores 16 originals and removes seven theme-owned files | Pass |
| Enable reapplies the exact payload | Pass |
| Post-install drift detection | Pass |
| Conflict-safe uninstall refusal | Pass |
| Normal uninstall and recovery-state archive | Pass |
| Purge uninstall | Pass |
| Incompatible baseline refusal without mutation | Pass |
| Exact existing-payload adoption | Pass |
| Adopted web-state preservation and uninstall cleanup | Pass |
| Non-exact adoption refusal | Pass |
| Package tamper detection | Pass |
| GUI request allow-listing and protected-service setup requirement | Pass |
| In-place stage polling, health-check progress, rollback outcome, recovery guidance, and retry controls | Pass |
| Per-surface validation and global-fallback resolution | Pass |
| Desktop/mobile navigation destination, icon, role, visibility, and order constraints | Pass |
| System, user, forced, and overnight scheduled dark-mode resolution | Pass |
| Mode-aware light/dark assets and WebP companion retention | Pass |
| Shared live/preview ticket component parity | Pass |
| Emergency disable, native presentation fallback, and managed-file/CSS health gating | Pass |
| Release version, checksum, archive, manifest, CLI, and no-shell invariants | Pass |
| systemd filesystem hardening and automatic rollback path | Pass |

## Identity and customization verification

- Public name: `Nexus Theme Manager for IT Flow`.
- Package ID: `org.nexus-theme-manager.itflow`.
- Stylesheet: `payload/css/nexus-theme.css`.
- CSS scope and tokens: `.nexus-*` and `--nexus-*`.
- Default Linux state root: `/var/lib/nexus-itflow-theme`.
- Windows fallback state: `.nexus-theme-manager-state` beneath the fixture root.
- No company-specific domain, navigation destination, footer destination, package identifier, CSS prefix, or temporary-file prefix remains in the current tree.
- ITFlow company identity remains the fallback; Nexus overrides are isolated, removable, and never rewrite company data.

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
2. Verify the v3.9.0 archive and companion SHA-256 file.
3. Run the Nexus 3.9.0 `doctor` command.
4. Install through the supported lifecycle or GUI updater and reload the PHP/web service gracefully.
5. Smoke-test login, MFA, password recovery, customer tickets, technician navigation, **Administration → NEXUS → Theme Manager**, surface profiles, desktop/mobile navigation ordering and roles, user/system/scheduled dark mode, side-by-side previews, asset crop/resize and GIF/WebP reporting, Save draft, Publish, known-good recovery, emergency disable/reactivate, diagnostics, updater progress/retry/rollback, and configured integrations.
6. Retain the previous release recovery state until acceptance is complete.

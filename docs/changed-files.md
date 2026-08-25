# Nexus Theme Manager for IT Flow File Manifest

Baseline: ITFlow 26.08 at `89b080b430aaafba5d520c4e52c57b28a9559085`

## Added

### Package and privileged helper

| File | Purpose | Privilege boundary |
|---|---|---|
| `updater.php` | Installs/removes the per-instance systemd bridge and executes fixed-policy, checksum-verified Nexus updates with staged progress, health checks, and explicit rollback outcomes | CLI/root only; rejects web execution and arbitrary arguments |
| `tests/updater.php` | Exercises updater request, progress context, recovery outcome, version, archive, checksum, manifest, argument, and hardening invariants | Test-only |
| `upgrade.php` | Performs a checksum-preceded manager-to-manager command-line upgrade, preserves active mode, and verifies automatic rollback | CLI only; accepts resolved package/root paths and invokes managers without a shell |
| `install-latest.sh` | Resolves and verifies the latest release, then installs fresh or invokes the transactional package upgrader for an existing managed installation | Root-only bootstrap; strict semantic versions, fixed repository, downgrade refusal |
| `tests/upgrade.php` | Exercises successful upgrade, mode preservation, deliberately failed activation, rollback, and post-operation verification | Test-only |

### Managed ITFlow payload

| File | Purpose | Upgrade conflict risk |
|---|---|---|
| `css/nexus-theme.css` | Scoped design system plus responsive Theme Studio, side-by-side previews, profile/navigation/asset/recovery controls, palette, density, sidebar/header, motion, radius, and responsive states | Low |
| `css/nexus-theme-custom.php` | CSP-compatible same-origin stylesheet generated exclusively from validated settings | Low |
| `includes/nexus_theme.php` | Presentation state, per-surface resolution, shared preview/live components, constrained navigation, automatic dark mode, recovery health gating, atomic drafts/publishing, protected revisions, quality checks, sanitized diagnostics, raster processing/WebP/GIF inspection, and fixed update request/status helpers | Low |
| `includes/nexus_invoice_pdf.php` | Pure escaped Nexus invoice-document HTML builder for ITFlow's bundled TCPDF runtime | Low |
| `admin/nexus.php` | Administrator-only Theme Studio with focused sub-navigation, side-by-side eight-surface previews, per-surface profiles, navigation builder, advanced asset metadata, dark mode, responsive testing, recovery mode, protected revisions, diagnostics, and a live staged updater | Low |
| `admin/post/nexus.php` | CSRF-protected state, validated asset processing, emergency disable/known-good recovery, settings, quality fixes, revision pinning, automatic snapshots, diagnostics, presets, schedule, rollback, import/reset, and allow-listed update/retry actions | Low |
| `guest/nexus_invoice_pdf.php` | URL-key-validated themed invoice download endpoint; redirects to ITFlow's original renderer whenever Nexus presentation is paused | Medium |

## Edited

| File | Change | Business/security logic |
|---|---|---|
| `login.php` | Loads static and generated theme CSS; applies custom logo, identity, and login copy while preserving auth behavior | Preserved |
| `includes/header.php` | Loads static/generated CSS; applies technician profile, mode-aware assets, health fallback, and constrained navigation without changing authorization | Preserved |
| `includes/top_nav.php` | Adds accessible labels, company link, and user-selectable light/dark/system control | Preserved |
| `client/includes/header.php` | Applies the custom identity and cache-busted logo directly to the persistent portal navigation, plus portal copy, generated palette, explicit landmarks, and responsive behavior | Module/permission conditions preserved |
| `client/includes/footer.php` | Uses the configured Nexus identity with ITFlow fallback; existing scripts and TinyMCE initialization preserved | Preserved |
| `client/index.php` | Clarifies support overview hierarchy and primary create-request action | Queries and permission branches preserved |
| `client/tickets.php` | Adds scan-friendly heading/CTA, responsive table container, hover state, and explicit textual status badge | Queries and routes preserved |
| `client/ticket_add.php` | Adds semantic card/heading, persistent labels, IDs, help association, responsive columns, cancel action, and clearer submit copy | Field names, CSRF, action, and handler preserved |
| `client/ticket.php` | Improves ticket/reply hierarchy, reply and attachment labels, action copy, and avatar alt text | Field names, CSRF, action, handlers, comments, and feedback preserved |
| `agent/tickets.php` | Adds the scope-aware live ticket-queue summary for open, waiting-on-client, high-priority, and median-response metrics while Nexus is active | Existing filters, queries, permissions, list/kanban rendering, and actions preserved |
| `client/profile.php` | Adds page heading and password label association | Field names, action, and handler preserved |
| `client/login_reset.php` | Applies generated palette, custom logo/identity, persistent labels/autocomplete, and accessible recovery treatment | Reset tokens, request keys, decisions, and redirects preserved |
| `agent/user/mfa_enforcement.php` | Applies generated palette and custom logo/identity while improving MFA labels and QR alternative text | MFA logic, token field, action, and redirects preserved |
| `guest/includes/guest_header.php` | Extends shared guest pages with the Nexus identity, tagline, favicon, responsive public-billing masthead, and generated theme styles | Guest URL-key validation, invoice queries, payment actions, and exports remain untouched |
| `guest/guest_view_invoice.php` | Routes the Download action through the managed Nexus PDF endpoint while the overlay is active | Invoice queries, payments, print action, history, and URL-key validation preserved |
| `admin/includes/side_nav.php` | Adds the **NEXUS → Theme Manager** administration navigation entry | Existing sections, conditions, and custom links preserved |

## Intentionally untouched

- `libs/adminlte/**`
- Bootstrap and all other files under `libs/**`
- `css/itflow_custom.css`
- `includes/inc_wrapper.php` and shared footer logic
- Agent/client sidebar PHP conditions and all pre-existing administration navigation conditions
- Authentication handlers, session decisions, rate limiting, MFA verification, CSRF validation, redirects, and authorization
- Database queries except where existing page output is rendered unchanged
- Existing POST handlers such as `client/post.php`; Nexus adds only its isolated admin handler
- Schema, migrations, ITFlow company logo/favicon records, and client data; Nexus stores only isolated settings and an optional raster logo under `uploads`

## Contract regression result

The overlay was compared file-by-file with the pinned archive. Across all 14 edited upstream PHP files, these sets are unchanged except for the isolated Nexus administration route and its explicit POST action:

- Request/session key references
- HTML field names
- Form methods
- Form actions
- PHP include/require targets

See `test-report.md` for current automated and staging verification coverage.

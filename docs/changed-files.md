# Nexus Theme Manager for IT Flow File Manifest

Baseline: ITFlow 26.08 at `89b080b430aaafba5d520c4e52c57b28a9559085`

## Added

### Package and privileged helper

| File | Purpose | Privilege boundary |
|---|---|---|
| `updater.php` | Installs/removes the per-instance systemd bridge and executes fixed-policy, checksum-verified Nexus updates with rollback | CLI/root only; rejects web execution and arbitrary arguments |
| `tests/updater.php` | Exercises updater request, version, archive, checksum, manifest, argument, and hardening invariants | Test-only |

### Managed ITFlow payload

| File | Purpose | Upgrade conflict risk |
|---|---|---|
| `css/nexus-theme.css` | Scoped design system plus Theme Studio, authentication/navigation previews, palette, density, sidebar/header, motion, radius, and responsive states | Low |
| `css/nexus-theme-custom.php` | CSP-compatible same-origin stylesheet generated exclusively from validated settings | Low |
| `includes/nexus_theme.php` | Presentation state, allow-listed customization, atomic settings/rollback/presets/schedule, palette derivation, inspected raster-asset storage, and fixed update request/status helpers | Low |
| `admin/nexus.php` | Administrator-only Theme Studio with live authentication/navigation previews, responsive branding, presets, scheduling, rollback, accessibility feedback, import/export, reset, and update actions | Low |
| `admin/post/nexus.php` | CSRF-protected state, settings, assets, presets, schedule, rollback, import/reset, and allow-listed update queue actions with audit/application logging | Low |

## Edited

| File | Change | Business/security logic |
|---|---|---|
| `login.php` | Loads static and generated theme CSS; applies custom logo, identity, and login copy while preserving auth behavior | Preserved |
| `includes/header.php` | Loads static and generated theme CSS; applies agent density, scale, motion, and palette while preserving accent and user dark mode | Preserved |
| `includes/top_nav.php` | Adds accessible names/label associations, meaningful image alt text, and discreet company website link | Preserved |
| `client/includes/header.php` | Applies custom identity, logo, portal copy, generated palette, explicit landmarks, and responsive navigation | Module/permission conditions preserved |
| `client/includes/footer.php` | Uses the configured Nexus identity with ITFlow fallback; existing scripts and TinyMCE initialization preserved | Preserved |
| `client/index.php` | Clarifies support overview hierarchy and primary create-request action | Queries and permission branches preserved |
| `client/tickets.php` | Adds scan-friendly heading/CTA, responsive table container, hover state, and explicit textual status badge | Queries and routes preserved |
| `client/ticket_add.php` | Adds semantic card/heading, persistent labels, IDs, help association, responsive columns, cancel action, and clearer submit copy | Field names, CSRF, action, and handler preserved |
| `client/ticket.php` | Improves ticket/reply hierarchy, reply and attachment labels, action copy, and avatar alt text | Field names, CSRF, action, handlers, comments, and feedback preserved |
| `client/profile.php` | Adds page heading and password label association | Field names, action, and handler preserved |
| `client/login_reset.php` | Applies generated palette, custom logo/identity, persistent labels/autocomplete, and accessible recovery treatment | Reset tokens, request keys, decisions, and redirects preserved |
| `agent/user/mfa_enforcement.php` | Applies generated palette and custom logo/identity while improving MFA labels and QR alternative text | MFA logic, token field, action, and redirects preserved |
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

The overlay was compared file-by-file with the pinned archive. Across all 13 edited upstream PHP files, these sets are unchanged except for the isolated Nexus administration route and its explicit POST action:

- Request/session key references
- HTML field names
- Form methods
- Form actions
- PHP include/require targets

See `test-report.md` for current automated and staging verification coverage.

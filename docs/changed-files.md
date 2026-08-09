# Nexus Theme Manager for IT Flow File Manifest

Baseline: ITFlow 26.08 at `89b080b430aaafba5d520c4e52c57b28a9559085`

## Added

| File | Purpose | Upgrade conflict risk |
|---|---|---|
| `css/nexus-theme.css` | Scoped tokens and styles for authentication, customer, agent, admin, light/dark, responsive, reduced-motion, and print states | Low |
| `includes/nexus_theme.php` | Fixed-path, atomic presentation-state control shared by managed surfaces | Low |
| `admin/nexus.php` | Administrator-only status and theme-control workspace | Low |
| `admin/post/nexus.php` | CSRF-protected theme state action with audit/application logging | Low |

## Edited

| File | Change | Business/security logic |
|---|---|---|
| `login.php` | Loads theme last; adds auth scope, state-aware headings/copy, persistent labels, accessible feedback, security note, Entra treatment, website return link | Preserved |
| `includes/header.php` | Loads theme after AdminLTE; adds agent scope while preserving accent and user dark mode | Preserved |
| `includes/top_nav.php` | Adds accessible names/label associations, meaningful image alt text, and discreet company website link | Preserved |
| `client/includes/header.php` | Adds explicit body/main landmarks, theme scope, responsive navigation, primary create-request action, welcome treatment, and accessible feedback | Module/permission conditions preserved |
| `client/includes/footer.php` | Closes main/body/html correctly and adds a dynamic company footer; existing scripts and TinyMCE initialization preserved | Preserved |
| `client/index.php` | Clarifies support overview hierarchy and primary create-request action | Queries and permission branches preserved |
| `client/tickets.php` | Adds scan-friendly heading/CTA, responsive table container, hover state, and explicit textual status badge | Queries and routes preserved |
| `client/ticket_add.php` | Adds semantic card/heading, persistent labels, IDs, help association, responsive columns, cancel action, and clearer submit copy | Field names, CSRF, action, and handler preserved |
| `client/ticket.php` | Improves ticket/reply hierarchy, reply and attachment labels, action copy, and avatar alt text | Field names, CSRF, action, handlers, comments, and feedback preserved |
| `client/profile.php` | Adds page heading and password label association | Field names, action, and handler preserved |
| `client/login_reset.php` | Applies auth design, persistent labels/autocomplete, accessible feedback, security note, and return links; repairs invalid presentation markup | Reset tokens, request keys, decisions, and redirects preserved |
| `agent/user/mfa_enforcement.php` | Applies auth design and improves MFA labels/autocomplete/QR alternative text | MFA logic, token field, action, and redirects preserved |
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
- Schema, migrations, uploads, company logo, favicon, and client data

## Contract regression result

The overlay was compared file-by-file with the pinned archive. Across all 13 edited upstream PHP files, these sets are unchanged except for the isolated Nexus administration route and its explicit POST action:

- Request/session key references
- HTML field names
- Form methods
- Form actions
- PHP include/require targets

See `test-report.md` for the 102-check static result and the staging checks still required.

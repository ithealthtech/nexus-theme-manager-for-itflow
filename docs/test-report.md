# IT Done Right / ITFlow Theme Verification Report

- Date: 2026-08-08
- Baseline: ITFlow 26.08 at `ccaa45b0ae9900ad731a6491559f65ff8d87a8f3`

Status terms:

- **Pass**: executed in this workspace with a passing result.
- **Not run**: requires a configured ITFlow/PHP/database/browser environment not supplied here.
- **Review**: static implementation exists, but production acceptance requires human or staging validation.

## Automated static result

**110 passed, 0 failed.**

Checks include:

- All request/session key reference sets preserved against the pinned archive
- All HTML field-name sets preserved
- All form methods and actions preserved
- All include/require targets preserved
- PHP parentheses, square brackets, braces, quotes, and comments balanced by a static lexer
- Composed customer HTML nesting balanced
- Standalone login/reset/MFA HTML nesting balanced
- Theme scope and stylesheet entry points present
- CSS delimiter balance
- No remote CSS import or HTTP(S) asset
- Theme scoped beneath `.itdr-theme`
- Reduced-motion and print rules present
- 15 explicit text/background contrast pairs at or above 4.5:1

### Measured contrast

| Pair | Ratio | Result |
|---|---:|---|
| Primary text on white | 17.55:1 | Pass |
| Muted text on white | 4.73:1 | Pass |
| Link blue on white | 4.91:1 | Pass |
| Auth text on dark | 17.49:1 | Pass |
| Auth muted on dark | 8.93:1 | Pass |
| Cyan on dark | 9.17:1 | Pass |
| Dark ink on violet gradient endpoint | 5.96:1 | Pass |
| Dark ink on cyan gradient endpoint | 10.46:1 | Pass |
| White on success button | 4.67:1 | Pass |
| White on danger button | 5.21:1 | Pass |
| White on info badge | 5.51:1 | Pass |
| White on success badge | 5.31:1 | Pass |
| White on danger badge | 5.73:1 | Pass |
| Dark ink on warning badge | 10.67:1 | Pass |

## Responsive visual result

Chromium-based static previews of login, customer portal, and technician UI were each rendered at all seven required viewports: **21 page/viewport combinations passed with no page-level horizontal overflow**.

| Viewport | Login | Customer | Technician |
|---|---|---|---|
| 320×568 | Pass | Pass; table scroll contained | Pass; table scroll contained |
| 375×812 | Pass | Pass; table scroll contained | Pass; table scroll contained |
| 768×1024 | Pass | Pass | Pass |
| 1024×768 | Pass | Pass | Pass |
| 1366×768 | Pass | Pass | Pass |
| 1440×900 | Pass | Pass | Pass |
| 1920×1080 | Pass | Pass | Pass |

The complete machine-readable measurements are in `responsive-qa-results.json`.

Additional browser observations:

- Theme stylesheet loaded at every viewport: Pass
- Visible primary heading at every viewport: Pass
- Page-level horizontal overflow: none
- Wide-table overflow at 320/375px: intentionally contained
- Changed preview console warnings/errors: none
- Authentication controls at 375px: 44px measured height
- Mobile auth-footer spacing: corrected during review and recaptured

## Accessibility checklist

| Check | Status | Evidence/remaining work |
|---|---|---|
| WCAG AA body text contrast | Pass | Measured pairs above |
| Visible `:focus-visible` | Pass | Central cyan ring rule; browser rule presence verified |
| Persistent auth/ticket labels | Pass | Added `label`/`for` and stable IDs on changed forms |
| Semantic page landmarks | Pass | Explicit client `<body>`, skip link, `<main>`, footer; auth headings |
| Icon-only accessible names | Pass | Top-nav menu/search/notification/site controls labeled |
| Status not color-only | Pass | Text labels remain in ticket/status/priority badges |
| Reduced motion | Pass | `prefers-reduced-motion` rule verified |
| 320px reflow | Pass | No page-level overflow; only tables scroll |
| Approx. 44px touch controls | Pass on changed narrow auth/actions | CSS and measured auth controls |
| 200% browser zoom | Review | Responsive reflow passes comparable CSS widths; manual zoom still required in staging |
| Full keyboard journey | Not run | Requires live dropdown/modal/collapse/plugin state |
| Screen reader pass | Not run | Run NVDA/JAWS and VoiceOver in staging |
| Error association in every upstream form | Review | Changed auth/customer feedback improved; application-wide forms are outside minimal-markup scope |

## Security and regression checklist

| Check | Status | Notes |
|---|---|---|
| Authentication POST names | Pass | Contract sets equal to baseline |
| MFA/reset tokens and field names | Pass | Contract sets equal to baseline |
| Form actions/methods | Pass | Contract sets equal to baseline |
| CSRF field names | Pass | Preserved |
| Session/request references | Pass | Preserved set comparison |
| Authorization/permission branches | Pass by source comparison | No branch removal; live role testing still required |
| Database queries | Pass by source review | Theme work did not rewrite queries |
| CSP compatibility | Pass by asset inspection | Self-hosted CSS/fonts/assets only; live header verification required |
| Secrets/client data in package | Pass | Static previews use sample data only |
| Analytics/trackers/remote fonts | Pass | None added |
| Vendor files | Pass | None edited |
| PHP syntax on target runtime | Pass | 12/12 edited PHP files passed before and after installation on PHP 8.4.16 |
| PHP warnings on live flows | Pass for smoke-tested routes | No new deployment-time Apache/PHP application error after agent, admin, login, client-redirect, and stylesheet checks |

## Functional flow matrix

The markup and request contracts are implemented, but these workflows cannot be honestly marked passed without a configured staging instance.

| Flow | Static/theme status | Staging status |
|---|---|---|
| Agent login | Implemented | Not run |
| Client login | Implemented | Not run |
| Invalid/rate-limited login feedback | Styled | Not run |
| Dual-role choice | Styled; controls preserved | Not run |
| MFA success/failure/timeout | Styled; controls preserved | Not run |
| Remember me | Styled; field preserved | Not run |
| Password reset | Styled; tokens/fields preserved | Not run |
| Entra link configured/unconfigured | Styled; condition preserved | Not run |
| Disabled client portal | Styled feedback path preserved | Not run |
| Logout | Navigation preserved | Not run |
| Ticket creation/validation | Form improved; fields/action preserved | Not run |
| Attachment upload | Field/action preserved | Not run |
| Ticket response | Composer improved; field/action preserved | Not run |
| Filtering/pagination | Shared components themed | Not run |
| Modal/dropdown/sidebar behavior | Existing AdminLTE behavior preserved | Not run |
| Accounting enabled/disabled | Conditions preserved | Not run |
| IT documentation enabled/disabled | Conditions preserved | Not run |
| Long/empty/high-volume data | Static examples reviewed | Not run with live data |

## Browser matrix

| Browser | Status |
|---|---|
| Chromium-based in-app browser | Pass for static visual/responsive checks and live agent/admin smoke tests |
| Current Chrome | Not run separately |
| Current Edge | Not run |
| Current Firefox | Not run |
| Current Safari | Not run |

## Release gate

The exact overlay was reconciled with the live ITFlow 26.08 baseline and deployed on 2026-08-08. Production installation, integrity, PHP-lint, Apache health, public-login, stylesheet, agent, and administration smoke gates passed. See `production-deployment.md`.

The following acceptance items remain open and should not be inferred as tested:

1. Authenticated client-only ticket, reply, attachment, profile, reset, MFA, and Entra flows.
2. Current Edge, Firefox, and Safari.
3. Manual keyboard, 200% zoom, NVDA/JAWS, and VoiceOver passes.
4. Upstream rebase/merge validation before updating the production checkout beyond the pinned baseline.

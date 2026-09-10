# Migration plan — Nexus Theme Manager to ITFlow 26.09

Status: Stages B, C and D plus the §4 housekeeping shipped as 4.0.0
(`docs/release-v4.0.0.md`). Stage A (drift detection and re-apply, §2.4) shipped as
4.1.0 (`docs/release-v4.1.0.md`). Stage E (the new 26.09 surfaces, §2.5) is the
only one still outstanding.

**Decisions taken (2026-09-03):**

- **Keep every overlay.** The Nexus theme is the priority; we do not concede surfaces to
  upstream's native theming. All 16 overlaid files are re-authored against 26.09,
  including a from-scratch rewrite of `client/profile.php`. §5 questions 1 and 2 are
  closed on that basis.
- **26.09 only, clean break.** One baseline set, one payload set. 3.9.1 remains the last
  release for installs still on 26.08. §5 question 4 closed.

Package today: `3.9.1`, theme `26.08.24`, pinned to ITFlow release `26.08` at commit
`89b080b430aaafba5d520c4e52c57b28a9559085`.

Target: ITFlow `26.09.2`, master commit `5dcc99d8802ccb28970cd51a49d635e3e0a4908b`.

Sources: the v26.09 release notes on forum.itflow.org (thread 3081), plus a direct diff
of our 16 baseline files against a fresh clone of `itflow-org/itflow`.

## 1. Current state: the package will not install on 26.09

`manager.php:567` refuses installation when a target file does not hash to the recorded
`baseline_sha256`. **All 16 baselined files changed in 26.09**, so installation fails
outright rather than half-applying. That is the safety design working — but it means
26.09 support is a real migration, not a version-string bump.

Measured line deltas (`baseline/` vs 26.09.2):

| File | 26.08 | 26.09 | changed lines |
| --- | ---: | ---: | ---: |
| `client/profile.php` | 50 | 524 | 536 |
| `guest/guest_view_invoice.php` | 488 | 512 | 296 |
| `agent/tickets.php` | 567 | 566 | 87 |
| `client/includes/header.php` | 167 | 208 | 77 |
| `includes/header.php` | 50 | 85 | 65 |
| `agent/user/mfa_enforcement.php` | 156 | 166 | 62 |
| `includes/top_nav.php` | 115 | 110 | 59 |
| `client/includes/footer.php` | 72 | 97 | 39 |
| `admin/includes/side_nav.php` | 350 | 354 | 30 |
| `guest/includes/guest_header.php` | 35 | 46 | 29 |
| `login.php` | 825 | 819 | 28 |
| `client/ticket_add.php` | 120 | 112 | 26 |
| `client/ticket.php` | 377 | 377 | 22 |
| `client/tickets.php` | 113 | 117 | 18 |
| `client/login_reset.php` | 295 | 291 | 12 |
| `client/index.php` | 289 | 289 | 8 |

`client/profile.php` was rebuilt from scratch upstream (inline editing, PIN change,
recent sign-ins, recent activity). Our 50-line override cannot be rebased onto it — it
has to be re-authored against the new page, or dropped.

## 2. What 26.09 changed that hits us

### 2.1 Front-end framework migration (the bulk of the work)

AdminLTE 3.2.0 to 4.9.1, Bootstrap 4.6.2 to 5.3.8.

- **Layout skeleton renamed.** `content-wrapper`, `main-sidebar`, `main-header` become
  `app-main`, `app-content`, `app-sidebar`, `app-header`. Our usage:
  `payload/css/nexus-theme.css` (39 selectors), `payload/includes/nexus_theme.php` (8),
  `payload/includes/top_nav.php`, `payload/includes/header.php`,
  `payload/admin/includes/side_nav.php`.
- **Dropped v3 classes** — `text-bold`, `text-sm`, `btn-default`, `img-circle`,
  `.alert .icon`, sidebar badge positioning, the `small-box` watermark, and all sixteen
  theme colours. Upstream reproduces them in `css/itflow_custom.css` at v3's computed
  values, driven by one `--itflow-accent` variable per theme. We should consume
  `--itflow-accent` rather than maintain a competing colour system beside it.
- **Bootstrap 5 splits `.bg-*` from `.text-bg-*`**, so every `bg-dark` card and modal
  header in our payload needs its text colour restored explicitly.
- **`input-group-append` / `input-group-prepend` are deleted, not renamed**; selects move
  from `form-control` to `form-select`; `data-toggle="buttons"` becomes `.btn-check`;
  `custom-control`, `custom-select` and `custom-file` are gone.
- **`data-toggle` / `data-dismiss` / `data-target` become `data-bs-*`.** 44 hits in
  `payload/admin/nexus.php` (Theme Studio) alone, plus `top_nav.php`, `login.php`,
  `client/includes/header.php`, `guest_view_invoice.php`, `client/ticket_add.php` and
  `mfa_enforcement.php`.
- **Known trap from the notes:** `.input-group > .form-control` at specificity (0,2,0)
  outranks `.form-control-color`'s `width:3rem` at (0,1,0) and collapses colour swatches.
  Theme Studio is full of colour inputs — fix with a matching-specificity
  `.input-group > .form-control-color` rule, not a `w-auto` override.

### 2.2 jQuery and its plugin stack are gone

Removed upstream: jQuery, jQuery UI, select2, Inputmask, daterangepicker, Moment, Tempus
Dominus, toastr, pdfmake, Dropzone, Popper. Replacements: Tom Select, Flatpickr, IMask,
SweetAlert2, Bootstrap toasts, and `js/autocomplete.js`.

Our dead references:

- `select2` in `payload/agent/tickets.php` (7 selects), `payload/client/ticket_add.php`
  (3 selects), and `payload/css/nexus-theme.css`
  (`.select2-container--default`, `.select2-dropdown`, `.daterangepicker`).
  Replace with Tom Select, styled via `initTomSelect` / `refreshTomSelect`.
- `toastr` CSS and JS loaded by `payload/agent/user/mfa_enforcement.php` — Bootstrap toasts.
- Explicit jQuery `<script>` loads in `payload/client/includes/footer.php` and
  `payload/client/login_reset.php`.

New helpers we must adopt in `payload/admin/nexus.php` and anything served into a modal:

- `itflowReady()` — scripts injected into an ajax modal run after `DOMContentLoaded`, so
  a bare listener never fires. Upstream lists this as what silently broke five of their
  own features mid-cycle. Theme Studio is modal-heavy; this is our highest-risk item.
- `itflowBindOnce()` — replaces the namespaced `.off().on()` pattern.
- `itflowPostForm()` — reproduces jQuery's bracketed array encoding that `ajax.php`
  parses. Theme Studio forms posting arrays (nav builders, surface profiles) need it.
- `includes/modal_footer.php` re-executes `js/app.js` on every modal open, so **every
  initialiser needs a re-entry guard** or it double-initialises.

### 2.3 Upstream now does some of what we do

- Theme colour and custom CSS now apply to the client portal, guest pages, login and
  setup natively. Those surfaces previously ignored both, which was a large part of our
  reason for overlaying them.
- `css/itflow_custom.css` and `libs/sweetalert2/css/sweetalert2.min.css` are now loaded by
  the client portal, guest, login, setup and MFA headers — not just `includes/header.php`.
  Our `guest_header.php` and `client/includes/header.php` overrides likely now double-load
  these.

**Decision needed (§5):** for each overlaid surface, keep the overlay or drop it and ride
upstream's own theming? Every file we stop overlaying is a file we never rebase again.

### 2.4 The update mechanism now actively fights us — highest severity

From the release notes: running `php scripts/update_cli.php` with no arguments updates
files and database in one pass, **and the file update is forced — any local edits to
shipped files are discarded.** Maintenance > Update likewise hands the job to cron.

Nexus overlays 16 shipped files. An ordinary ITFlow update now silently reverts the whole
overlay, with no prompt and nothing in our UI to say it happened. The manager must:

1. Detect drift on load — recompute hashes of managed files against `manifest.json`
   `payload_sha256` and flag any that reverted to a native file.
2. Offer one-click re-apply from the installed package, reusing the existing verified
   install path.
3. Warn before an ITFlow update where we can see one queued.

Worth shipping regardless of how the rest of the migration is staged.

### 2.5 New surfaces with no Nexus styling

Canned responses (Admin > Templates), SLA holidays and closure days, the subnet IP address
section, the invoice/quote contact-picker modal and Quick Send, the Mark Sent reason
prompt, client account statements (agent, guest and portal PDF), the files
document/thumbnail view, the rebuilt portal profile and its activity page, the rebuilt
guest task-approval page, and the new collapsing client header. These render unstyled
against our theme until covered.

### 2.6 Not our problem

Payment delete/refund permission changes, fixed SLA clock behaviour on built-in statuses,
the `agent/asset.php` / `contact.php` / `locations.php` access-gating fix, the API
changes, and schema migrations through 2.7.8 touch no file we overlay.

## 3. Staging

**Stage A — safety. Ships first, independent of the rest.**
Drift detection and re-apply (§2.4), plus a hard compatibility gate: if the detected
ITFlow version is not the pinned one, refuse to install and say so, rather than failing on
an opaque baseline hash mismatch. Release as `3.10.0` against 26.08.

**Stage B — decided.** All overlays retained (see Decisions above). `docs/design-spec.md`
and `docs/changed-files.md` are updated in Stage D as each file lands.

**Stage C — re-baseline.** Copy the 26.09.2 version of every surviving overlaid file into
`baseline/`, recompute `baseline_sha256`, and repoint `compatible_itflow` to release
`26.09` at commit `5dcc99d8802ccb28970cd51a49d635e3e0a4908b`.

**Stage D — re-author payloads.** Per file, re-apply our changes onto the new upstream
version. Order by risk: `admin/nexus.php` (Theme Studio — 44 BS4 attributes plus all the
modal JS), then `css/nexus-theme.css` (the whole selector surface), then
`includes/nexus_theme.php`, then the client portal set, then guest, then login and MFA.
`client/profile.php` is a rewrite, not a rebase.

**Stage E — new surfaces** (§2.5).

**Stage F — validation.** Extend `tests/lifecycle.php` (272 assertions today) with the
drift-and-reapply case; re-run the desktop and 390px overflow checks; verify Theme Studio
end to end under `modal_footer.php`'s script re-execution; re-run the diagnostics export.

## 4. Housekeeping

`manifest.json` `compatible_itflow`; `README.md`; `docs/architecture.md`,
`docs/changed-files.md`, `docs/design-spec.md`; the GitHub Pages site under `pages/`
(`index.html`, `guide.html`, and `demo.html`, which is AdminLTE-3 markup);
`SHA256SUMS.txt`; `CHANGELOG.md`.

## 5. Open questions

1. **Which surfaces do we stop overlaying?** Now that upstream applies theme colour and
   custom CSS to the portal, guest, login and setup, several overlays may only be earning
   cosmetic polish. Candidates to drop: `login.php` (825 lines), `client/login_reset.php`,
   `guest/includes/guest_header.php`, `agent/user/mfa_enforcement.php`.
2. **`client/profile.php`** — re-author against upstream's rebuilt page, or drop the
   overlay and let the new page stand?
3. **Track ITFlow master or the 26.09 release tag?** Master is already at 26.09.2; pinning
   to a moving branch means re-baselining on every patch.
4. **Support both 26.08 and 26.09 from one package** (two baseline sets selected at install
   time), or cut 26.08 loose at a version boundary? One baseline set is much less to
   maintain.

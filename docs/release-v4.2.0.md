# Nexus Theme Manager 4.2.0

Target: ITFlow **26.09** (unchanged). Drop-in upgrade — no re-baseline, no new
overlaid templates.

## What this covers

ITFlow 26.09 added a run of new surfaces that Nexus did not yet style: canned
responses, SLA holidays and closure days, the subnet IP address section, client
account statements, Quick Send and Mark Sent on invoices and quotes, the rebuilt
guest task-approval page, and the portal activity page.

None of them introduced bespoke markup. Every one is assembled from stock
Bootstrap 5 and AdminLTE 4 parts, so 4.2.0 themes those parts rather than
overlaying each page. That adds no ITFlow template to rebase at each release, and
the next surface ITFlow builds from the same parts is themed on arrival.

| Component | Where 26.09 uses it |
| --- | --- |
| `thead.table-dark` | account statement, portal activity |
| SweetAlert2 dialogs | Quick Send, Mark Sent, payment deletion, guest approve and decline |
| intl-tel-input | every phone field, agent and portal |
| `.font-monospace`, tabular figures | IP addresses and hostnames, statement money columns, activity log |
| `.pagination` | every paginated list |
| `.nav-tabs` | the statement modal |
| `.list-group` | the SLA holiday list |
| Quick Send's bolt | leads the invoice and quote action menus in the warning accent |
| `.btn-lg`, inline alerts | the guest approval action and its confirmation screens |

## Three fixes to 4.0.0 found along the way

All three predate Stage E and affect every page, not just the new ones.

**Page titles were unreadable in light mode.** Dark header surfaces — a
`card-dark` header, anything `bg-dark`, the user-menu header — set a white
foreground on the header element, but the titles inside carry colours of their
own: `.card-title` takes `--nexus-text` and headings take Bootstrap's heading
colour. Both are dark in light mode, so the title went dark on the dark bar.
Measured in light mode on a live install: **1.39:1**, against the 4.5:1 WCAG
requires. Dark mode hid it, because the text token is light there.

This is the most visible of the three. ITFlow 26.09 uses dark card headers in 115
files (195 places), including the ticket queue, client, invoice and user lists, so
light-mode users were squinting at most primary page titles. Titles, headings and
bare icons inside a dark header now inherit its foreground; buttons, badges and
explicit colour utilities keep their own.

**Row striping and hover tints were invisible on every table.** Nexus tinted the
`<tr>`, but Bootstrap 5 paints each cell with `--bs-table-bg`, which sits above
the row, so the tint never showed. The same mechanism is how `thead.table-dark`
sets its palette, which is why the new statement headers escaped styling. Nexus
now drives the table variables instead. Measured afterwards on a live install:
odd cells carry `rgba(120, 136, 255, 0.055)` through Bootstrap's own inset shadow,
even cells none.

**The Nexus page colour never reached the page.** All three ITFlow 26.09 headers
put `bg-body-tertiary` on `<body>`, a Bootstrap utility declared `!important`.
Measured live, the body painted Bootstrap's dark tertiary `#2b3035` under Nexus
navy cards on the agent, client and guest surfaces alike. Only `.app-content` in
the agent shell was ever covered, and `body.nexus-guest` lost to the utility.

Bootstrap's own lever for that utility is `--bs-tertiary-bg-rgb`, an RGB triplet,
and it cannot be used here: `--nexus-page` is a hex colour Theme Studio sets at
runtime, and CSS cannot split one into the other. Nexus matches the utility's
`!important` at higher specificity instead, so the ground follows whatever palette
and mode are live.

## Upgrading

4.2.0 changes a theme-owned file (`css/nexus-theme.css`), so upgrade through
`upgrade.php` or `install-latest.sh` as usual. `manager.php reapply` will — correctly
— refuse to lay 4.2.0's stylesheet over 4.1.0's: the installed file matches neither
this package nor any ITFlow baseline, which is precisely what reapply is designed
to stop on.

## Validation

Verified on a live ITFlow 26.09.3 by rendering markup copied from each new surface
inside the real stylesheet stack, in `includes/header.php`'s order, with body
classes from `nexusThemePresentationModel()` and the real colour-mode script. The
authenticated pages themselves were not opened: that needs a sign-in, and the CSS
under test is identical either way. Every rule above was confirmed by computed
style rather than by eye, on the agent, client and guest surfaces.

Lifecycle, updater, upgrade, drift and installer-integration suites unchanged and
passing.

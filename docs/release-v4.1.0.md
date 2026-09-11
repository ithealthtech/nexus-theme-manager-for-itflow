# Nexus Theme Manager 4.1.0

Target: ITFlow **26.09** (unchanged from 4.0.0). Drop-in upgrade — no re-baseline,
no template changes.

## What this fixes

4.0.0 shipped with a known issue. ITFlow 26.09's `scripts/update_cli.php` runs
`git fetch --all` followed by `git reset --hard origin/<branch>`, and Maintenance
> Update now hands that same job to cron. Nexus overlays sixteen tracked ITFlow
templates, so an ordinary ITFlow update reverts the entire overlay.

Two properties of a hard reset matter here:

- **Tracked files land on exactly the upstream blob.** A reverted overlay file
  therefore hashes to precisely the baseline this package recorded, which is what
  lets 4.1.0 tell an ITFlow update apart from a hand edit with certainty rather
  than by guesswork.
- **Untracked files are left alone.** Every theme-owned file — `nexus_theme.php`,
  `nexus-theme.css`, `admin/nexus.php` and the rest — is untracked upstream, so
  they all survive. What is left is a half-state: the theme's machinery is
  entirely present and healthy, but no ITFlow template requires it any more.

That half-state is why nothing caught it. Theme Studio checked that Nexus-owned
files were present, and they were; it was the sixteen *ITFlow* templates around
them that had gone back to stock. `update_cli.php` does print "Discarding local
changes to 16 tracked file(s)" when run at a terminal, but the cron path buries
that in a log, and afterwards nothing on either side says the theme has stopped
applying.

An install deployed from a zip rather than a git checkout has no `.git`, so ITFlow
skips the application update entirely and the overlay is never touched.

4.1.0 makes all of this visible and reversible.

## After an ITFlow update

```bash
php manager.php status --root /var/www/itflow.example.com
```

```
CONFLICT: 5 managed file(s) were reverted, which is what an ITFlow update does.
Run reapply to restore them.
```

```bash
php manager.php reapply --root /var/www/itflow.example.com
```

`reapply` re-installs the payload over the reverted files and verifies the result.
It is idempotent — on a clean installation it reports "nothing to re-apply" and
changes nothing.

## What it will not do

Drift is classified three ways, because the responses differ:

| Classification | Meaning | `reapply` |
|---|---|---|
| `reverted` | Matches the supported ITFlow baseline, or a theme-owned file is absent | Restores it |
| `missing` | Managed file absent from the tree | Restores it |
| `modified` | Matches neither this package nor the baseline | **Refuses** |

A `modified` file is somebody's own edit, and overwriting it unasked is worse
than stopping. `reapply` names it and exits 3 without touching anything:

```
ERROR: Refusing to re-apply. These files were changed outside the theme manager.
Review them, then re-run with --force to overwrite:
- client/tickets.php
```

`--force` overwrites. Use it only once you know what those edits were.

If the tree is a *newer* ITFlow than this package supports, its templates also
classify as `modified` — correctly, since they are not files this package knows
how to overlay. The refusal says so and points at the Nexus release for that
version rather than suggesting `--force`.

## Reporting

`status` gained `drift`, `drift_counts`, `reapply_recommended`, `itflow_version`
and `itflow_version_supported`, all available under `--json` for monitoring.
`reapply_recommended` is true only when every drifted file is safely restorable,
so it is a sound trigger for an unattended `reapply --yes`.

Theme Studio's **Updates & system** workspace shows the same thing for
administrators who never touch the shell: the reverted surfaces by name, and the
command that restores them. It reports rather than offering a button — re-applying
is a filesystem operation over the installed package, which the web layer
deliberately cannot perform, and `admin/post/nexus.php` is barred from shelling
out.

## Clearer compatibility failures

Installing against the wrong ITFlow release used to print sixteen identical
"does not match the supported ITFlow baseline" lines. It now leads with the fact
that matters:

```
ERROR: This ITFlow installation is 26.08.9, but this package supports ITFlow 26.09.
Update ITFlow to 26.09 first, or install the Nexus release that targets 26.08.9.
No files were changed. Underlying detail:
```

A tampered tree at the *supported* release still gets the original message — the
version line only appears when the version is genuinely the reason.

## Reload PHP, or you will be looking at a lie

Worth knowing before you trust what the browser shows you: PHP's opcode cache
holds the compiled templates, so **the served pages lag the files on disk in both
directions.**

Measured on a live install, counting Nexus markup in the served `login.php`:

| | on disk | served |
| --- | ---: | ---: |
| Nexus installed | 49 | 14 |
| immediately after an ITFlow update | 0 | 14 |
| after reloading Apache | 0 | 0 |
| after `reapply`, before a reload | 49 | 0 |
| after `reapply` and a reload | 49 | 14 |

So an ITFlow update takes the theme off the site, and for a while the site keeps
serving it anyway. The breakage is real but invisible, which is one more reason
not to rely on noticing it by eye.

`manager.php status` reads the filesystem, so it is right immediately and is the
thing to trust. Reload the web or PHP-FPM service after `reapply` — the command
says so in its output for exactly this reason.

## Validation

New `tests/drift.php`, 23 assertions, wired into CI: detection and classification,
restore, idempotence, refusal, that a refused run leaves the external edit
byte-identical, `--force`, the disabled-mode refusal, and both compatibility-gate
paths. Lifecycle grows to 275. Updater 38 and upgrade 8 unchanged.

### Verified against a live ITFlow, not a fixture

Everything above was also run end to end against a real ITFlow **26.09.3**
(commit `dbf143d7`) on Ubuntu 26.04 — Apache, MariaDB, PHP 8.5, a genuine git
checkout, set up through ITFlow's own `scripts/setup_cli.php`. Reproduce it with
`tests/live/` (see the README there; it is not part of CI).

The run installed Nexus, confirmed the theme was being served, then invoked
**ITFlow's own `scripts/update_cli.php`** rather than any simulation of it:

- ITFlow reported `Discarding local changes to 16 tracked file(s)` and named all
  sixteen — the overlay, exactly.
- `status` then reported `{'reverted': 16, 'modified': 0, 'missing': 0}` with
  `reapply_recommended: true`. Every file landed on precisely its recorded
  baseline hash, confirming the classification is decidable rather than a guess.
- All **7 theme-owned files survived**, as a hard reset on untracked files
  implies, producing the half-state described at the top of this document.
- `nexusThemeOverlayDrift()` independently named all 16 reverted surfaces.
- `reapply` restored all 16 and `verify` passed.
- `disable` left every tracked file byte-identical to upstream.

One incidental finding from the same environment, unrelated to Nexus: Ubuntu
26.04 ships PHP 8.4+, where `imap` has left core and has no distribution package.
ITFlow still needs it for the mail parser, so a stock 26.04 host cannot run that
feature without building the PECL extension.

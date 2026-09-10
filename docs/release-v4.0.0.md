# Nexus Theme Manager 4.0.0

Target: ITFlow **26.09** at commit `5dcc99d8802ccb28970cd51a49d635e3e0a4908b`.

## Read this first

4.0.0 is a clean break. It installs on ITFlow 26.09 and refuses 26.08, the way it
refuses any unsupported tree — the preflight check names the mismatch rather than
touching a file. Installations still on 26.08 stay on 3.9.1.

Upgrade order matters: **update ITFlow to 26.09 first, then install Nexus 4.0.0.**
Installing over a 26.08 tree is refused, and 26.09's own updater will overwrite a
3.9.1 overlay on its way through (see Known issue below).

## Why a major version

ITFlow 26.09 moved from AdminLTE 3.2 / Bootstrap 4.6 to AdminLTE 4.9 / Bootstrap
5.3. All sixteen ITFlow templates Nexus overlays changed upstream — about 1,400
lines — so every baseline had to be replaced and every payload re-authored on top
of the new files. `client/profile.php` was rebuilt upstream from 50 lines to 524
and had to be re-authored rather than rebased.

## Changes that affect your own customisations

If you have written CSS or JavaScript against the Nexus class surface, expect to
revisit it:

- `content-wrapper` → `app-content`, `main-sidebar` → `app-sidebar`,
  `main-header` → `app-header`, `nav-sidebar` → `sidebar-menu`,
  `.wrapper` → `.app-wrapper`.
- `data-toggle` / `data-target` / `data-dismiss` → `data-bs-*`.
- `form-group` → `mb-3`, `custom-select` → `form-select`, `btn-block` → `w-100`,
  `img-circle` → `rounded-circle`, `mr-*`/`ml-*` → `me-*`/`ms-*`,
  `float-right`/`text-left` → `float-end`/`text-start`.
- `badge-info` and friends → `text-bg-info`; Bootstrap 5 split `.bg-*` from
  `.text-bg-*`.
- `input-group-append` and `input-group-prepend` wrappers are deleted, not
  renamed — the addon is now a direct child of `.input-group`.
- select2 and daterangepicker are gone. Style Tom Select (`.ts-wrapper`,
  `.ts-control`, `.ts-dropdown`) and Flatpickr (`.flatpickr-calendar`) instead.

## Dark mode

26.09 turns AdminLTE 4's colour-mode manager off (`data-lte-color-mode="off"`)
and paints `data-bs-theme` server-side from each user's ITFlow setting.
Bootstrap 5.3 reads only that attribute, so Nexus now drives it directly —
`data-bs-theme`, `data-color-scheme` and the `color-scheme` meta all move
together with the Nexus palette, on first paint and on every later change.

`nexusThemeInitialDarkMode()` resolves the server-rendered first paint. Under the
default `system` mode the server cannot know the visitor's OS preference, so it
defers to ITFlow's per-user `user_config_theme_dark` and the head script corrects
it during parse if the OS disagrees. Nexus dark mode set to `light`, `dark` or a
schedule overrides ITFlow's boolean outright.

## Known issue — ITFlow updates revert the overlay

26.09 changed how ITFlow updates itself. `php scripts/update_cli.php` with no
arguments forces the file update and **discards local edits to shipped files**,
and Maintenance > Update now hands the same job to cron. Nexus overlays sixteen
shipped files, so an ordinary ITFlow update silently reverts the whole overlay
with no warning from either side.

Until drift detection ships, after any ITFlow update run:

```bash
php manager.php status --root /var/www/itflow.example.com
```

and reinstall if it reports managed-file conflicts.

## Validation

- Lifecycle 272 assertions, updater 38, upgrade 8 — all passing.
- `doctor`, `install`, `verify`, `status` and `disable` exercised end to end
  against a clean ITFlow 26.09.2 checkout. After `disable`, all sixteen managed
  files are byte-identical to pristine upstream.

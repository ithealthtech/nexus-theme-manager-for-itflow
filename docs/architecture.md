# Lifecycle architecture

## Why this is a manager rather than a native ITFlow plugin

The supported ITFlow 26.08 source exposes an administration color selector, but it does not provide a plugin loader, theme manifest, template override registry, or lifecycle hooks. The official project distributes the application as a source checkout, and the official installer deploys that checkout directly.

A web-upload installer would have to introduce a PHP endpoint capable of replacing application code. Nexus does not do that. Installation, update execution, rollback, disable, and uninstall remain root-owned operations outside the web process. The installed administration page manages allow-listed presentation settings and raster branding assets and may queue only a fixed `check` or `update` request through ITFlow's administrator session, CSRF validation, audit log, and POST-handler conventions.

References:

- ITFlow source: https://github.com/itflow-org/itflow
- Official installation script: https://github.com/itflow-org/itflow-install-script

## State model

The default state root is `/var/lib/nexus-itflow-theme`. Each ITFlow document root receives a deterministic instance ID derived from the canonical path.

```text
/var/lib/nexus-itflow-theme/
  <instance-id>.lock
  <instance-id>/
    state.json
    original/
      ...exact pre-install files...
  archives/
    <instance-id>-<UTC timestamp>/
      state.json
      original/
```

The state file records package identity, version, ITFlow root, lifecycle mode, original and payload hashes, backup paths, timestamps, and original owner/group/mode metadata. It is written atomically with mode `0600`; state and backup directories use `0700`.

## Lifecycle states

```text
not installed --install/adopt--> enabled --disable--> disabled
                                  ^               |
                                  |----enable-----|

enabled/disabled --uninstall--> not installed + archived recovery state
enabled/disabled --uninstall --purge--> not installed
```

An interrupted installation first attempts automatic rollback. The operation lock lives outside the active state directory so uninstall can archive or purge state without releasing protection early.

## Conflict policy

- Install requires exact supported baseline hashes.
- Enable requires the exact backed-up originals to still be active.
- Disable and uninstall require exact managed payload hashes before restoring originals.
- Any mismatch is a conflict; the manager refuses to overwrite it.
- There is intentionally no `--force` overwrite option. Resolve or preserve drift explicitly, then retry.

This policy prevents an uninstall from silently discarding an ITFlow update, emergency edit, or another administrator's change.

## File activation

For each managed file, the manager creates a temporary file in the destination directory, applies the recorded permissions and ownership, then renames it over the target. On Linux this is a same-filesystem atomic replacement. Activation installs the shared helper before dependent pages and adds administration navigation last. Restoration reverses that order so links and dependent templates are removed before shared assets.

## Administration and customization control

The in-app manager is available only after ITFlow's standard administrator permission check. Every POST action validates the session CSRF token. Theme state uses the fixed `uploads/.nexus-theme-disabled` marker; settings, the previous-design snapshot, saved presets, and the activation schedule use separate fixed JSON files under `uploads`; and raster assets use allow-listed fixed filenames beneath `uploads/nexus-theme`. Settings and state writes use same-directory atomic replacement.

The settings schema allow-lists brand and browser text, portal copy, nine six-digit colors, logo sizing/alignment, authentication-background presentation, navigation treatments, bounded sidebar/interface sizing, placement switches, and reduced motion. Arbitrary CSS and executable values are never accepted. The generated palette is served as `text/css` from the same origin so strict authentication-page Content Security Policy remains intact.

Logo uploads accept PNG, JPEG, WebP, and GIF, while favicon and login-background uploads remain restricted to PNG, JPEG, and WebP. Animated GIF logos are stored without transcoding so their embedded frame timing is preserved. Nexus validates the HTTP upload, file size, decoded image type, MIME type, and dimensions; derives the destination extension from inspected content; rejects SVG; and never uses the client filename. Preset/configuration imports apply the same settings validator. Scheduling accepts only a future UTC timestamp and an enable/pause action. Each settings mutation snapshots the previous validated design, and rollback atomically swaps the two designs.

Pausing the visual layer does not replace PHP templates or alter protected manager state. Customization survives package disable and uninstall to support safe upgrades. Full package lifecycle commands remain available only through the root-owned CLI manager.

## GUI update bridge

GUI updates use a narrow filesystem queue rather than shell access from PHP. The administration handler atomically writes `uploads/.nexus-theme-update-request.json` containing only a schema number, an allow-listed action, and a random request ID. It cannot select a release, repository, URL, command, argument, destination, or file. A root-owned systemd path unit watches that exact filename and launches a fixed updater copy from `/usr/local/lib/nexus-theme-manager/<instance-id>` with a root-only configuration under `/etc/nexus-theme-manager`.

The helper accepts releases only from `ithealthtech/nexus-theme-manager-for-itflow`. It resolves a strict semantic-version tag, constructs the exact versioned ZIP and checksum filenames, enforces HTTPS, validates the SHA-256 record, rejects unexpected or traversing archive paths, and verifies the internal package manifest. Downloads and extraction use a randomized root-only staging directory beneath `/opt`, guaranteeing that final activation is a same-filesystem atomic rename even when systemd provides a private temporary mount. It then verifies and uninstalls the current release, runs the new release's `doctor`, `install`, and `verify`, and updates its protected registration. A failed activation attempts to uninstall any partial new activation and reinstall and verify the previous release.

Status flows back through fixed read-only JSON data in `uploads/.nexus-theme-update-status.json`. The systemd service runs with a private temporary directory, protected home directories, a strict read-only filesystem view, and explicit write access only to the ITFlow root, Nexus state, package, configuration, and stable-helper locations. The privileged helper still performs the same baseline compatibility and conflict checks as a manual lifecycle operation.

## Trust boundary

The manifest protects payload and baseline files from accidental corruption. It is stored in the same archive, so release authenticity relies on GitHub repository control and HTTPS while integrity is checked against the published outer ZIP SHA-256 before extraction. The updater never accepts a browser-provided source, and the manager never runs a package-supplied SQL migration. The administration endpoint cannot directly alter package code, backups, or lifecycle state.

# Lifecycle architecture

## Why this is a manager rather than a native ITFlow plugin

The supported ITFlow 26.08 source exposes an administration color selector, but it does not provide a plugin loader, theme manifest, template override registry, or lifecycle hooks. The official project distributes the application as a source checkout, and the official installer deploys that checkout directly.

A web-upload installer would have to introduce a PHP endpoint capable of replacing application code. Nexus does not do that: installation, updates, rollback, disable, and uninstall remain root-only CLI operations outside the document root. The installed administration page can only toggle a fixed presentation-state marker through ITFlow's existing administrator session, CSRF validation, audit log, and POST-handler conventions.

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

## Administration control

The in-app manager is available only after ITFlow's standard administrator permission check. Its POST action validates the session CSRF token and accepts only `enable` or `disable`. It writes or removes the fixed `uploads/.nexus-theme-disabled` marker atomically; the marker contains no secret or executable content. When absent, the Nexus visual layer is active by default.

Pausing the visual layer does not replace PHP templates or alter protected manager state. Full package lifecycle commands remain available only through the root-owned CLI manager.

## Trust boundary

The manifest protects payload and baseline files from accidental corruption. It is stored in the same archive, so authenticity comes from verifying the published outer ZIP SHA-256 before extraction. The manager never downloads code or runs a package-supplied SQL migration. The administration endpoint cannot alter package code, backups, or lifecycle state.

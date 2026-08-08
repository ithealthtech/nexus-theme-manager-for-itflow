# Lifecycle architecture

## Why this is a manager rather than a native ITFlow plugin

The supported ITFlow 26.08 source exposes an administration color selector, but it does not provide a plugin loader, theme manifest, template override registry, or lifecycle hooks. The official project distributes the application as a source checkout, and the official installer deploys that checkout directly.

A web-upload installer would have to introduce a new privileged PHP endpoint into the document root. That would increase attack surface and still require patching core templates. This package instead uses the existing PHP CLI runtime and remains outside the document root.

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

For each managed file, the manager creates a temporary file in the destination directory, applies the recorded permissions and ownership, then renames it over the target. On Linux this is a same-filesystem atomic replacement. The theme-owned stylesheet is removed while disabled or uninstalled.

## Trust boundary

The manifest protects payload and baseline files from accidental corruption. It is stored in the same archive, so authenticity comes from verifying the published outer ZIP SHA-256 before extraction. The manager never downloads code, runs a package-supplied SQL migration, or exposes a web endpoint.

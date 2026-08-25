# Nexus Theme Manager 3.9.1

Version 3.9.1 is a command-line update reliability release for the v3.9 design system.

## Command-line update

`install-latest.sh` now detects an existing managed Nexus installation and performs a verified version transition. The installer resolves the latest published GitHub release, downloads the versioned package and checksum, validates the archive, locates the currently registered package, and refuses mismatched state, missing packages, invalid versions, and downgrades.

The package upgrader verifies the current installation before changing files, removes the current managed payload through its own manager, checks the replacement against the restored ITFlow baseline, installs and verifies the replacement, and preserves the previous enabled or disabled mode. If replacement activation fails, it reinstalls and verifies the previous package before returning an error.

The bootstrap accepts the established `.zip.sha256.txt` release checksum name and the `.zip.sha256` compatibility name published with v3.9.0.

## Verified command

Replace the example document root with the actual ITFlow document root:

```bash
cd /tmp
curl --fail --location --output install-latest.sh \
  https://raw.githubusercontent.com/ithealthtech/nexus-theme-manager-for-itflow/main/install-latest.sh
chmod +x install-latest.sh
sudo ./install-latest.sh --root /var/www/itflow.example.com
```

Pass `--root` and its value separately. Installations using a custom state directory must also pass the same `--state-root` value used for the existing installation.

## Versions

- Nexus manager: 3.9.1
- Nexus payload: 26.08.24
- Compatible ITFlow release: 26.08
- Compatible ITFlow commit: `89b080b430aaafba5d520c4e52c57b28a9559085`

No database migration is required.

## Validation

- Successful enabled-mode upgrade through the package upgrader
- Deliberately corrupted replacement with automatic rollback
- Disabled-mode preservation across verified rollback
- Post-upgrade and post-rollback managed-file verification
- Installer strict-version, downgrade, package-location, and checksum-suffix regression checks
- Full lifecycle, updater, PHP lint, shell syntax, Linux activation, and package checksum workflows

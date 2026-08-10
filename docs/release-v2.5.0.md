# Nexus Theme Manager 2.5.0

Nexus 2.5.0 adds a secure update experience directly to Theme Studio while keeping privileged code replacement out of the ITFlow web process.

## Highlights

- Check GitHub for the latest Nexus release from the administration GUI.
- Install an available release with visible download, verification, rollback, installation, and completion status.
- One-time systemd setup is installed automatically by the standard bootstrap on supported Linux hosts.
- Exact repository, semantic-version tag, asset filename, SHA-256, archive layout, and package manifest validation.
- Automatic attempt to restore and verify the previous Nexus package if activation fails.
- Existing Theme Studio branding, palette, layout, content, preview, import/export, and logo controls remain intact.

## Privilege boundary

The authenticated CSRF-protected web action writes only a fixed request with `check` or `update`. It cannot submit a command, URL, repository, path, version, or manager option. A root-owned systemd path service executes the fixed updater helper, which downloads only from `ithealthtech/nexus-theme-manager-for-itflow` and invokes only the allow-listed manager lifecycle sequence.

## Installation

Fresh installs using `install-latest.sh` enable the GUI updater automatically. Existing or manually installed packages can enable it once with:

```bash
sudo php updater.php install-service --root /var/www/itflow.example.com
```

Then open **Administration → NEXUS → Theme Manager** and use **Check for updates**.

## Compatibility

- Manager 2.5.0 / theme payload 26.08.6
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer
- GUI update service: Linux with systemd, curl, and unzip

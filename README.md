# Nexus Theme Manager for ITFlow

[![CI](https://github.com/ithealthtech/nexus-theme-manager-for-itflow/actions/workflows/ci.yml/badge.svg)](https://github.com/ithealthtech/nexus-theme-manager-for-itflow/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/ithealthtech/nexus-theme-manager-for-itflow)](https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/latest)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

Nexus gives ITFlow a polished, customizable interface without changing its database or business logic. It adds an administrator-only Theme Studio, exact page previews, safe draft publishing, revision history, and verified updates.

![Nexus Theme Studio](docs/images/nexus-theme-studio-v3-8.jpg)

## What you can customize

- Light and dark logos, animated GIF logos, favicon, browser title, and login background
- Brand name, tagline, login copy, and client portal messaging
- Colors, typography scale, spacing, sidebar width, menu density, and compact mode
- Header treatments, active-navigation styles, corners, and interface motion
- Login, password reset, technician, client portal, guest invoice, print, and PDF surfaces
- Saved presets, scheduled activation, import/export, and protected revisions

Theme Studio also includes:

- Eight previews built from the same presentation model and stylesheet as the live pages
- Phone, tablet, laptop, widescreen, and custom-width responsive testing
- Accessibility and layout checks with direct links to affected settings
- Safe automatic corrections that remain unpublished until you approve them
- Automatic recovery snapshots and downloadable sanitized diagnostics
- A protected GUI updater with checksum verification and rollback

After installation, open **Administration → NEXUS → Theme Manager**.

## Compatibility

| Component | Supported version |
|---|---|
| Nexus manager | 3.9.0 |
| Nexus theme | 26.08.23 |
| ITFlow | 26.08 |
| ITFlow commit | `89b080b430aaafba5d520c4e52c57b28a9559085` |
| PHP | 8.1 or newer |
| Production host | Debian or Ubuntu with systemd |

Nexus checks the supported ITFlow templates before changing anything. A modified or unsupported ITFlow installation is refused without altering files.

## Install

Back up the ITFlow application and database first. Then run this as root, replacing the example path with your ITFlow document root:

```bash
curl --fail --location --remote-name \
  https://raw.githubusercontent.com/ithealthtech/nexus-theme-manager-for-itflow/main/install-latest.sh

chmod +x install-latest.sh
sudo ./install-latest.sh --root /var/www/itflow.example.com
```

The installer downloads the latest release, verifies its checksum, extracts it under `/opt`, checks ITFlow compatibility, installs the theme, verifies the result, and configures GUI updates.

Open Theme Studio after installation and confirm that the theme is active and the updater reports **Ready**.

### Manual install

Use the versioned ZIP and `.sha256.txt` file from the [latest release](https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/latest). Do not use GitHub's automatically generated source archive as the installation package.

```bash
sha256sum --check Nexus-Theme-Manager-for-ITFlow-3.9.0.zip.sha256.txt
sudo unzip Nexus-Theme-Manager-for-ITFlow-3.9.0.zip -d /opt
cd /opt/Nexus-Theme-Manager-for-ITFlow-3.9.0

sudo php manager.php doctor --root /var/www/itflow.example.com
sudo php manager.php install --root /var/www/itflow.example.com --yes
sudo php updater.php install-service --root /var/www/itflow.example.com
sudo php manager.php verify --root /var/www/itflow.example.com
```

## Update

Open **Theme Studio → Updates & System**, select **Check for updates**, then install the available release.

The web application only queues an allow-listed request. A root-owned systemd service downloads the fixed GitHub release, verifies the checksum and manifest, checks compatibility, installs it, runs health checks, and restores the previous release if activation fails.

Older releases that cannot update safely should be uninstalled with their original manager before running `install-latest.sh`.

## Pause or remove Nexus

The Theme Studio **Pause theme** button removes all Nexus presentation customizations immediately while keeping settings available for reactivation.

To restore the original ITFlow templates from the command line:

```bash
sudo php manager.php disable --root /var/www/itflow.example.com --yes
```

To re-enable them:

```bash
sudo php manager.php enable --root /var/www/itflow.example.com --yes
```

To uninstall Nexus while retaining an archived recovery copy:

```bash
sudo ./uninstall.sh --root /var/www/itflow.example.com --yes
```

Nexus refuses to overwrite a managed file that changed after installation.

## Important ITFlow update rule

Disable or uninstall Nexus before updating ITFlow. Install a Nexus release only when its compatibility table explicitly supports the new ITFlow revision.

ITFlow currently has no native theme or plugin hook system, so Nexus manages a bounded set of verified templates. It never claims compatibility with templates it has not tested.

## Documentation

- [v3.9.0 release notes](docs/release-v3.9.0.md)
- [Architecture and privilege boundaries](docs/architecture.md)
- [Managed file list](docs/changed-files.md)
- [Validation report](docs/test-report.md)
- [Lifecycle test report](docs/lifecycle-test-report.md)
- [Security policy](SECURITY.md)

## License

Nexus is an independent ITFlow integration licensed under GPL-3.0. See [LICENSE](LICENSE) and [NOTICE.md](NOTICE.md). Use GitHub Issues for reproducible defects.

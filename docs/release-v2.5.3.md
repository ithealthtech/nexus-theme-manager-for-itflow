# Nexus Theme Manager 2.5.3

Nexus 2.5.3 fixes GUI update activation on systemd hosts where the service's private temporary directory and `/opt` are different filesystems.

## Fixed

- Verified release downloads and extraction now occur in a randomized root-only staging directory under `/opt`.
- The final package activation remains an atomic rename, but now always occurs on the same filesystem.
- Failed staging directories remain constrained to the protected package root and are removed by the existing guarded cleanup routine.

## Recovery for 2.5.0–2.5.2

The verified bootstrap supports `--repair-gui-updater`. It downloads and verifies the newest release, refreshes only the stable privileged helper, restarts the path service, and preserves the currently active theme and registered version. After repair, Theme Studio can install 2.5.3 normally.

## Validation

- PHP syntax validation covers every PHP entrypoint and payload.
- Lifecycle and updater security suites pass against the packaged release.
- Regression coverage verifies that the staging directory is created directly on the activation filesystem.
- Linux CI validates systemd units and the complete package checksum inventory.

## Compatibility

- Manager 2.5.3 / theme payload 26.08.7
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer
- GUI update service: Linux with systemd, curl, and unzip

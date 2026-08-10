#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php "$SCRIPT_DIR/manager.php" uninstall "$@"

if [ "$(uname -s 2>/dev/null || true)" = "Linux" ] && [ "$(id -u)" -eq 0 ] && command -v systemctl >/dev/null 2>&1; then
    if ! php "$SCRIPT_DIR/updater.php" remove-service "$@"; then
        printf 'Warning: Nexus was uninstalled, but GUI updater cleanup failed. Run updater.php remove-service after resolving the reported systemd issue.\n' >&2
    fi
fi

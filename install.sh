#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php "$SCRIPT_DIR/manager.php" install "$@"

if [ "$(uname -s 2>/dev/null || true)" = "Linux" ] && [ "$(id -u)" -eq 0 ] && command -v systemctl >/dev/null 2>&1; then
    if ! php "$SCRIPT_DIR/updater.php" install-service "$@"; then
        printf 'Warning: Nexus installed, but GUI updater setup failed. Run updater.php install-service after resolving the reported systemd prerequisite.\n' >&2
    fi
fi

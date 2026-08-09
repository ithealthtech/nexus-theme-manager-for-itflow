#!/bin/sh
set -eu

REPOSITORY="ithealthtech/nexus-theme-manager-for-itflow"
RELEASES_URL="https://github.com/$REPOSITORY/releases"

usage() {
    cat <<'EOF'
Usage: sudo ./install-latest.sh --root PATH [--state-root PATH]

Downloads the latest published Nexus Theme Manager release from GitHub,
verifies its SHA-256 checksum, extracts it under /opt, and installs it.

Options:
  --root PATH        ITFlow application root (required)
  --state-root PATH  Override the protected manager state directory
  -h, --help         Show this help
EOF
}

fail() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

itflow_root=""
state_root=""

while [ "$#" -gt 0 ]; do
    case "$1" in
        --root)
            [ "$#" -ge 2 ] || fail "--root requires a path"
            itflow_root=$2
            shift 2
            ;;
        --state-root)
            [ "$#" -ge 2 ] || fail "--state-root requires a path"
            state_root=$2
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            fail "Unknown argument: $1"
            ;;
    esac
done

[ -n "$itflow_root" ] || {
    usage >&2
    exit 2
}

[ "$(id -u)" -eq 0 ] || fail "Run this installer as root (for example, with sudo)"
[ -d "$itflow_root" ] || fail "ITFlow root is not a directory: $itflow_root"

require_command curl
require_command mktemp
require_command php
require_command sha256sum
require_command unzip

temporary_directory=$(mktemp -d)
trap 'rm -rf -- "$temporary_directory"' EXIT HUP INT TERM

printf 'Resolving the latest GitHub release...\n'
latest_url=$(curl --fail --silent --show-error --location \
    --output /dev/null --write-out '%{url_effective}' "$RELEASES_URL/latest")

release_tag=${latest_url##*/}
case "$release_tag" in
    v[0-9]*) ;;
    *) fail "Could not determine a version tag from: $latest_url" ;;
esac

release_version=${release_tag#v}
asset_name="Nexus-Theme-Manager-for-ITFlow-$release_version"
archive_name="$asset_name.zip"
checksum_name="$archive_name.sha256.txt"
download_url="$RELEASES_URL/download/$release_tag"
install_directory="/opt/$asset_name"

[ ! -e "$install_directory" ] || fail "Destination already exists: $install_directory"

printf 'Downloading Nexus Theme Manager %s...\n' "$release_version"
curl --fail --silent --show-error --location \
    --output "$temporary_directory/$archive_name" "$download_url/$archive_name"
curl --fail --silent --show-error --location \
    --output "$temporary_directory/$checksum_name" "$download_url/$checksum_name"

printf 'Verifying the release checksum...\n'
(
    cd "$temporary_directory"
    sha256sum --check "$checksum_name"
)

printf 'Validating the archive layout...\n'
unzip -Z1 "$temporary_directory/$archive_name" |
while IFS= read -r archive_entry; do
    case "$archive_entry" in
        "$asset_name"/*) ;;
        *) fail "Archive contains an unexpected path: $archive_entry" ;;
    esac
done

mkdir -p /opt
unzip -q "$temporary_directory/$archive_name" -d "$temporary_directory/extracted"
mv "$temporary_directory/extracted/$asset_name" "$install_directory"

run_manager() {
    manager_command=$1
    shift
    if [ -n "$state_root" ]; then
        php "$install_directory/manager.php" "$manager_command" \
            --root "$itflow_root" --state-root "$state_root" "$@"
    else
        php "$install_directory/manager.php" "$manager_command" \
            --root "$itflow_root" "$@"
    fi
}

printf 'Running compatibility preflight...\n'
run_manager doctor

printf 'Installing Nexus Theme Manager %s...\n' "$release_version"
run_manager install --yes

printf 'Verifying the installation...\n'
run_manager verify
run_manager status

printf '\nInstalled Nexus Theme Manager %s from %s.\n' "$release_version" "$install_directory"
printf 'Reload the web/PHP service gracefully, then smoke-test the ITFlow interfaces.\n'

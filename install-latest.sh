#!/bin/sh
set -eu

REPOSITORY="ithealthtech/nexus-theme-manager-for-itflow"
RELEASES_URL="https://github.com/$REPOSITORY/releases"

usage() {
    cat <<'EOF'
Usage: sudo ./install-latest.sh --root PATH [--state-root PATH] [--no-gui-updater] [--repair-gui-updater]

Downloads the latest published Nexus Theme Manager release from GitHub,
verifies its SHA-256 checksum, and installs or upgrades it under /opt.

Options:
  --root PATH        ITFlow application root (required)
  --state-root PATH  Override the protected manager state directory
  --no-gui-updater   Skip installing the protected systemd GUI updater
  --repair-gui-updater
                     Refresh a broken existing GUI updater without changing the active theme
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

read_json_string() {
    json_file=$1
    json_key=$2
    php -r '
        try {
            $data = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
            $value = $data[$argv[2]] ?? null;
            if (!is_string($value) || $value === "") {
                exit(3);
            }
            fwrite(STDOUT, $value);
        } catch (Throwable $error) {
            exit(4);
        }
    ' "$json_file" "$json_key"
}

itflow_root=""
state_root=""
gui_updater="yes"
repair_gui_updater="no"

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
        --no-gui-updater)
            gui_updater="no"
            shift
            ;;
        --repair-gui-updater)
            repair_gui_updater="yes"
            shift
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
require_command cut
require_command mktemp
require_command php
require_command sha256sum
require_command unzip

resolved_itflow_root=$(CDPATH= cd -- "$itflow_root" && pwd -P)
instance_id=$(printf '%s' "$resolved_itflow_root" | sha256sum | cut -c1-16)
effective_state_root=${state_root:-/var/lib/nexus-itflow-theme}
existing_state="$effective_state_root/$instance_id/state.json"

if [ "$repair_gui_updater" = "yes" ] && [ "$gui_updater" = "no" ]; then
    fail "--repair-gui-updater cannot be combined with --no-gui-updater"
fi

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
php -r 'exit(preg_match("/^\\d+\\.\\d+\\.\\d+$/", $argv[1]) === 1 ? 0 : 1);' "$release_version" ||
    fail "Latest release tag is not a strict semantic version: $release_tag"
asset_name="Nexus-Theme-Manager-for-ITFlow-$release_version"
archive_name="$asset_name.zip"
checksum_name="$archive_name.sha256.txt"
download_url="$RELEASES_URL/download/$release_tag"
install_directory="/opt/$asset_name"

printf 'Downloading Nexus Theme Manager %s...\n' "$release_version"
curl --fail --silent --show-error --location \
    --output "$temporary_directory/$archive_name" "$download_url/$archive_name"
if ! curl --fail --silent --show-error --location \
    --output "$temporary_directory/$checksum_name" "$download_url/$checksum_name"; then
    checksum_name="$archive_name.sha256"
    curl --fail --silent --show-error --location \
        --output "$temporary_directory/$checksum_name" "$download_url/$checksum_name"
fi

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

if [ "$repair_gui_updater" = "yes" ]; then
    printf 'Repairing the protected GUI updater service...\n'
    php "$temporary_directory/extracted/$asset_name/updater.php" repair-service --root "$itflow_root"
    printf '\nGUI updater repaired. Return to Theme Studio, check for updates, and install %s.\n' "$release_version"
    exit 0
fi

upgrade_mode="no"
current_package=""
current_version=""
current_mode=""
if [ -f "$existing_state" ]; then
    current_version=$(read_json_string "$existing_state" package_version) ||
        fail "Existing Nexus state does not contain a valid package version: $existing_state"
    current_mode=$(read_json_string "$existing_state" mode) ||
        fail "Existing Nexus state does not contain a valid active/disabled mode: $existing_state"
    case "$current_mode" in
        enabled|disabled) ;;
        *) fail "Existing Nexus state contains an unsupported mode: $current_mode" ;;
    esac

    version_relation=$(php -r 'fwrite(STDOUT, (string) version_compare($argv[1], $argv[2]));' "$release_version" "$current_version")
    case "$version_relation" in
        1) upgrade_mode="yes" ;;
        0)
            printf 'Nexus Theme Manager %s is already installed. No update is required.\n' "$current_version"
            exit 0
            ;;
        -1) fail "Installed Nexus $current_version is newer than latest published release $release_version; downgrade refused" ;;
        *) fail "Could not compare installed and published Nexus versions" ;;
    esac

    updater_config="/etc/nexus-theme-manager/$instance_id.json"
    if [ -f "$updater_config" ]; then
        current_package=$(read_json_string "$updater_config" package_directory) ||
            fail "Existing GUI updater configuration does not contain a valid package directory: $updater_config"
    else
        current_package="/opt/Nexus-Theme-Manager-for-ITFlow-$current_version"
    fi
    [ -f "$current_package/manager.php" ] ||
        fail "The installed Nexus $current_version package was not found at $current_package"
    recorded_package_version=$(read_json_string "$current_package/manifest.json" package_version) ||
        fail "The current Nexus package manifest is invalid: $current_package/manifest.json"
    [ "$recorded_package_version" = "$current_version" ] ||
        fail "Existing state reports Nexus $current_version but $current_package contains $recorded_package_version"
fi

[ ! -e "$install_directory" ] || fail "Destination already exists: $install_directory"

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

if [ "$upgrade_mode" = "yes" ]; then
    printf 'Upgrading Nexus Theme Manager %s to %s...\n' "$current_version" "$release_version"
    if [ -n "$state_root" ]; then
        if ! php "$install_directory/upgrade.php" --root "$itflow_root" --current-package "$current_package" --state-root "$state_root" --yes; then
            case "$install_directory" in
                /opt/Nexus-Theme-Manager-for-ITFlow-[0-9]*.[0-9]*.[0-9]*) rm -rf -- "$install_directory" ;;
                *) fail "Upgrade failed and the verified replacement package requires manual review: $install_directory" ;;
            esac
            fail "Upgrade failed; review the verified rollback result above"
        fi
    elif ! php "$install_directory/upgrade.php" --root "$itflow_root" --current-package "$current_package" --yes; then
        case "$install_directory" in
            /opt/Nexus-Theme-Manager-for-ITFlow-[0-9]*.[0-9]*.[0-9]*) rm -rf -- "$install_directory" ;;
            *) fail "Upgrade failed and the verified replacement package requires manual review: $install_directory" ;;
        esac
        fail "Upgrade failed; review the verified rollback result above"
    fi
else
    printf 'Running compatibility preflight...\n'
    run_manager doctor

    printf 'Installing Nexus Theme Manager %s...\n' "$release_version"
    run_manager install --yes

    printf 'Verifying the installation...\n'
    run_manager verify
    run_manager status
fi

if [ "$gui_updater" = "yes" ]; then
    printf 'Installing the protected GUI updater service...\n'
    if [ -n "$state_root" ]; then
        if ! php "$install_directory/updater.php" install-service --root "$itflow_root" --state-root "$state_root"; then
            printf 'Warning: Nexus installed, but GUI updater setup failed. Run updater.php install-service after resolving the reported systemd prerequisite.\n' >&2
        fi
    elif ! php "$install_directory/updater.php" install-service --root "$itflow_root"; then
        printf 'Warning: Nexus installed, but GUI updater setup failed. Run updater.php install-service after resolving the reported systemd prerequisite.\n' >&2
    fi
fi

if [ "$upgrade_mode" = "yes" ]; then
    printf '\nUpdated Nexus Theme Manager from %s to %s using %s.\n' "$current_version" "$release_version" "$install_directory"
else
    printf '\nInstalled Nexus Theme Manager %s from %s.\n' "$release_version" "$install_directory"
fi
printf 'Reload the web/PHP service gracefully, then smoke-test the ITFlow interfaces.\n'

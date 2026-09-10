#!/bin/sh
set -eu

[ "$(id -u)" -eq 0 ] || {
    printf 'This integration test must run as root.\n' >&2
    exit 1
}

repository=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
test_root=$(mktemp -d)

# The release being installed is whatever this checkout is. Read it from the
# manifest rather than writing it here: a literal goes stale on the next version
# bump, and because the archive below is built from HEAD, a stale literal makes
# the mocked release tag disagree with the manifest inside the archive it serves.
new_version=$(php -r '
    $manifest = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    fwrite(STDOUT, (string) $manifest["package_version"]);
' "$repository/manifest.json")

# The installation being upgraded is pinned to a real earlier release, so it
# always sorts below the current one and the installer's downgrade refusal never
# trips.
old_version=3.9.1

old_package="/opt/Nexus-Theme-Manager-for-ITFlow-$old_version"
new_package="/opt/Nexus-Theme-Manager-for-ITFlow-$new_version"

[ "$old_version" != "$new_version" ] || {
    printf 'Package version %s must differ from the pinned upgrade source %s.\n' "$new_version" "$old_version" >&2
    exit 1
}

cleanup() {
    case "$old_package" in /opt/Nexus-Theme-Manager-for-ITFlow-?*) rm -rf -- "$old_package" ;; esac
    case "$new_package" in /opt/Nexus-Theme-Manager-for-ITFlow-?*) rm -rf -- "$new_package" ;; esac
    rm -rf -- "$test_root"
}
trap cleanup EXIT HUP INT TERM

[ ! -e "$old_package" ] || {
    printf 'Refusing to replace existing test package: %s\n' "$old_package" >&2
    exit 1
}
[ ! -e "$new_package" ] || {
    printf 'Refusing to replace existing test package: %s\n' "$new_package" >&2
    exit 1
}

cp -a "$repository" "$old_package"
php -r '
    $path = $argv[1];
    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $manifest["package_version"] = $argv[2];
    file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
' "$old_package/manifest.json" "$old_version"

fixture="$test_root/itflow"
state_root="$test_root/state"
mkdir -p "$fixture/css" "$fixture/uploads"
cp -a "$repository/baseline/." "$fixture/"
printf '%s\n' '<?php // Installer integration fixture.' > "$fixture/config.php"
php "$old_package/manager.php" install --root "$fixture" --state-root "$state_root" --yes >/dev/null

archive="$test_root/Nexus-Theme-Manager-for-ITFlow-$new_version.zip"
checksum="$archive.sha256.txt"
git -C "$repository" archive --format=zip --prefix="Nexus-Theme-Manager-for-ITFlow-$new_version/" --output="$archive" HEAD
(
    cd "$test_root"
    sha256sum "$(basename "$archive")" > "$(basename "$checksum")"
)

mock_bin="$test_root/bin"
mkdir -p "$mock_bin"
cat > "$mock_bin/curl" <<'MOCK'
#!/bin/sh
set -eu
output=""
write_out="no"
url=""
while [ "$#" -gt 0 ]; do
    case "$1" in
        --output)
            output=$2
            shift 2
            ;;
        --write-out)
            write_out="yes"
            shift 2
            ;;
        --fail|--silent|--show-error|--location)
            shift
            ;;
        *)
            url=$1
            shift
            ;;
    esac
done
if [ "$write_out" = "yes" ]; then
    printf '%s' "https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v$NEXUS_TEST_VERSION"
elif [ "${url##*/}" = "Nexus-Theme-Manager-for-ITFlow-$NEXUS_TEST_VERSION.zip" ]; then
    cp "$NEXUS_TEST_ARCHIVE" "$output"
elif [ "${url##*/}" = "Nexus-Theme-Manager-for-ITFlow-$NEXUS_TEST_VERSION.zip.sha256.txt" ]; then
    cp "$NEXUS_TEST_CHECKSUM" "$output"
else
    printf 'Unexpected mock curl URL: %s\n' "$url" >&2
    exit 22
fi
MOCK
chmod +x "$mock_bin/curl"

export NEXUS_TEST_VERSION="$new_version"
export NEXUS_TEST_ARCHIVE="$archive"
export NEXUS_TEST_CHECKSUM="$checksum"
PATH="$mock_bin:$PATH"
export PATH

output=$(sh "$repository/install-latest.sh" --root "$fixture" --state-root "$state_root" --no-gui-updater)
printf '%s\n' "$output" | grep -F "Updated Nexus Theme Manager from $old_version to $new_version" >/dev/null || {
    printf 'install-latest.sh did not report the %s to %s upgrade. Its output was:\n%s\n' "$old_version" "$new_version" "$output" >&2
    exit 1
}

resolved_fixture=$(CDPATH= cd -- "$fixture" && pwd -P)
instance_id=$(printf '%s' "$resolved_fixture" | sha256sum | cut -c1-16)
installed_version=$(php -r '
    $state = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    fwrite(STDOUT, (string) ($state["package_version"] ?? ""));
' "$state_root/$instance_id/state.json")
[ "$installed_version" = "$new_version" ] || {
    printf 'Expected installed version %s, got %s\n' "$new_version" "$installed_version" >&2
    exit 1
}
php "$new_package/manager.php" verify --root "$fixture" --state-root "$state_root" >/dev/null

printf 'PASS: install-latest.sh upgraded %s to %s and verified the managed installation.\n' "$old_version" "$new_version"

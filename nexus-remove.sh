#!/bin/sh
# nexus-remove.sh - remove Nexus Theme Manager completely, whatever state it is in.
#
# manager.php uninstall only works when its state files, the matching /opt package
# and the installed templates all agree. An ITFlow update, an interrupted Nexus
# update or a deleted /opt folder breaks that, and uninstall then refuses. This
# script needs none of it: ITFlow is a git checkout, so stock templates come
# straight back from ITFlow's own history.
#
#   sudo sh nexus-remove.sh --root /var/www/itflow            # dry run: shows what it would do
#   sudo sh nexus-remove.sh --root /var/www/itflow --yes      # actually remove
#
# Theme settings are copied to /root/nexus-removed-<timestamp>/ before anything is
# deleted, so a later reinstall can bring the design back.

set -eu

root=""
yes="no"
while [ "$#" -gt 0 ]; do
    case "$1" in
        --root) root=${2:-}; shift 2 ;;
        --yes) yes="yes"; shift ;;
        -h|--help) sed -n '2,15p' "$0"; exit 0 ;;
        *) printf 'Unknown argument: %s\n' "$1" >&2; exit 2 ;;
    esac
done

[ -n "$root" ] || { printf 'Usage: sh nexus-remove.sh --root /path/to/itflow [--yes]\n' >&2; exit 2; }
[ "$(id -u)" -eq 0 ] || { printf 'Run as root (sudo).\n' >&2; exit 2; }
root=$(CDPATH= cd -- "$root" && pwd -P) || { printf 'No such directory: %s\n' "$root" >&2; exit 2; }
[ -f "$root/login.php" ] && [ -d "$root/includes" ] || { printf 'Not an ITFlow root: %s\n' "$root" >&2; exit 2; }

say()  { printf '\n==> %s\n' "$1"; }
run()  { if [ "$yes" = "yes" ]; then "$@"; else printf '    would run: %s\n' "$*"; fi; }

# The 16 ITFlow templates Nexus overwrites, and the 7 files it adds.
templates="admin/includes/side_nav.php agent/tickets.php agent/user/mfa_enforcement.php
client/includes/footer.php client/includes/header.php client/index.php client/login_reset.php
client/profile.php client/ticket.php client/ticket_add.php client/tickets.php
guest/guest_view_invoice.php guest/includes/guest_header.php includes/header.php
includes/top_nav.php login.php"
owned="admin/nexus.php admin/post/nexus.php css/nexus-theme-custom.php css/nexus-theme.css
guest/nexus_invoice_pdf.php includes/nexus_invoice_pdf.php includes/nexus_theme.php"

[ "$yes" = "yes" ] || printf 'DRY RUN - nothing will change. Re-run with --yes to remove.\n'
printf 'ITFlow root: %s\n' "$root"

owner=$(stat -c '%U' "$root/login.php")
git_as() { sudo -u "$owner" git -c safe.directory="$root" -C "$root" "$@"; }

say "1. Back up theme settings"
backup="/root/nexus-removed-$(date +%Y%m%d-%H%M%S)"
found=$(find "$root/uploads" -maxdepth 1 -name '.nexus-theme*' 2>/dev/null || true)
if [ -n "$found" ] || [ -d /var/lib/nexus-itflow-theme ]; then
    run mkdir -p "$backup"
    for f in $found; do run cp -a "$f" "$backup/"; done
    [ -d /var/lib/nexus-itflow-theme ] && run cp -a /var/lib/nexus-itflow-theme "$backup/state"
    printf '    backup: %s\n' "$backup"
else
    printf '    no settings found\n'
fi

say "2. Put the 16 ITFlow templates back to stock"
if [ -d "$root/.git" ]; then
    # shellcheck disable=SC2086
    changed=$(git_as status --porcelain --untracked-files=no -- $templates | wc -l)
    printf '    %s of 16 differ from ITFlow\n' "$changed"
    # shellcheck disable=SC2086
    [ "$changed" -eq 0 ] || run git_as checkout -- $templates
else
    printf '    ITFlow is not a git checkout - restoring from Nexus backups instead\n'
    orig=$(find /var/lib/nexus-itflow-theme -type d -name original 2>/dev/null | head -1)
    [ -n "$orig" ] || { printf 'ERROR: no git and no Nexus backups; cannot restore stock templates safely.\n' >&2; exit 1; }
    for t in $templates; do
        if [ -f "$orig/$t" ]; then run cp -p "$orig/$t" "$root/$t"
        elif grep -qi nexus "$root/$t" 2>/dev/null; then printf '    WARNING: no backup for %s, still contains Nexus\n' "$t"; fi
    done
fi

say "3. Remove files Nexus added"
for f in $owned; do [ -e "$root/$f" ] && run rm -f "$root/$f"; done
for f in $found; do run rm -f "$f"; done

say "4. Remove the updater service"
units=$(systemctl list-unit-files --no-legend 'nexus*' 2>/dev/null | awk '{print $1}')
if [ -n "$units" ]; then
    for u in $units; do run systemctl disable --now "$u"; done
    for u in $units; do [ -f "/etc/systemd/system/$u" ] && run rm -f "/etc/systemd/system/$u"; done
    run systemctl daemon-reload
else
    printf '    none installed\n'
fi

say "5. Remove Nexus folders outside ITFlow"
for d in /etc/nexus-theme-manager /usr/local/lib/nexus-theme-manager /var/lib/nexus-itflow-theme; do
    [ -e "$d" ] && run rm -rf "$d"
done
for d in /opt/Nexus-Theme-Manager-for-ITFlow-*; do [ -d "$d" ] && run rm -rf "$d"; done

say "6. Reload PHP so the stock pages are served"
for s in apache2 httpd $(systemctl list-units --no-legend --type=service 'php*-fpm*' 2>/dev/null | awk '{print $1}'); do
    systemctl is-active --quiet "$s" 2>/dev/null && run systemctl reload "$s"
done

say "7. Check"
left=$(grep -rli nexus "$root/includes" "$root/login.php" "$root/client/includes" "$root/guest/includes" 2>/dev/null | wc -l)
if [ "$yes" = "yes" ]; then
    printf '    ITFlow files still mentioning Nexus: %s\n' "$left"
    [ "$left" -eq 0 ] && printf '\nNexus removed. ITFlow is stock.\n' || printf '\nWARNING: some Nexus references remain - see above.\n'
else
    printf '\nDry run complete. Re-run with --yes to remove.\n'
fi

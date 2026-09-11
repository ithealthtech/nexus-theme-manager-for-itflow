#!/usr/bin/env bash
# The full update cycle against a live ITFlow.
#
# This is the test the fixture suite cannot do: it runs ITFlow's own updater
# rather than a model of it, and checks that Nexus classifies and repairs the
# damage. The claim under test is that `git reset --hard` puts every overlaid
# template back on exactly the upstream blob, so each one hashes to precisely
# the baseline this package recorded and is therefore decidably "reverted"
# rather than merely "different".

set -uo pipefail

PKG=${PKG:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}
WEB_ROOT=${WEB_ROOT:-/var/www/itflow}
STATE=${STATE:-/var/lib/nexus-itflow-theme}
M="sudo -u www-data php ${PKG}/manager.php"
COMMON="--root ${WEB_ROOT} --state-root ${STATE}"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
pass() { printf '\033[1;32mPASS\033[0m %s\n' "$1"; }
fail() { printf '\033[1;31mFAIL\033[0m %s\n' "$1"; FAILED=1; }
FAILED=0

say "0. State directory, owned by the web user"
# manager.php runs as www-data here, matching a real deployment where the web
# user owns the ITFlow files. It cannot create a directory under /var/lib itself.
sudo rm -rf "$STATE"
sudo install -d -o www-data -g www-data -m 0750 "$STATE"
ls -ld "$STATE"

say "1. Preflight against the live ITFlow"
$M doctor $COMMON || fail "doctor refused the live tree"

say "2. Install Nexus"
$M install $COMMON --yes || fail "install failed"

say "3. Verify and record the installed state"
$M verify $COMMON || fail "verify failed after install"
$M status $COMMON --json > /tmp/nexus-status-installed.json
python3 - <<'PY'
import json, sys
s = json.load(open('/tmp/nexus-status-installed.json'))
print("mode:", s.get("mode"), "| drift:", len(s.get("drift", [])),
      "| itflow:", s.get("itflow_version"), "| supported:", s.get("itflow_version_supported"))
sys.exit(0 if s.get("drift") == [] and s.get("mode") == "enabled" else 1)
PY
if [ $? -eq 0 ]; then pass "clean enabled install, no drift"; else fail "status did not report a clean enabled install"; fi

say "4. Confirm the theme is actually being served"
code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/login.php)
echo "HTTP $code /login.php"
sudo service apache2 reload >/dev/null; sleep 2
hits=$(curl -s http://localhost/login.php | grep -ci nexus)
echo "nexus references in served login.php: $hits"
if [ "$hits" -gt 0 ]; then pass "login.php is serving Nexus markup"; else fail "login.php has no Nexus markup"; fi

say "5. What ITFlow sees as locally modified before the update"
sudo -u www-data git -C "$WEB_ROOT" status --porcelain --untracked-files=no > /tmp/nexus-tracked-before.txt
tracked=$(wc -l < /tmp/nexus-tracked-before.txt)
echo "$tracked tracked files modified (expect 16)"
sed 's/^/  /' /tmp/nexus-tracked-before.txt
if [ "$tracked" -eq 16 ]; then pass "all 16 overlays are tracked modifications ITFlow will discard"; else fail "expected 16 tracked modifications, saw $tracked"; fi

say "6. Run ITFlow's REAL updater - this is the whole point"
sudo -u www-data php "${WEB_ROOT}/scripts/update_cli.php" 2>&1 | tail -40

say "7. Did the overlay revert, and is it recognised as such?"
$M status $COMMON --json > /tmp/nexus-status-after.json
python3 - <<'PY'
import json, sys
s = json.load(open('/tmp/nexus-status-after.json'))
counts = s.get('drift_counts', {})
print("drift:", len(s.get('drift', [])), counts)
print("reapply_recommended:", s.get('reapply_recommended'))
for d in s.get('drift', []):
    print(f"  {d['kind']:9} {d['path']}")
ok = True
if not s.get('drift'):
    print("UNEXPECTED: no drift detected at all")
    ok = False
if counts.get('modified', -1) != 0:
    print("UNEXPECTED: files classified as modified rather than reverted")
    ok = False
if not s.get('reapply_recommended'):
    print("UNEXPECTED: reapply was not recommended")
    ok = False
sys.exit(0 if ok else 1)
PY
if [ $? -eq 0 ]; then pass "every reverted file classified as reverted, not modified"; else fail "classification did not match the reverted case"; fi

say "8. Is the theme actually off the served page now?"
# opcache holds the compiled templates, so the served page lags the disk in both
# directions. A reload is what makes this assertion about the files rather than
# about the cache - and is exactly what the manager tells operators to do.
sudo service apache2 reload >/dev/null; sleep 2
hits_after=$(curl -s http://localhost/login.php | grep -ci nexus)
echo "nexus references in served login.php: $hits_after"
if [ "$hits_after" -eq 0 ]; then pass "the update really did take the theme off the live site"; else fail "theme markup unexpectedly still present"; fi

say "9. Do the theme-owned files survive, as a hard reset implies?"
survived=0; missing=0
for f in includes/nexus_theme.php css/nexus-theme.css admin/nexus.php admin/post/nexus.php \
         css/nexus-theme-custom.php includes/nexus_invoice_pdf.php guest/nexus_invoice_pdf.php; do
  if [ -f "$WEB_ROOT/$f" ]; then survived=$((survived+1)); else missing=$((missing+1)); echo "  gone: $f"; fi
done
echo "theme-owned files: $survived survived, $missing removed"
if [ "$missing" -eq 0 ]; then pass "hard reset left every untracked theme-owned file in place"; else fail "theme-owned files were removed, contradicting the hard-reset model"; fi

say "10. Theme Studio's own view of the damage"
WEB_ROOT="$WEB_ROOT" sudo -u www-data -E php -r '
$root = getenv("WEB_ROOT") ?: "/var/www/itflow";
$_SERVER["DOCUMENT_ROOT"] = $root;
require $root . "/includes/nexus_theme.php";
$d = nexusThemeOverlayDrift($root);
echo "drifted: ", var_export($d["drifted"], true), " of ", $d["surface_count"], " surfaces\n";
foreach ($d["reverted"] as $r) { echo "  ", $r["label"], " (", $r["path"], ")\n"; }
' 2>&1 | head -25

say "11. Repair"
$M reapply $COMMON --yes || fail "reapply failed"

say "12. Verify the repair"
$M verify $COMMON || fail "verify failed after reapply"
$M status $COMMON --json > /tmp/nexus-status-repaired.json
python3 - <<'PY'
import json, sys
s = json.load(open('/tmp/nexus-status-repaired.json'))
print("drift after reapply:", len(s.get('drift', [])), "| mode:", s.get('mode'))
sys.exit(0 if s.get('drift') == [] and s.get('mode') == 'enabled' else 1)
PY
if [ $? -eq 0 ]; then pass "no drift remains after reapply"; else fail "drift remained after reapply"; fi

sudo service apache2 reload >/dev/null; sleep 2
hits_repaired=$(curl -s http://localhost/login.php | grep -ci nexus)
echo "nexus references in served login.php: $hits_repaired"
if [ "$hits_repaired" -gt 0 ]; then pass "login.php is serving Nexus markup again"; else fail "login.php lost its Nexus markup after reapply"; fi

say "13. Clean disable restores pristine upstream"
$M disable $COMMON --yes || fail "disable failed"
leftover=$(sudo -u www-data git -C "$WEB_ROOT" status --porcelain --untracked-files=no | wc -l)
if [ "$leftover" -eq 0 ]; then
  pass "all tracked files byte-identical to upstream after disable"
else
  fail "$leftover tracked files still differ from upstream after disable"
  sudo -u www-data git -C "$WEB_ROOT" status --porcelain --untracked-files=no | sed 's/^/  /'
fi

say "Result"
if [ $FAILED -eq 0 ]; then echo "ALL CHECKS PASSED"; else echo "SOME CHECKS FAILED"; fi
exit $FAILED

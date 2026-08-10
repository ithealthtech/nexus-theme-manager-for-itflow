# Nexus Theme Manager 3.0.2

This patch release fixes stale custom logos after replacing an uploaded image with another file of the same type.

## Fixed

- Every successful custom-asset upload now generates a new protected revision token.
- Light and dark logo URLs include that revision across login, password recovery, MFA enrollment, client navigation, technician navigation, and Theme Studio.
- The generated navigation stylesheet receives a new settings cache key and embeds the revised logo and login-background URLs.
- Favicons and login backgrounds use the same reliable cache-busting behavior.
- Asset removal and one-click design rollback also rotate the revision.
- Native ITFlow images outside `/uploads/nexus-theme` are never rewritten.

## Validation

- PHP source parser validation
- Package manifest and SHA-256 verification
- Full lifecycle install, verify, disable, enable, adopt, conflict, and uninstall suite
- GUI updater policy and rollback suite
- Linux activation and systemd unit validation
- Cache-revision and rendered-surface lifecycle assertions

Manager 3.0.2 ships theme payload 26.08.12 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

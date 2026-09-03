## What does this change?

<!-- Explain why, not just what. -->

## Supported ITFlow revision

<!-- Which release and commit does this target? Unchanged, or updated in manifest.json? -->

## Type of change

- [ ] Theme or presentation change
- [ ] Lifecycle behavior (install, update, pause, restore, uninstall)
- [ ] New or updated ITFlow revision support
- [ ] Documentation
- [ ] CI or tooling

## Safety review

Which of the seven rules in [CONTRIBUTING.md](../CONTRIBUTING.md) does this touch?

<!-- e.g. "Rule 3 - baseline and payload move together", or "none" -->

- [ ] This change does not weaken a compatibility check, hash verification, or drift detection.
- [ ] Pause, restore, and uninstall still leave a working ITFlow install.
- [ ] If a managed template changed, both `baseline/` and `payload/` were updated and `manifest.json` hashes refreshed.

## Rollback implications

<!-- What happens to an install that applies this and then rolls back? -->

## Verification

```bash
php -l manager.php
find payload baseline tests -type f -name '*.php' -exec php -l {} \;
php tests/lifecycle.php
sha256sum --check SHA256SUMS.txt
```

- [ ] PHP lint passes
- [ ] Lifecycle tests pass
- [ ] `SHA256SUMS.txt` regenerated and verifying
- [ ] Release notes added under `docs/` and linked from `docs/README.md` (if releasing)

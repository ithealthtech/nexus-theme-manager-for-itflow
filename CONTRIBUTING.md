# Contributing

Thank you for helping improve the Nexus Theme Manager for IT Flow.

## Before opening a change

- Discuss user-facing behavior or support for a new ITFlow revision in an issue first.
- Never include production configuration, credentials, databases, backups, logs, customer information, or private screenshots.
- Keep each compatibility package pinned to one verified ITFlow release and commit.

## Development checks

Run these from the repository root with PHP 8.2 or newer:

```bash
php -l manager.php
find payload baseline tests -type f -name '*.php' -exec php -l {} \;
php tests/lifecycle.php
sha256sum --check SHA256SUMS.txt
```

When a managed template changes, update both the exact upstream file under `baseline/` and the themed file under `payload/`, then update the hashes in `manifest.json`. Regenerate `SHA256SUMS.txt` after every source or documentation change.

Pull requests should explain the supported ITFlow revision, the visual or lifecycle behavior changed, rollback implications, and the test results.

# Contributing

Thank you for helping improve the Nexus Theme Manager for ITFlow.

Nexus patches files inside a running ITFlow installation. A bad change does not produce a cosmetic bug — it can leave someone's service desk unbootable. Contributions are reviewed with that in mind.

## Before opening a change

- Discuss user-facing behavior or support for a new ITFlow revision in an issue first.
- Report suspected vulnerabilities privately through [SECURITY.md](SECURITY.md), never in a public issue.
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

CI additionally runs `tests/updater.php`, `tests/upgrade.php`, `tests/install_latest.sh`, `tests/linux_activation.php`, `tests/pages_site.mjs`, and shell syntax checks on the entrypoints, across PHP 8.2, 8.3, and 8.4.

## The rules that keep Nexus safe

1. **Never widen compatibility without testing it.** A release declares one ITFlow revision and commit in `manifest.json`. Claiming support for a revision whose templates have not been verified is the most damaging change possible here.
2. **Never weaken a refusal.** Compatibility checks, template-hash verification, and drift detection exist to stop Nexus writing to files it does not recognize. Do not add a force flag, a debug bypass, or a "just warn" path.
3. **Baseline and payload move together.** When a managed template changes, update both the exact upstream file under `baseline/` and the themed file under `payload/`, then update the hashes in `manifest.json`.
4. **Regenerate `SHA256SUMS.txt` after every source or documentation change.** CI verifies it; a stale manifest fails the build.
5. **Presentation is not authorization.** Navigation visibility and theming never replace ITFlow authentication, permission, or role enforcement. Do not build a feature that implies otherwise.
6. **Every change must be reversible.** Pause, restore, and uninstall have to leave a working ITFlow install. If a change cannot be rolled back cleanly, it is not finished.
7. **Stay an independent integration.** Nexus manages a bounded set of verified templates. It does not fork ITFlow or claim to be an official project.

## Adding support for a new ITFlow revision

1. Identify the exact upstream release and commit.
2. Copy the unmodified upstream templates into `baseline/`.
3. Apply the Nexus presentation layer in `payload/`.
4. Update `manifest.json` with the revision, commit, and file hashes.
5. Run the lifecycle and upgrade tests.
6. Regenerate `SHA256SUMS.txt`.
7. Add release notes under `docs/` and link them from [docs/README.md](docs/README.md).

## Pull requests

Explain:

- the supported ITFlow revision,
- the visual or lifecycle behavior changed,
- rollback implications,
- test results,
- and which of the seven rules above the change touches, if any.

## Documentation

`docs/` holds guides, validation reports, and per-release notes — indexed in [docs/README.md](docs/README.md). `pages/` is the published portal at <https://ithealthtech.github.io/nexus-theme-manager-for-itflow/>, validated by `tests/pages_site.mjs`.

Update both when behavior changes, and remember rule 4: regenerate `SHA256SUMS.txt` afterwards.

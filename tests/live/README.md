# Live-install tests

The suites under `tests/` prove the manager against fixtures built from
`baseline/`. That is fast, hermetic and runs in CI, but it can only ever check
Nexus against a *model* of what ITFlow does.

These two scripts check it against ITFlow itself. They are not part of CI: they
need a throwaway Linux host, network access and root.

## What they exist to prove

Drift classification rests on one claim: that an ITFlow update leaves every
overlaid template on *exactly* the upstream blob, so each hashes to precisely the
baseline recorded in `manifest.json` and is therefore decidably `reverted` rather
than merely different.

ITFlow's `scripts/update_cli.php` runs `git fetch --all` then
`git reset --hard origin/<branch>`, which is why that holds — and why a zip
deployment, having no `.git`, is never touched at all. `update-cycle.sh` runs
that real script rather than imitating it.

## Running them

On a disposable Ubuntu host (WSL2 is fine), as root:

```bash
bash provision-itflow.sh     # Apache, MariaDB, PHP, a git checkout of ITFlow, headless setup
bash update-cycle.sh         # install Nexus, break it with ITFlow's updater, repair it
```

`provision-itflow.sh` is destructive by design: it drops and recreates the
database and deletes `/var/www/itflow`. Point it at nothing you care about.

`update-cycle.sh` expects the package at `/mnt/c/dev/nexus-theme-manager`; override
with `PKG=/path/to/package`. `WEB_ROOT` and `STATE` are overridable the same way.
It exits non-zero if any check fails.

## Two things that will bite you

**Run the manager as the user that owns the ITFlow files.** These scripts use
`www-data`, which cannot create the state directory under `/var/lib` on its own —
step 0 creates it with the right ownership first. A real deployment either does
the same or runs the manager as root.

**Reload PHP before believing what a browser shows you.** The opcode cache holds
the compiled templates, so the served pages lag the disk in *both* directions: a
reverted overlay keeps rendering as themed, and a freshly repaired one keeps
rendering as native. `manager.php status` reads the filesystem and is right
immediately; the browser is not. Both scripts reload Apache before asserting
anything about served output.

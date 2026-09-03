# Security policy

Nexus modifies files inside a running ITFlow installation. That makes its safety
properties — refusing unsupported revisions, detecting drift, and restoring cleanly —
security features, not conveniences.

## Supported versions

Security fixes are provided for the latest tagged release. Compatibility remains limited to the ITFlow release and commit declared in `manifest.json`.

## Reporting a vulnerability

Do **not** publish suspected vulnerabilities, credentials, or production details in a GitHub issue. Use the repository's [private vulnerability reporting](https://github.com/ithealthtech/nexus-theme-manager-for-itflow/security/advisories/new) under the Security tab.

Include the affected Nexus version, the ITFlow revision, impact, reproduction steps, and any proposed mitigation.

For ordinary defects without sensitive details, open a GitHub issue.

We aim to acknowledge within 5 business days.

## In scope

- Installing or activating against an ITFlow revision the manifest does not declare as supported.
- Bypassing the compatibility, checksum, or template-hash verification that runs before files are changed.
- Privilege escalation through Theme Studio, the GUI updater, or the command-line upgrade path — including any route by which a non-administrator reaches theme management.
- Stored or reflected XSS through theme settings, custom CSS, logo handling, or navigation labels.
- Path traversal or arbitrary file write through the installer, updater, or asset handling.
- Restoring or rolling back in a way that leaves an ITFlow install unbootable or partially patched.
- Presentation-layer navigation visibility being mistaken for authorization — see below.

## Not vulnerabilities

- **Nexus refusing to install.** A failed compatibility check that leaves files untouched is the design working. Forcing past it is the unsafe act.
- **Presentation visibility is not authorization.** Hiding a navigation item changes presentation only; it never replaces ITFlow's own authentication, permission, or role enforcement. A hidden item still being reachable by URL is expected — ITFlow authorizes the request, not Nexus.
- **Local template edits being detected as drift.** That detection exists to prevent silent conflict.
- **Nexus not supporting a newer ITFlow revision yet.** Compatibility is deliberately bounded to tested templates.

## Operator responsibilities

- **Disable or uninstall Nexus before updating ITFlow.** This is the single most important operational rule.
- Take and verify application-file and database backups before installing, updating, or restoring.
- Never force an install past a failed compatibility check.
- Keep `SHA256SUMS.txt` verification in your process — it is how you detect a tampered or truncated package.
- Restrict Theme Manager access to ITFlow administrators.

## Verifying a package

Every release ships a checksum manifest. Verify before installing:

```bash
sha256sum --check SHA256SUMS.txt
```

CI verifies the same manifest on every push and pull request, alongside PHP linting on 8.2, 8.3, and 8.4, lifecycle tests, updater and upgrade security tests, an installer integration run, and Pages portal validation.

## Independence

Nexus is an independent GPL-3.0 integration. It is not an official ITFlow project. Vulnerabilities in ITFlow itself should be reported to the [ITFlow project](https://github.com/itflow-org/itflow), not here.

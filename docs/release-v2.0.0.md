# Nexus Theme Manager for IT Flow 2.0.0

Brand-neutral major release of the standalone, lifecycle-managed Nexus theme for ITFlow.

Highlights:

- Exact support for ITFlow 26.08 at commit `ccaa45b0ae9900ad731a6491559f65ff8d87a8f3`.
- Safe preflight, install, adoption, verification, disable/enable, and uninstall workflows.
- Protected backups, atomic file replacement, conflict detection, package integrity checks, and rollback.
- Themed authentication, customer portal, ticket, technician navigation, MFA, and password-recovery surfaces.
- No organization-specific names, domains, navigation links, footer links, or internal identifiers.
- 55 lifecycle assertions and PHP syntax validation across the manager and every managed PHP template.

Download `Nexus-Theme-Manager-for-ITFlow-2.0.0.zip` and verify it with the adjacent `.sha256.txt` file before extracting it outside the ITFlow web root.

This package only supports the exact ITFlow revision declared above. Run `doctor` before installation; it refuses incompatible or locally modified baselines without writing to the ITFlow instance.

Version 1.0.0 users must verify and uninstall that payload before installing 2.0.0. See the migration section in `README.md`.

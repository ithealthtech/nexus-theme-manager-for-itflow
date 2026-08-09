# Nexus Theme Manager for IT Flow 2.2.0

Compatibility release for current ITFlow 26.08 installations at commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

Highlights:

- Rebases all 12 managed baseline templates onto the exact current ITFlow commit.
- Preserves upstream SQL query optimizations in the five templates changed after the previous Nexus baseline.
- Rebases the Nexus theme modifications without merge conflicts.
- Retains safe preflight, install, adoption, verification, disable/enable, and uninstall workflows.
- Retains protected backups, atomic replacement, conflict detection, package integrity checks, and rollback.
- Includes a sanitized technician-interface screenshot captured from an isolated local ITFlow 26.08/AdminLTE test environment using the release stylesheet.
- Runs 55 lifecycle assertions plus PHP syntax validation across the manager and every managed PHP template.

Download `Nexus-Theme-Manager-for-ITFlow-2.2.0.zip` and verify it with the adjacent `.sha256.txt` file before extraction. The repository bootstrap installer automatically resolves this release as the latest version.

This package supports only ITFlow commit `89b080b430aaafba5d520c4e52c57b28a9559085`. Run `doctor` before installation; it refuses incompatible or locally modified templates without changing the ITFlow instance.

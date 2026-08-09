# Nexus Theme Manager for IT Flow 2.3.0

Administration experience release for ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

Highlights:

- Adds a dedicated **Administration → NEXUS → Theme Manager** menu entry and control panel.
- Shows active/paused state, manager and payload versions, exact ITFlow compatibility, core-asset health, and protected lifecycle commands.
- Adds an administrator-only, CSRF-protected presentation control for pausing or activating Nexus across login, technician, administration, and client surfaces.
- Uses ITFlow's existing administrator permission enforcement, POST-handler routing, audit log, application log, and alert feedback.
- Keeps package installation, PHP replacement, backups, rollback, disable, and uninstall behind the root-only CLI manager.
- Writes only a fixed, non-executable presentation-state marker through the web control; it cannot alter package code or protected recovery state.
- Makes the latest-release bootstrap detect an existing managed Nexus installation before downloading and direct the operator through the verified uninstall/reinstall upgrade path.
- Expands the exact managed payload to 17 files and the verified upstream baseline to 13 templates.
- Includes a sanitized screenshot captured from an isolated ITFlow 26.08/AdminLTE preview using the release stylesheet.
- Passes PHP syntax validation across 31 PHP files and 78 lifecycle assertions, including administrator/CSRF/no-shell invariants, web pause/reactivate, adoption-state preservation, and CLI state cleanup.

Download `Nexus-Theme-Manager-for-ITFlow-2.3.0.zip` and verify it with the adjacent `.sha256.txt` file before extraction. The repository bootstrap installer automatically resolves this release as the latest version.

This package supports only ITFlow commit `89b080b430aaafba5d520c4e52c57b28a9559085`. Run `doctor` before installation; it refuses incompatible or locally modified templates without changing the ITFlow instance.

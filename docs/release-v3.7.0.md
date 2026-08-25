# Nexus Theme Manager 3.7.0

Version 3.7.0 turns Theme Studio's accessibility and responsive tools into a unified design-quality workflow. Administrators can audit a private draft, inspect exact ITFlow surface previews at realistic widths, jump directly to risky settings, and apply conservative corrections without affecting users.

## Highlights

- Server-side accessibility checks cover body and header contrast, visible focus treatments, logo alternative text, interface scale, reduced motion, and touch-target risks.
- Responsive checks cover sidebar collisions, mobile logo overflow, long-brand truncation, and wide-table behavior from 320px through 1920px.
- Eight runtime previews cover login, password reset, dashboard, ticket queue, client portal, mobile navigation, guest invoice, and printable/PDF invoice surfaces.
- Every finding identifies its affected surface and links to the responsible Theme Studio control.
- Safe automatic corrections are validated and saved to the private draft; publication remains an explicit administrator action.
- Revision history supports protected known-good designs and preserves them during normal history trimming.
- Automatic recovery snapshots are created before publication, preset/configuration imports, scheduled activation, and Nexus updates.
- A sanitized diagnostic bundle records versions, design hashes, desktop/mobile quality results, asset health, revision counts, and updater status without exposing paths or secrets.

## Compatibility

- Nexus manager: 3.7.0
- Nexus payload: 26.08.21
- ITFlow release: 26.08
- Exact ITFlow commit: `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP: 8.1 or newer

## Validation

- 246 lifecycle assertions
- 32 GUI updater security assertions
- PHP syntax checks for every managed and baseline PHP file
- Package and manifest checksum verification
- Responsive browser verification at phone and desktop widths
- Linux systemd unit verification and root activation coverage in CI

The versioned release ZIP and its `.sha256.txt` companion are the supported installation artifacts. Run `doctor` before installation, keep a verified ITFlow application/database backup, and validate the package in staging before production promotion.

# Nexus Theme Manager 2.5.2

Nexus 2.5.2 polishes the administration navigation and makes custom-logo presentation deterministic across authentication pages.

## Fixed

- The administration return control is now a compact, rounded navigation element rather than an oversized rectangular header.
- The main login, password-reset, and MFA-enforcement templates now mark logo and text branding as mutually exclusive states.
- Logo mode suppresses any non-image title content inside the authentication brand header, preventing a duplicate company name beside an uploaded logo.

## Changed

- The Theme Manager navigation badge displays the installed Nexus manager version dynamically.
- The theme payload is versioned as 26.08.7.

## Validation

- PHP syntax validation covers every payload, baseline, manager, updater, and test file.
- Lifecycle regression tests verify the compact navigation treatment and all three authentication branding states.
- Browser validation confirms the logo remains visible, duplicate title content is not rendered, and the administration control remains compact and rounded.
- Existing lifecycle, updater security, shell syntax, and package checksum checks remain active.

## Compatibility

- Manager 2.5.2 / theme payload 26.08.7
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer
- GUI update service: Linux with systemd, curl, and unzip

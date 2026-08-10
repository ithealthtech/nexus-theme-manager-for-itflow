# Nexus Theme Manager 3.1.0

This release completes the visual transition from Nexus authentication and client pages into public guest invoices.

## Added

- Public guest pages now load the active Nexus identity and generated theme safely through the shared ITFlow guest header.
- Guest invoices receive a responsive dark billing masthead, active logo, configured tagline, browser title, favicon, palette, document hierarchy, buttons, tables, and print-safe styling.
- Invoice company details and metadata stack into a clean single-column layout on phones while wide tables retain their existing responsive container.

## Fixed

- **Welcome back** and other authentication headings remain readable on the dark login card even when the configured document text color is dark.
- The configured brand tagline now appears beneath login, password recovery, and MFA enrollment cards.
- Supporting authentication copy, field labels, and primary-button text have explicit accessible contrast.

## Preserved

- Guest URL-key validation
- Invoice and payment queries
- Pay, print, and PDF-download actions
- Invoice status/history behavior
- Existing footer scripts and print output

## Validation

- PHP source parser validation
- Browser checks at 1440px and 390px with zero horizontal page overflow
- Package manifest and SHA-256 verification
- Full lifecycle install, verify, disable, enable, adopt, conflict, and uninstall suite
- GUI updater policy, rollback, Linux activation, systemd, and shell validation

Manager 3.1.0 ships theme payload 26.08.13 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

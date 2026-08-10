# Nexus Theme Manager 3.2.0

This release carries the polished Nexus invoice identity into both browser printing and PDF downloads without crossing the manager lifecycle boundary.

## Added

- Print output retains a compact paper-safe Nexus masthead, invoice heading, brand identity, and tagline.
- The Download action uses a dedicated URL-key-validated TCPDF renderer with the active palette and branding.
- Downloaded PDFs include the company and client hierarchy, invoice status, issue and due dates, balance, items, notes, totals, and configured footer.
- Custom PNG, JPEG, or GIF light logos are embedded when TCPDF can consume them; otherwise the brand name or native company logo provides a reliable fallback.

## Deactivation behavior

- Theme Studio pause redirects invoice downloads to ITFlow's original PDF renderer and suppresses Nexus print styles.
- CLI disable restores the original `guest_view_invoice.php` byte-for-byte and removes both Nexus-owned PDF files.
- Re-enable reapplies the verified themed view, helper, endpoint, and print styling.
- Uninstall performs the same restoration and removal before archiving or purging manager state.

## Validation

- PHP 8.4 syntax validation and full lifecycle suite
- TCPDF sample generation using ITFlow's bundled library
- Poppler PDF metadata inspection and PNG rendering
- Visual review of the latest rendered A4 page
- Package manifest, baseline, payload, and SHA-256 verification
- GitHub Actions validation on PHP 8.2, 8.3, and 8.4

Manager 3.2.0 ships theme payload 26.08.15 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

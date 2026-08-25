# Nexus Theme Manager 3.6.0

Version 3.6.0 adds accessibility inspection and responsive testing to Theme Studio. Administrators can evaluate a saved draft at common device widths, choose a custom viewport width, and review important presentation checks before publishing the design.

## Highlights

- Inspect body and primary-button color contrast against WCAG-oriented thresholds.
- Confirm reduced-motion state, logo alternative text, login heading and tagline content, and compact-navigation label behavior.
- Switch the exact runtime preview between desktop, tablet, and mobile widths.
- Test a custom viewport from the same Theme Studio workspace.
- Apply the responsive tester consistently to authentication, technician, authenticated-client, and guest-invoice previews.

## Upgrade notes

No database migration is required. Existing published settings, private drafts, revision history, uploaded branding, presets, and schedules remain compatible. The new tools inspect the current saved draft and do not publish or modify it.

Use the versioned release ZIP and checksum. Run `doctor`, take verified ITFlow application and database backups, install the package, then smoke-test all four preview surfaces at desktop, tablet, mobile, and custom widths before publishing a draft.

## Compatibility

- ITFlow release: 26.08
- Pinned ITFlow commit: `89b080b430aaafba5d520c4e52c57b28a9559085`
- Nexus manager: 3.6.0
- Nexus payload: 26.08.20
- PHP: 8.1 or newer

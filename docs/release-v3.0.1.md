# Nexus Theme Manager 3.0.1

This patch release repairs the Theme Studio layout controls so the live preview and installed ITFlow interface use the same visible states.

## Fixed

- Content density now updates the live navigation preview and meaningfully changes cards, tables, forms, buttons, and workspace spacing after saving.
- Menu density now produces clearly differentiated compact, comfortable, and spacious navigation rhythms.
- Header treatment now shows distinct solid, gradient, and glass results.
- Active navigation now applies pill, accent-rail, or outline treatment to both top-level and nested active links.
- Compact sidebar mode now visibly condenses labels, icons, section headings, spacing, and preview width.
- Changing a layout control automatically opens the navigation preview, so the result is visible without a separate mode switch.

## Validation

- PHP source parser validation
- Package manifest and SHA-256 verification
- Full lifecycle install, verify, disable, enable, adopt, conflict, and uninstall suite
- GUI updater policy and rollback suite
- Linux activation and systemd unit validation
- Browser interaction checks for all five repaired controls
- Updated 1440px Theme Studio screenshot

Manager 3.0.1 ships theme payload 26.08.11 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

# Nexus Theme Manager 3.4.1

This maintenance release completes the neutral empty-brand experience and makes Theme Studio and the technician Ticket queue reliable on phones and tablets.

## Fixed

- Empty Display name, Logo alt text, and Browser title fields now show `Nexus MSP` instead of the configured ITFlow company name.
- Empty authentication and technician-navigation previews also use `Nexus MSP` without writing that placeholder into saved settings.
- Theme Studio's horizontal section navigation is contained within the viewport and remains swipeable with scroll snapping.
- The mobile save footer no longer overlays form fields.
- Hero content, status cards, upload controls, preview controls, update panels, schedule controls, configuration tools, and modal actions now reflow into touch-friendly mobile layouts.
- The technician Ticket queue uses a vertical phone heading, compact metric cards, and a two-column tablet layout without horizontal page overflow.

## Preserved behavior

- Leaving Display name empty still allows live Nexus surfaces to inherit ITFlow's native company name.
- Pause skips Nexus presentation behavior, while CLI Disable and Uninstall continue to restore every managed ITFlow baseline exactly.
- No database schema or production-state change is introduced.

## Validation

- Responsive browser checks at 390px and 768px
- PHP syntax validation across every managed PHP file
- Full install, pause-state, disable, enable, adoption, and uninstall lifecycle simulation
- GUI updater and protected-command regression suite
- Manifest, payload, baseline, package, and release SHA-256 verification
- GitHub Actions validation on PHP 8.2, 8.3, and 8.4

Manager 3.4.1 ships theme payload 26.08.18 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

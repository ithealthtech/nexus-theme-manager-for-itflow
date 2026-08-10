# Nexus Theme Manager 3.1.1

This patch restores the configured brand logo to the client portal navigation.

## Fixed

- The client portal navbar now renders the active light/dark custom logo instead of the decorative cyan marker whenever **Show logo in client portal** is enabled.
- Logo size controls and cache-busted asset revisions apply to the navigation image.
- The logo remains visible at mobile widths and links back to the portal home page.
- Brand text remains as the accessible fallback when no portal logo is configured or logo placement is disabled.
- The welcome panel no longer repeats the same custom logo.
- Static Nexus stylesheets now carry the theme payload version on every rendered surface, preventing stale pre-update CSS from styling new markup.
- Guest invoice mastheads no longer fall back to raw document flow or render uploaded logos at their intrinsic dimensions after an update.

## Validation

- PHP source parser validation
- Browser rendering and computed-style checks for the authenticated portal navigation
- Package manifest and SHA-256 verification
- Full lifecycle and protected-updater test suites
- GitHub Actions validation on PHP 8.2, 8.3, and 8.4

Manager 3.1.1 ships theme payload 26.08.14 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

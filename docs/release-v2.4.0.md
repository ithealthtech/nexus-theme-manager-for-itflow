# Nexus Theme Manager 2.4.0

Nexus 2.4.0 turns the administration control panel into a complete Theme Studio while preserving the package's strict lifecycle and compatibility boundaries.

## Theme Studio

- Upload a validated PNG, JPEG, or WebP logo and choose where it appears.
- Override the displayed brand name and tagline without changing ITFlow company data.
- Personalize authentication and client-portal messaging.
- Choose Aurora, Ocean, Emerald, Ember, or Slate, then tune seven semantic colors.
- See an instant authentication preview and live accessibility contrast scores.
- Select corner style, interface density, 90–110% scale, and reduced motion.
- Export, import, reset, pause, and reactivate from the administrator interface.

## Safety

- Administrator permission checks, CSRF validation, audit logging, atomic writes, and symlink protections remain enforced.
- Logo uploads are limited to 3 MB and 4000×2000, verified by image content and MIME type, and stored under a fixed generated path.
- Theme settings are allow-listed before persistence and rendered only as validated CSS properties through a CSP-compatible same-origin endpoint.
- The web interface cannot execute shell commands or install, update, uninstall, or replace PHP files.
- Customization survives CLI disable/uninstall so a package upgrade does not erase branding.

## Compatibility

- ITFlow 26.08
- Exact ITFlow commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer
- Manager 2.4.0 / theme payload 26.08.5

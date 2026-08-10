# Nexus Theme Manager 2.5.4

Nexus 2.5.4 connects the technician navigation brand header to the custom logo managed in Theme Studio and resolves the cramped header shown with long company names.

## Technician navigation branding

- An uploaded logo can now be shown independently on authentication pages, in the technician navigation, and in the client portal.
- The technician logo remains inside ITFlow's existing dashboard link.
- When the logo is active, the company-name text remains available as the link's accessible name but is not rendered beside the image.
- Existing Theme Studio configurations inherit the new technician placement by default.

## Header polish

- Logo sizing is constrained to the available sidebar width.
- The no-logo fallback uses a compact Nexus mark and an ellipsized company name.
- Collapsed desktop navigation keeps a compact brand mark.
- The technician navigation toggle receives a consistent rounded control treatment.

## Validation

- PHP syntax validation covers every PHP entrypoint and payload.
- Lifecycle tests cover new-install defaults, upgrade compatibility, opt-out behavior, and generated custom-logo CSS.
- Browser checks cover expanded and narrow technician navigation using the release stylesheet.
- Package checksum and Linux activation validation remain part of CI.

## Compatibility

- Manager 2.5.4 / theme payload 26.08.8
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer

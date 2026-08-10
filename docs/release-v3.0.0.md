# Nexus Theme Manager 3.0.0

Nexus 3.0 turns Theme Studio into a complete ITFlow design-control system. Branding, navigation, authentication imagery, saved designs, activation timing, and recovery can now be managed from one administrator-only workspace.

## Brand and browser identity

- Separate light and dark logos are selected automatically for surface contrast.
- Both logo slots accept animated GIFs up to 8 MB and preserve their embedded playback timing, including 24fps animations, in previews and deployed surfaces.
- Logo size and alignment can be tuned without editing CSS.
- A custom favicon and browser-title base apply across agent, client, login, recovery, and MFA surfaces.
- Authentication pages support an uploaded background, top/center/bottom focal point, and adjustable dark overlay.

## Navigation design

- The live preview can switch between authentication and a representative ITFlow sidebar/header shell.
- Sidebar width supports 220–340 pixels with an optional compact presentation.
- Menu density is independent from page-content density.
- Header background and text colors are configurable with solid, gradient, and glass treatments.
- Active menu items can use a gradient pill, accent rail, or outlined treatment.

## Presets, scheduling, and recovery

- Administrators can save up to 20 named designs, apply or delete them, and move the set between installations with validated JSON export/import.
- Theme activation or pause can be scheduled up to one year ahead. The action applies on the first ITFlow request at or after the chosen time and does not require cron, sudo, or shell access for the web service.
- Every settings change snapshots the previous validated design. Rollback atomically swaps the two designs, so it can be used again to switch back.
- Uploaded assets stay available when detached or reset, allowing a rollback to restore the complete previous appearance.

## Safety

- New image slots use fixed allow-listed filenames and validate upload status, byte limits, image dimensions, detected MIME type, and decoded image type.
- Settings, presets, schedules, and rollback state are size-limited and revalidated when read.
- All administrator mutations retain ITFlow administrator enforcement, CSRF validation, audit logging, atomic writes, and same-origin asset URLs.

## Validation

- All 20 PHP files pass parser-level syntax validation.
- Lifecycle coverage includes settings migration, new control allow-lists, generated layout classes, sidebar clamping, saved-preset operations, scheduling, and rollback.
- The isolated AdminLTE Theme Studio fixture was browser-checked at 1440×1000 and 390×844 without horizontal overflow.
- The release screenshot is stored at `docs/images/nexus-theme-studio-v3.png`.

## Compatibility

- Manager 3.0.0 / theme payload 26.08.10
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer

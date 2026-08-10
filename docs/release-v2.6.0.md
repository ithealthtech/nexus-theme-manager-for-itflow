# Nexus Theme Manager 2.6.0

Nexus 2.6.0 introduces a configurable motion system that gives ITFlow dialogs and floating surfaces a polished sense of depth without slowing down daily work.

## Motion profiles

- **Subtle** uses short transitions and minimal movement.
- **Fluid** provides the balanced Nexus default with smooth easing and soft depth.
- **Snappy** is quicker and more energetic while remaining brief.

The selected profile applies to modal windows, backdrops, dropdown menus, tooltips, popovers, Select2 menus, date pickers, alerts, toasts, and floating notifications. Popper-managed transforms are preserved so animated menus retain their calculated positions.

## Theme Studio

- A new **Popup and modal motion** selector appears under Interface feel.
- The adjacent **Preview** action opens a real animated modal using the currently selected profile.
- Changing the profile or reduced-motion switch updates the current administration page immediately for preview; **Save and apply** persists the choice.
- The Theme Studio hero and design-system mark now inherit the active primary, secondary, and navigation colors and update immediately while selecting a preset or editing the palette.

## Accessibility and performance

- Theme Studio's reduced-motion preference reduces every animation and transition to effectively zero duration.
- The operating system's `prefers-reduced-motion` setting remains authoritative.
- Animations are finite, use short durations, and avoid scripts or timers in normal application views.
- Bootstrap modal focus, dismissal, keyboard, and backdrop behavior remain intact.

## Validation

- PHP syntax validation covers every PHP entrypoint and payload.
- Lifecycle tests cover profile defaults, accepted and rejected values, safe body classes, animation assets, and reduced-motion safeguards.
- Browser checks verify modal start/mid/end states, backdrop blur, dropdown positioning, reduced-motion duration, and a 390-pixel mobile viewport.
- Package checksum and Linux activation validation remain part of CI.

## Compatibility

- Manager 2.6.0 / theme payload 26.08.9
- ITFlow 26.08 at commit `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP 8.1 or newer

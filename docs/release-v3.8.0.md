# Nexus Theme Manager 3.8.0

Version 3.8.0 makes Theme Studio easier to navigate and operate on phones, tablets, laptops, and compact administrator windows. It also includes the production login bootstrap correction introduced after v3.7.0.

## Highlights

- A compact section picker replaces the wide navigation rail on tablets and phones.
- Previous and Next controls provide a clear guided path through all eight workspaces.
- The exact runtime preview can be hidden to give the editor the full available width.
- Narrow screens use a single preview-surface picker instead of eight compressed buttons.
- Save and publish actions stay reachable while editing long mobile forms.
- Responsive test presets use a touch-friendly two-by-two layout on phones.
- Update, preset, schedule, import/export, upload, and dialog controls stack cleanly at narrow widths.
- Update checks and installations return to the System & Updates workspace so status and progress remain visible.
- Login presentation now initializes after ITFlow company settings and safely returns to the native login if Nexus rendering fails.

## Compatibility

- Nexus manager: 3.8.0
- Nexus payload: 26.08.22
- ITFlow release: 26.08
- Exact ITFlow commit: `89b080b430aaafba5d520c4e52c57b28a9559085`
- PHP: 8.1 or newer

## Upgrade note

Use the versioned v3.8.0 release ZIP and checksum when they are published. GitHub's automatically generated source archive is not an installation package. Existing designs and revision history remain intact during the managed updater workflow.

## Validation

- PHP syntax checks for every managed and baseline PHP file
- Lifecycle and GUI updater security suites
- Linux activation and systemd unit checks
- Manifest and complete package checksum verification
- Responsive browser checks at phone, tablet, laptop, and widescreen widths

The login bootstrap has a native-interface fallback so a Nexus presentation error cannot prevent access to the authentication page.

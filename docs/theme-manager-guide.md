# Nexus Theme Manager beginner guide

This guide explains how to use Nexus Theme Manager 3.9.1 in plain language. It covers every workspace and control currently available in Theme Studio.

## The one concept to understand first

Nexus separates your work into two versions:

- **Published design:** what users see while the Nexus presentation layer is active.
- **Draft:** a private working copy visible only inside Theme Studio.

Changing a field does not immediately change the live site. Use this workflow:

1. Change one or more settings.
2. Select **Save draft**.
3. Review the regenerated previews and Design quality findings.
4. Select **Publish draft** only when the draft is ready for users.

**Discard draft** deletes all unpublished changes and leaves the published design untouched. Restoring a revision, applying a preset, importing a configuration, applying automatic quality fixes, or loading defaults also creates or changes a draft; those actions do not publish by themselves.

## Opening Theme Studio

Sign in to ITFlow as an administrator, then open:

**Administration → NEXUS → Theme Manager**

Theme Studio has eight workspaces:

1. Branding
2. Colors
3. Layout
4. Design quality
5. Motion
6. Content
7. Presets & schedule
8. Updates & system

On smaller screens, use the **Choose a Theme Studio section** menu. **Previous** and **Next** move through the same eight workspaces.

## A safe first-time setup

For a first design, use this order:

1. In **Branding**, set the display name, alt text, tagline, and logos.
2. In **Colors**, select the closest built-in palette and adjust it.
3. In **Layout**, keep the global defaults until the basic design looks right.
4. Select **Save draft**.
5. Check all eight preview surfaces at laptop, tablet, and phone widths.
6. Open **Design quality** and resolve important findings.
7. Select **Publish draft** and give the revision a useful name such as `Initial company design`.
8. Open **Presets & schedule**, locate the published revision, and **Pin** it as known good.

## Preview and responsive tester

The preview area can display these surfaces:

- Login
- Password reset
- Technician dashboard
- Ticket queue
- Client portal
- Mobile navigation
- Guest invoice
- Print/PDF invoice

The large frame shows the selected desktop or custom width. A separate 390-pixel phone frame appears beside it when space permits.

Use **Wide**, **Laptop**, **Tablet**, or **Phone** for common widths. Use **Custom width** to test any width from 320 to 1920 pixels. Responsive alerts identify predicted collisions or overflow at the selected width.

The preview is a saved snapshot, not an unsaved live form. After changing fields, select **Save draft** to regenerate an accurate draft preview. **Hide preview** gives the editor more room without changing the design.

## Branding

Branding controls identity, images, browser details, and login artwork.

| Option | What it does |
|---|---|
| Display name | Replaces the native ITFlow company name wherever Nexus branding is active. Leave it blank to keep ITFlow's company name. `Nexus MSP` is only preview placeholder text and is never used as the live company name. |
| Logo alt text | Supplies the accessible text description for the logo. Use a short description such as `Example MSP logo`. |
| Brand tagline | Displays supporting brand text on authentication and other branded surfaces. |
| Browser title | Changes the title shown in the browser tab. Leave it blank to use the normal fallback. |
| Light logo | Logo intended for dark surfaces. Accepts PNG, JPEG, WebP, and animated GIF files up to 8 MB. |
| Dark logo | Logo intended for light surfaces. Accepts PNG, JPEG, WebP, and animated GIF files up to 8 MB. |
| Logo size | Scales the displayed logo from 50% to 180% without changing the source file. |
| Logo alignment | Aligns branding left, center, or right where the surface supports alignment. |
| Custom favicon | Replaces the browser-tab icon. Accepts PNG, JPEG, or WebP. A square image works best. |
| Login background | Adds a PNG, JPEG, or WebP background image to authentication pages. Maximum size is 8 MB. |
| Background focal point | Keeps the top, center, or bottom portion of the login background in focus when the image is cropped by the browser. |
| Background overlay | Places a dark overlay from 0% to 90% over the login background. Increase it when text or the login card needs stronger separation from the image. |
| Authentication pages | Shows or hides the configured logo on login and password-reset surfaces. |
| Technician navigation | Shows or hides the logo in the technician sidebar/header branding area. |
| Client portal | Shows or hides the logo after a client signs in to the portal. |

### Advanced asset processing

Asset processing is optional and applies while uploading a logo.

| Option | What it does |
|---|---|
| Crop X | Number of pixels removed from the left before processing. |
| Crop Y | Number of pixels removed from the top before processing. |
| Crop width | Width of the rectangle to keep. Use `0` or leave the default to keep the full available width. |
| Crop height | Height of the rectangle to keep. Use `0` or leave the default to keep the full available height. |
| Output width | Resizes the processed image to this width, up to 4000 pixels. `0` keeps the original width unless the other dimension requires proportional resizing. |
| Output height | Resizes to this height, up to 4000 pixels. `0` allows automatic proportional height. |

The asset-health cards report whether each asset exists, its dimensions, size, static or animated state, GIF frame count and estimated frame rate, WebP readiness, and any warnings. Nexus recognizes approximately 24fps GIF timing when the measured rate is between 23 and 25fps. Animated GIF timing is preserved; automatic WebP companions apply where the uploaded format and server image support allow them.

Removing an asset saves that removal into the draft. Publish the draft before expecting the removal to become the intentional live design.

## Colors

Start with one of the built-in palettes: **Aurora**, **Ocean**, **Emerald**, **Ember**, or **Slate**. Selecting a palette fills the global color fields; you can then adjust individual colors.

Every color value is a six-digit hexadecimal value such as `#2563eb`.

| Option | Main use |
|---|---|
| Primary accent | Main buttons, selected controls, links, highlights, and focus color. |
| Gradient accent | Second color used with the primary accent in gradients. |
| Sidebar | Technician navigation background. |
| Header | Main header and navigation-header background. |
| Header text | Text and icons displayed on the header color. |
| Login background | Base authentication-page background when no custom image is used. |
| Page background | Background behind cards and content panels. |
| Cards & surfaces | Cards, forms, panels, and elevated content surfaces. |
| Primary text | Main readable text on cards and page surfaces. |

The **Accessibility contrast** result updates while colors are edited. It is a quick warning, not a replacement for the complete Design quality audit.

## Layout

### Global appearance

| Option | What it does |
|---|---|
| Corner style | **Sharp** minimizes rounding, **Balanced** uses moderate rounding, and **Rounded** uses the largest corner radius. |
| Content density | Changes spacing inside page content. **Compact** fits more information, **Comfortable** is the default balance, and **Spacious** increases breathing room and touch space. |
| Menu density | Independently changes spacing between navigation entries. |
| Sidebar width | Sets the desktop technician sidebar from 220 to 340 pixels. |
| Header treatment | **Solid** uses the header color, **Gradient** blends accent colors, and **Glass** applies the translucent Nexus header treatment. |
| Active navigation | **Gradient pill**, **Accent rail**, or **Outline** changes the appearance of the currently selected navigation item. |
| Compact sidebar labels and section spacing | Reduces sidebar label and section spacing without changing page-content density. |
| Interface scale | Scales interface typography from 90% to 110%. |

### Per-surface design profiles

Global settings apply everywhere unless a surface override is enabled. Available profiles are:

- Technician
- Client portal
- Login & reset
- Guest invoice
- Print/PDF

Enable an override only when that surface needs different settings. Each profile can override its accent, header, page, card, and text colors plus content density. A blank density value means **Use global**. Disabling a profile returns that surface to the global design; it does not delete the global settings.

### Navigation builder

Desktop sidebar and mobile navigation are configured independently. For each managed item you can:

- move it up or down;
- change its visible label;
- select an approved icon;
- show it to administrators, technicians, or both; and
- turn **Show** off to hide it in that viewport.

The managed destinations are fixed. The builder changes presentation and role-based visibility; it does not create arbitrary links and does not replace ITFlow authorization checks. Hiding a menu entry is not a security permission.

### Automatic dark mode

| Option | What it does |
|---|---|
| System preference | Follows the visitor's operating-system/browser color preference. |
| Always light | Forces the light palette unless an allowed user selection overrides it. |
| Always dark | Forces the dark palette unless an allowed user selection overrides it. |
| Scheduled | Uses the dark palette between **Dark from** and **Light from**, based on the server's configured local time. Overnight ranges are supported. |
| Allow each user to select | Adds light, dark, and system choices for users. A saved user choice takes priority over the configured default mode. |
| Dark colors | Defines the dark-mode accent, sidebar, header, page, card, and text colors. |

The dark logo and light logo are selected according to the rendered surface color so the brand remains visible.

The accessibility summary at the bottom of Layout checks body contrast, primary-button contrast, reduced-motion behavior, brand text, logo alt text, login copy, and compact navigation labels for the current draft.

## Design quality

Design quality audits the saved draft at a 390-pixel phone width. It reports a score plus errors, warnings, and informational notes.

Each finding includes:

- the affected surface;
- a plain-language explanation; and
- **Open setting**, which returns you to the responsible control.

**Apply fixes to draft** makes only the allow-listed corrections supported by Nexus, including validated colors, scale, spacing, logo text, and reduced-motion settings. It never publishes automatically. Review the regenerated preview before publishing.

## Motion

**Popup and modal motion** affects modals, dropdowns, tooltips, alerts, toasts, popovers, and floating panels:

- **Subtle:** short duration and low movement.
- **Fluid:** smooth default motion.
- **Snappy:** quicker movement with more energy.

Select **Preview** to see the chosen modal behavior. **Reduce animations and hover motion** minimizes Nexus motion. A visitor's operating-system reduced-motion preference always takes priority.

## Content

| Option | Where it appears |
|---|---|
| Login eyebrow | Small label above the main login heading. |
| Login heading | Main authentication-page heading, such as `Welcome back`. |
| Login message | Supporting login instructions below the heading. |
| Portal heading | Main heading in the signed-in client portal. |
| Portal message | Supporting text in the signed-in client portal. |

These controls change presentation text only. They do not change authentication, ticket, invoice, or permission behavior.

## Presets & schedule

### Draft comparison

**Unpublished vs. live** lists the saved draft fields that differ from the published design. Use it as the final checklist before publishing.

### Revision history

Nexus retains up to 50 published design revisions. Each entry records the action, administrator, creation time, design hash, and differences from the live design.

- **Restore** loads that revision into the private draft. It does not immediately change the live site.
- **Pin** marks a revision as known good and protects it during normal history trimming.
- **Unpin** removes that protection.
- **Compare with live** shows before-and-after values.

Publishing can include an optional revision name. Use meaningful names such as `Approved summer branding` rather than leaving every revision unnamed.

### Saved presets

A saved preset is a reusable design configuration. Nexus supports up to 20 saved presets.

- **Save** stores the current design under the supplied name.
- **Apply** loads the preset into the draft while retaining the installation's current uploaded asset references.
- The trash button deletes the saved preset, not the published design.
- **Export presets** downloads the preset collection as JSON.
- **Import presets** validates a Nexus preset export and creates new secure preset identifiers.

### Scheduled activation

Choose a date and time, then choose **Activate** or **Pause**. The browser converts the chosen local date/time to UTC for storage. Nexus applies the action on the first ITFlow request at or after the scheduled time. **Cancel** removes the pending action.

Scheduling activates or pauses the entire Nexus presentation layer; it does not schedule publication of an unpublished draft.

## Updates & system

### Nexus updates

If the protected updater is not configured, Theme Studio displays a one-time root command. Run that exact command through SSH. Theme Studio itself never receives general shell or sudo access.

Once configured:

- **Check for updates** checks the fixed Nexus GitHub repository.
- **Install** appears only when a newer release is available.
- The timeline reports checking, download, checksum verification, staging, current-version protection, installation, health checks, and finalization.
- **Retry** repeats only the previously approved check or update action after a retryable failure.
- **Release notes** opens the release being offered.
- Rollback status states whether the previous package was restored or needs manual attention.

The updater verifies the release checksum and package manifest. It runs post-update health checks and attempts to restore the previous release if replacement activation fails.

For the supported SSH update procedure, see [Update from the command line](../README.md#update-from-the-command-line).

### Recovery mode

| Action | Result |
|---|---|
| Run health check | Checks required managed files, readable settings, and generated CSS structure. |
| Restore known-good | Creates a safety snapshot, then restores and activates the newest pinned revision. If none is pinned, it uses the newest available revision. |
| Emergency disable | Immediately bypasses all Nexus presentation so native ITFlow administrator access remains available. Settings and history are retained. |

The top **Pause theme** button also removes all Nexus presentation customizations while retaining settings for later reactivation. **Activate theme** re-enables the saved published design. Neither button uninstalls the package.

### Configuration tools

| Tool | What it does |
|---|---|
| Export workspace | Downloads the current Nexus configuration as JSON. |
| Import to draft | Validates an exported Nexus configuration and loads it into the private draft. Existing uploaded assets remain attached unless changed separately. |
| Diagnostics bundle | Downloads sanitized versions, compatibility information, setting hashes, quality findings, asset health, revision counts, and updater status. It excludes database credentials and filesystem paths. |
| Defaults to draft | Loads Nexus factory defaults into the private draft. The live design does not change until publication. |

## Common tasks

### Change a logo safely

1. Open **Branding**.
2. Enter useful **Logo alt text**.
3. Upload the light logo, dark logo, or both.
4. Adjust size and alignment.
5. Select **Save draft**.
6. Review Login, Client, Dashboard, Tickets, Mobile navigation, Invoice, and Print/PDF previews.
7. Open **Design quality** and resolve logo warnings.
8. Select **Publish draft**.

### Change colors safely

1. Open **Colors** and select the closest built-in palette.
2. Adjust individual colors.
3. Watch the contrast result.
4. Select **Save draft**.
5. Check light and dark mode plus every affected surface.
6. Review **Design quality**.
7. Publish only after the draft passes review.

### Undo a published design

1. Open **Presets & schedule**.
2. Find the desired revision.
3. Select **Compare with live**.
4. Select **Restore**.
5. Review the restored draft.
6. Select **Publish draft** to make it live.

### Temporarily remove every customization

Select **Pause theme**. Native ITFlow presentation becomes active while Nexus settings remain available. Select **Activate theme** to return to the published Nexus design.

### Recover when the styled interface is unhealthy

1. Open **Updates & system** if Theme Studio remains accessible.
2. Select **Run health check**.
3. Use **Restore known-good** for a design-setting problem.
4. Use **Emergency disable** when native presentation must be restored immediately.
5. Download a **Diagnostics bundle** before making additional changes.

## Troubleshooting

### My changes are not visible

- Confirm that you selected **Save draft** after editing.
- Confirm that you selected **Publish draft** after reviewing the saved draft.
- Confirm that the theme is **Active**, not paused or emergency-disabled.
- For a logo change, inspect the relevant surface switch and the light/dark logo choice.

### The preview did not update while I typed

This is expected. Unsaved form values are not treated as a trustworthy preview. Select **Save draft** to validate the settings and regenerate the preview.

### A menu item disappeared

Check both desktop and mobile entries in **Layout → Navigation builder**. Confirm **Show** is selected and the current user's role is enabled.

### Dark mode is not using the expected choice

If user selection is enabled, the user's saved light/dark/system choice overrides the configured default. Scheduled dark mode uses the server's configured local time.

### Update verification reports that a managed file changed

Nexus intentionally stops rather than overwriting an unexpected file. Preserve the changed file, compare it with the installed package, and restore the exact managed version only after determining why it changed. Do not alter manager state or checksums to bypass verification.

### Theme Studio says the updater needs setup

Run the exact one-time command displayed in **Updates & system** as root through SSH. Return to Theme Studio and select **Check for updates**.

## What Nexus does not change

Theme Manager changes presentation and managed navigation display. It does not replace ITFlow authentication, database logic, ticket permissions, invoice authorization, or role enforcement. Pause, recovery, disable, uninstall, and update operations remain deliberately separate so a visual change cannot silently become a permission or server-management change.

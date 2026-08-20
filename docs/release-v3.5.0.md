# Nexus Theme Manager 3.5.0

Version 3.5.0 turns Theme Studio into a safe publishing workspace. Administrators can save and revisit design work without changing the live ITFlow interface, inspect the same rendering model used by live pages, and publish or restore a complete design as one revision.

## Highlights

- Save design work to a private draft; live authentication, technician, client, and guest pages remain unchanged.
- Publish the complete draft atomically with an optional revision name.
- Compare the draft with live settings field by field before publishing.
- Review up to 50 published revisions with actor, time, name, settings hash, and exact comparison.
- Restore any retained publication to the draft for review instead of changing the live design immediately.
- Preview authentication, technician, authenticated-client, and guest-invoice surfaces through sandboxed runtime documents generated from the same settings model and stylesheets as live pages.
- Protect draft writes with an optimistic version token and filesystem lock so stale tabs and simultaneous requests cannot overwrite newer work.
- Store uploads with immutable filenames and retain assets referenced by the live design, draft, or history.

## Upgrade notes

No database migration is required. Nexus creates draft and revision JSON files in ITFlow's existing writable `uploads` directory when those features are first used. Existing active settings remain the published design after upgrade.

Use the versioned release ZIP and checksum. Run `doctor`, take verified ITFlow application and database backups, install the package, then smoke-test Theme Studio Save draft, all four preview tabs, Publish, revision comparison, Restore to draft, Discard, and Pause/Activate in staging.

## Compatibility

- ITFlow release: 26.08
- Pinned ITFlow commit: `89b080b430aaafba5d520c4e52c57b28a9559085`
- Nexus manager: 3.5.0
- Nexus payload: 26.08.19
- PHP: 8.1 or newer

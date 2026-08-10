# Nexus Theme Manager 3.4.0

This release turns the technician Ticket queue row from the Theme Studio preview into a real, live part of ITFlow's Tickets page.

## Added

- Four responsive queue cards above the existing technician ticket list.
- Live open-ticket count for unresolved, unarchived queue items.
- Waiting-on-client count based on the direction of each ticket's latest client-visible reply.
- High-priority count covering unresolved High and Urgent tickets.
- Median first-response time using ITFlow's recorded creation and first-response timestamps.

## Access and lifecycle behavior

- Every metric honors the same client-access scope as the signed-in technician's ticket list.
- Web Pause removes the row immediately and avoids running its aggregate queries.
- CLI Disable and Uninstall restore the original pinned ITFlow Tickets page byte-for-byte.
- CLI Enable reapplies the managed Tickets page and the queue styling.

## Validation

- PHP syntax validation across all managed PHP files
- Full install, pause-state, disable, enable, and uninstall lifecycle assertions
- Updater and protected-command regression checks
- Exact pinned-baseline and manifest SHA-256 verification
- Responsive visual review using the release stylesheet and sanitized data
- GitHub Actions validation on PHP 8.2, 8.3, and 8.4

Manager 3.4.0 ships theme payload 26.08.17 and remains pinned to ITFlow 26.08 commit `89b080b430aaafba5d520c4e52c57b28a9559085`.

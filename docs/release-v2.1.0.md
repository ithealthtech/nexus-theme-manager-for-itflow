# Nexus Theme Manager for IT Flow 2.1.0

Adds a GitHub-hosted bootstrap installer for a complete latest-release installation workflow.

Highlights:

- Downloads the latest published versioned ZIP and adjacent SHA-256 file from GitHub.
- Verifies the release checksum before extraction.
- Rejects archive entries outside the expected versioned package directory.
- Extracts the verified package to `/opt/Nexus-Theme-Manager-for-ITFlow-<version>`.
- Runs compatibility preflight, installation, verification, and status checks.
- Requires an explicit ITFlow root and refuses an existing extraction directory.
- Retains exact support for ITFlow 26.08 at commit `ccaa45b0ae9900ad731a6491559f65ff8d87a8f3`.
- Retains the 55-assertion lifecycle suite and PHP syntax validation across all managed templates.
- Includes a sanitized technician-interface screenshot captured from an isolated local ITFlow 26.08/AdminLTE test environment using the release stylesheet.

Download `Nexus-Theme-Manager-for-ITFlow-2.1.0.zip` and verify it with the adjacent `.sha256.txt` file before extracting it outside the ITFlow web root. Alternatively, download `install-latest.sh` from the repository and run it as root with an explicit `--root` path.

This package only supports the exact ITFlow revision declared above. The bootstrap installer does not bypass `doctor`, package integrity verification, or compatibility checks.

Version 1.0.0 users must verify and uninstall that payload before installing 2.1.0. See the migration section in `README.md`.

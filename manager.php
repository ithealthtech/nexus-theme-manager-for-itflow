<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const NEXUS_EXIT_OK = 0;
const NEXUS_EXIT_USAGE = 2;
const NEXUS_EXIT_CONFLICT = 3;
const NEXUS_EXIT_VERIFY = 4;
const NEXUS_EXIT_OPERATION = 5;
const NEXUS_MANAGED_FILE_COUNT = 23;

final class ThemeManagerException extends RuntimeException
{
    public function __construct(string $message, public readonly int $exitCode = NEXUS_EXIT_OPERATION)
    {
        parent::__construct($message);
    }
}

final class ThemeManager
{
    private string $packageRoot;
    private string $root;
    private string $stateRoot;
    private string $instanceId;
    private string $stateDir;
    private string $stateFile;
    private array $manifest;
    private bool $json;

    public function __construct(string $packageRoot, string $root, ?string $stateRoot, bool $json)
    {
        $resolvedPackage = realpath($packageRoot);
        $resolvedRoot = realpath($root);

        if ($resolvedPackage === false || !is_dir($resolvedPackage)) {
            throw new ThemeManagerException("Package directory does not exist: $packageRoot", NEXUS_EXIT_USAGE);
        }
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new ThemeManagerException("ITFlow root does not exist: $root", NEXUS_EXIT_USAGE);
        }

        $this->packageRoot = rtrim($resolvedPackage, DIRECTORY_SEPARATOR);
        $this->root = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        $this->json = $json;

        $defaultStateRoot = PHP_OS_FAMILY === 'Windows'
            ? $this->root . DIRECTORY_SEPARATOR . '.nexus-theme-manager-state'
            : '/var/lib/nexus-itflow-theme';
        $this->stateRoot = rtrim($stateRoot ?: $defaultStateRoot, DIRECTORY_SEPARATOR);
        $this->instanceId = substr(hash('sha256', $this->root), 0, 16);
        $this->stateDir = $this->stateRoot . DIRECTORY_SEPARATOR . $this->instanceId;
        $this->stateFile = $this->stateDir . DIRECTORY_SEPARATOR . 'state.json';
        $this->manifest = $this->loadManifest();
    }

    public function doctor(): void
    {
        $this->assertPackageIntegrity();
        $this->assertITFlowRoot();

        if (is_file($this->stateFile)) {
            $state = $this->loadState();
            $this->verifyState($state, true);
            $this->emit([
                'status' => 'ready',
                'message' => 'Theme manager is installed and its current state verifies.',
                'mode' => $state['mode'],
                'root' => $this->root,
                'state_directory' => $this->stateDir,
            ]);
            return;
        }

        $this->assertBaselineCompatible();
        $this->lintPhpFiles($this->packageRoot . DIRECTORY_SEPARATOR . 'payload');
        $this->emit([
            'status' => 'ready',
            'message' => 'Preflight passed. This ITFlow checkout is compatible with the package.',
            'root' => $this->root,
            'package_version' => $this->manifest['package_version'],
            'itflow_release' => $this->manifest['compatible_itflow']['release'],
            'itflow_commit' => $this->manifest['compatible_itflow']['commit'],
        ]);
    }

    public function install(bool $yes): void
    {
        $this->withLock(function () use ($yes): void {
            $this->assertPackageIntegrity();
            $this->assertITFlowRoot();
            if (is_file($this->stateFile)) {
                throw new ThemeManagerException(
                    'Theme manager state already exists. Use status, verify, enable, disable, or uninstall.',
                    NEXUS_EXIT_CONFLICT
                );
            }

            $this->assertBaselineCompatible();
            $this->lintPhpFiles($this->packageRoot . DIRECTORY_SEPARATOR . 'payload');
            $this->confirm('Install the Nexus theme into ' . $this->root . '?', $yes);
            $this->clearWebThemeControlState();

            $state = $this->createStateAndBackup();

            try {
                $this->applyPayload($state);
                $state['status'] = 'installed';
                $state['mode'] = 'enabled';
                $state['installed_at'] = gmdate('c');
                $state['updated_at'] = gmdate('c');
                $this->writeState($state);
                $this->verifyState($state, true);
            } catch (Throwable $error) {
                try {
                    $this->restoreOriginals($state, false);
                    $this->removeTree($this->stateDir);
                } catch (Throwable $rollbackError) {
                    throw new ThemeManagerException(
                        "Install failed and automatic rollback also failed. Install error: {$error->getMessage()} " .
                        "Rollback error: {$rollbackError->getMessage()}. Backups remain at {$this->stateDir}.",
                        NEXUS_EXIT_OPERATION
                    );
                }
                throw new ThemeManagerException('Install failed and was rolled back: ' . $error->getMessage(), NEXUS_EXIT_OPERATION);
            }

            $this->emit([
                'status' => 'installed',
                'mode' => 'enabled',
                'message' => 'Theme installed and verified. Reload the web/PHP service to clear opcode caches.',
                'root' => $this->root,
                'state_directory' => $this->stateDir,
            ]);
        });
    }

    public function adopt(bool $yes): void
    {
        $this->withLock(function () use ($yes): void {
            $this->assertPackageIntegrity();
            $this->assertITFlowRoot();
            if (is_file($this->stateFile)) {
                throw new ThemeManagerException(
                    'Theme manager state already exists. Use status or verify instead of adopt.',
                    NEXUS_EXIT_CONFLICT
                );
            }

            $this->assertPayloadActive();
            $this->lintPhpFiles($this->root);
            $this->confirm('Adopt the existing exact theme installation into managed state?', $yes);

            try {
                $state = $this->createAdoptedState();
                $this->verifyState($state, true);
            } catch (Throwable $error) {
                if (is_dir($this->stateDir)) {
                    $this->removeTree($this->stateDir);
                }
                throw new ThemeManagerException('Adoption failed without changing ITFlow files: ' . $error->getMessage(), NEXUS_EXIT_OPERATION);
            }

            $this->emit([
                'status' => 'adopted',
                'mode' => 'enabled',
                'message' => 'Existing exact theme files are now managed. No ITFlow file was rewritten.',
                'root' => $this->root,
                'state_directory' => $this->stateDir,
            ]);
        });
    }

    public function status(): void
    {
        $this->assertITFlowRoot();
        if (!is_file($this->stateFile)) {
            $this->emit([
                'status' => 'not-installed',
                'message' => 'No active Nexus theme-manager installation was found.',
                'root' => $this->root,
            ]);
            return;
        }

        $state = $this->loadState();
        $issues = $this->stateIssues($state);
        $drift = $this->driftReport($state);
        $counts = $this->driftCounts($drift);
        $detected = $this->detectITFlowVersion();

        /* Say what to do, not just that something is wrong. Drift that is purely
           reverted files is the ITFlow-update case and reapply fixes it
           unattended; anything modified needs a human first. */
        if ($drift === []) {
            $message = $issues === [] ? 'Managed files match the recorded state.' : 'Managed files have conflicts.';
        } elseif ($counts['modified'] === 0) {
            $message = sprintf(
                '%d managed file(s) were reverted, which is what an ITFlow update does. Run reapply to restore them.',
                count($drift)
            );
        } else {
            $message = sprintf(
                '%d managed file(s) drifted, %d of them changed outside the theme manager. Review those before running reapply.',
                count($drift),
                $counts['modified']
            );
        }

        $this->emit([
            'status' => $issues === [] ? 'healthy' : 'conflict',
            'mode' => $state['mode'] ?? 'unknown',
            'message' => $message,
            'issues' => $issues,
            'drift' => $drift,
            'drift_counts' => $counts,
            'reapply_recommended' => $drift !== [] && $counts['modified'] === 0,
            'itflow_version' => $detected,
            'itflow_version_supported' => $this->itflowVersionMatchesPin($detected),
            'root' => $this->root,
            'state_directory' => $this->stateDir,
            'package_version' => $state['package_version'] ?? null,
            'installed_at' => $state['installed_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'reapplied_at' => $state['reapplied_at'] ?? null,
        ]);
    }

    /*
     * Re-install the payload over files an ITFlow update reverted.
     *
     * Refuses by default when any managed file was changed to something that is
     * neither this package nor the supported baseline: that is somebody's own
     * edit, and silently overwriting it is worse than stopping. --force says to
     * overwrite it anyway.
     */
    public function reapply(bool $yes, bool $force): void
    {
        $this->withLock(function () use ($yes, $force): void {
            $this->assertPackageIntegrity();
            $this->assertITFlowRoot();
            $state = $this->loadState();

            if (($state['mode'] ?? null) !== 'enabled') {
                throw new ThemeManagerException(
                    'The theme is disabled, so there is nothing to re-apply. Use enable to turn it back on.',
                    NEXUS_EXIT_CONFLICT
                );
            }

            $drift = $this->driftReport($state);
            if ($drift === []) {
                $this->emit([
                    'status' => 'healthy',
                    'mode' => 'enabled',
                    'message' => 'Every managed file already matches this package. Nothing to re-apply.',
                    'root' => $this->root,
                ]);
                return;
            }

            $counts = $this->driftCounts($drift);
            $blocked = array_values(array_filter($drift, static fn(array $i): bool => $i['kind'] === 'modified'));

            if ($blocked !== [] && !$force) {
                $detected = $this->detectITFlowVersion();
                $note = $this->itflowVersionMatchesPin($detected)
                    ? 'These files were changed outside the theme manager.'
                    : sprintf(
                        'This ITFlow installation is %s but the package supports %s, so these files are most likely a newer ITFlow rather than local edits. Install the Nexus release for %s instead of forcing.',
                        $detected,
                        $this->manifest['compatible_itflow']['release'],
                        $detected
                    );
                throw new ThemeManagerException(
                    "Refusing to re-apply. $note Review them, then re-run with --force to overwrite:\n- " .
                    implode("\n- ", array_column($blocked, 'path')),
                    NEXUS_EXIT_CONFLICT
                );
            }

            $summary = sprintf(
                '%d reverted, %d missing, %d externally modified',
                $counts['reverted'],
                $counts['missing'],
                $counts['modified']
            );
            $this->confirm("Re-apply the Nexus theme payload to " . $this->root . " ($summary)?", $yes);

            $this->applyPayload($state);
            $state['updated_at'] = gmdate('c');
            $state['reapplied_at'] = gmdate('c');
            $this->writeState($state);
            $this->verifyState($state, true);

            $this->emit([
                'status' => 'reapplied',
                'mode' => 'enabled',
                'message' => 'Managed files were restored from this package and verified. Reload the web/PHP service to clear opcode caches.',
                'restored' => count($drift),
                'drift_counts' => $counts,
                'drift' => $drift,
                'root' => $this->root,
            ]);
        });
    }

    public function verify(): void
    {
        $this->assertPackageIntegrity();
        $this->assertITFlowRoot();
        $state = $this->loadState();
        $this->verifyState($state, true);
        $this->emit([
            'status' => 'verified',
            'mode' => $state['mode'],
            'message' => 'Package integrity, managed file hashes, and PHP syntax checks passed.',
            'root' => $this->root,
        ]);
    }

    public function disable(bool $yes): void
    {
        $this->withLock(function () use ($yes): void {
            $this->assertPackageIntegrity();
            $this->assertITFlowRoot();
            $state = $this->loadState();
            if (($state['mode'] ?? null) !== 'enabled') {
                throw new ThemeManagerException('Theme is not enabled.', NEXUS_EXIT_CONFLICT);
            }
            $this->verifyState($state, false);
            $this->confirm('Disable the theme and restore the pre-theme templates?', $yes);
            $this->disableInternal($state);
            $this->emit([
                'status' => 'disabled',
                'mode' => 'disabled',
                'message' => 'Theme disabled. Original templates are active; manager state and backups were retained.',
                'root' => $this->root,
            ]);
        });
    }

    public function enable(bool $yes): void
    {
        $this->withLock(function () use ($yes): void {
            $this->assertPackageIntegrity();
            $this->assertITFlowRoot();
            $state = $this->loadState();
            if (($state['mode'] ?? null) !== 'disabled') {
                throw new ThemeManagerException('Theme is not disabled.', NEXUS_EXIT_CONFLICT);
            }
            $this->verifyState($state, false);
            $this->lintPhpFiles($this->packageRoot . DIRECTORY_SEPARATOR . 'payload');
            $this->confirm('Enable the Nexus theme?', $yes);
            $this->clearWebThemeControlState();
            $this->applyPayload($state);
            $state['mode'] = 'enabled';
            $state['status'] = 'installed';
            $state['updated_at'] = gmdate('c');
            $this->writeState($state);
            $this->verifyState($state, true);
            $this->emit([
                'status' => 'enabled',
                'mode' => 'enabled',
                'message' => 'Theme enabled and verified. Reload the web/PHP service to clear opcode caches.',
                'root' => $this->root,
            ]);
        });
    }

    public function uninstall(bool $yes, bool $purge): void
    {
        $this->withLock(function () use ($yes, $purge): void {
            $this->assertPackageIntegrity();
            $this->assertITFlowRoot();
            $state = $this->loadState();
            $this->verifyState($state, false);
            $this->confirm('Uninstall the theme and restore the pre-theme templates?', $yes);

            if (($state['mode'] ?? null) === 'enabled') {
                $this->disableInternal($state);
                $state = $this->loadState();
            }

            $this->verifyState($state, true);
            $archive = null;

            if ($purge) {
                $this->removeTree($this->stateDir);
            } else {
                $archiveRoot = $this->stateRoot . DIRECTORY_SEPARATOR . 'archives';
                $this->ensureDirectory($archiveRoot, 0700);
                $archive = $archiveRoot . DIRECTORY_SEPARATOR . $this->instanceId . '-' . gmdate('Ymd-His');
                if (!@rename($this->stateDir, $archive)) {
                    throw new ThemeManagerException("Could not archive manager state to $archive.");
                }
            }

            $this->emit([
                'status' => 'uninstalled',
                'message' => $purge
                    ? 'Theme removed, original templates restored, and manager state purged.'
                    : 'Theme removed and original templates restored. Recovery state was archived outside the web root.',
                'root' => $this->root,
                'recovery_archive' => $archive,
            ]);
        });
    }

    private function disableInternal(array &$state): void
    {
        if (($state['mode'] ?? null) !== 'enabled') {
            throw new ThemeManagerException('Cannot disable: recorded mode is not enabled.', NEXUS_EXIT_CONFLICT);
        }
        $this->verifyState($state, false);
        $this->clearWebThemeControlState();
        $this->restoreOriginals($state, true);
        $state['mode'] = 'disabled';
        $state['status'] = 'installed';
        $state['updated_at'] = gmdate('c');
        $this->writeState($state);
        $this->verifyState($state, true);
    }

    private function createStateAndBackup(): array
    {
        $this->ensureDirectory($this->stateRoot, 0700);
        if (file_exists($this->stateDir)) {
            throw new ThemeManagerException("State directory already exists: {$this->stateDir}", NEXUS_EXIT_CONFLICT);
        }
        $this->ensureDirectory($this->stateDir, 0700);
        $backupRoot = $this->stateDir . DIRECTORY_SEPARATOR . 'original';
        $this->ensureDirectory($backupRoot, 0700);

        $files = [];
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            $originalExists = is_file($target);
            $metadata = $originalExists ? $this->fileMetadata($target) : $this->newFileMetadata($target);
            $backupRelative = $originalExists ? $relative : null;

            if ($originalExists) {
                $backup = $backupRoot . DIRECTORY_SEPARATOR . $this->nativePath($relative);
                $this->ensureDirectory(dirname($backup), 0700);
                if (!copy($target, $backup)) {
                    throw new ThemeManagerException("Could not back up $relative.");
                }
                @chmod($backup, 0600);
            }

            $files[$relative] = [
                'original_exists' => $originalExists,
                'original_sha256' => $originalExists ? $this->hashFile($target) : null,
                'payload_sha256' => $entry['payload_sha256'],
                'backup_relative' => $backupRelative,
                'mode' => $metadata['mode'],
                'uid' => $metadata['uid'],
                'gid' => $metadata['gid'],
            ];
        }

        $state = [
            'schema' => 1,
            'package_id' => $this->manifest['package_id'],
            'package_version' => $this->manifest['package_version'],
            'theme_version' => $this->manifest['theme_version'],
            'manifest_sha256' => $this->hashFile($this->packageRoot . DIRECTORY_SEPARATOR . 'manifest.json'),
            'root' => $this->root,
            'instance_id' => $this->instanceId,
            'status' => 'installing',
            'mode' => 'disabled',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'files' => $files,
        ];
        $this->writeState($state);
        return $state;
    }

    private function createAdoptedState(): array
    {
        $this->ensureDirectory($this->stateRoot, 0700);
        if (file_exists($this->stateDir)) {
            throw new ThemeManagerException("State directory already exists: {$this->stateDir}", NEXUS_EXIT_CONFLICT);
        }
        $this->ensureDirectory($this->stateDir, 0700);
        $backupRoot = $this->stateDir . DIRECTORY_SEPARATOR . 'original';
        $this->ensureDirectory($backupRoot, 0700);

        $files = [];
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            $metadata = $this->fileMetadata($target);
            $hasBaseline = $entry['baseline_sha256'] !== null;

            if ($hasBaseline) {
                $baseline = $this->packageRoot . DIRECTORY_SEPARATOR . 'baseline' . DIRECTORY_SEPARATOR . $this->nativePath($relative);
                $backup = $backupRoot . DIRECTORY_SEPARATOR . $this->nativePath($relative);
                $this->ensureDirectory(dirname($backup), 0700);
                if (!copy($baseline, $backup)) {
                    throw new ThemeManagerException("Could not create adopted original backup for $relative.");
                }
                @chmod($backup, 0600);
            }

            $files[$relative] = [
                'original_exists' => $hasBaseline,
                'original_sha256' => $entry['baseline_sha256'],
                'payload_sha256' => $entry['payload_sha256'],
                'backup_relative' => $hasBaseline ? $relative : null,
                'mode' => $metadata['mode'],
                'uid' => $metadata['uid'],
                'gid' => $metadata['gid'],
            ];
        }

        $state = [
            'schema' => 1,
            'package_id' => $this->manifest['package_id'],
            'package_version' => $this->manifest['package_version'],
            'theme_version' => $this->manifest['theme_version'],
            'manifest_sha256' => $this->hashFile($this->packageRoot . DIRECTORY_SEPARATOR . 'manifest.json'),
            'root' => $this->root,
            'instance_id' => $this->instanceId,
            'status' => 'installed',
            'mode' => 'enabled',
            'created_at' => gmdate('c'),
            'installed_at' => gmdate('c'),
            'adopted_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'files' => $files,
        ];
        $this->writeState($state);
        return $state;
    }

    private function applyPayload(array $state): void
    {
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $source = $this->packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . $this->nativePath($relative);
            $target = $this->targetPath($relative);
            $metadata = $state['files'][$relative] ?? null;
            if (!is_array($metadata)) {
                throw new ThemeManagerException("State metadata is missing for $relative.", NEXUS_EXIT_VERIFY);
            }
            $this->atomicCopy($source, $target, $metadata);
            if ($this->hashFile($target) !== $entry['payload_sha256']) {
                throw new ThemeManagerException("Installed hash verification failed for $relative.", NEXUS_EXIT_VERIFY);
            }
        }
    }

    private function restoreOriginals(array $state, bool $conflictSafe): void
    {
        $backupRoot = $this->stateDir . DIRECTORY_SEPARATOR . 'original';
        // Reverse dependency order so navigation and templates are restored before
        // the shared Nexus helper and stylesheet are removed.
        foreach (array_reverse($this->manifest['files']) as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            $metadata = $state['files'][$relative] ?? null;
            if (!is_array($metadata)) {
                throw new ThemeManagerException("State metadata is missing for $relative.", NEXUS_EXIT_VERIFY);
            }

            if ($conflictSafe && is_file($target) && $this->hashFile($target) !== $entry['payload_sha256']) {
                throw new ThemeManagerException(
                    "Refusing to overwrite a changed managed file during restore: $relative",
                    NEXUS_EXIT_CONFLICT
                );
            }

            if ($metadata['original_exists']) {
                $backup = $backupRoot . DIRECTORY_SEPARATOR . $this->nativePath((string)$metadata['backup_relative']);
                if (!is_file($backup) || $this->hashFile($backup) !== $metadata['original_sha256']) {
                    throw new ThemeManagerException("Original backup is missing or corrupt for $relative.", NEXUS_EXIT_VERIFY);
                }
                $this->atomicCopy($backup, $target, $metadata);
            } elseif (is_file($target)) {
                if (!@unlink($target)) {
                    throw new ThemeManagerException("Could not remove managed file $relative.");
                }
            }
        }
    }

    private function verifyState(array $state, bool $lint): void
    {
        $issues = $this->stateIssues($state);
        if ($issues !== []) {
            throw new ThemeManagerException("Managed-state verification failed:\n- " . implode("\n- ", $issues), NEXUS_EXIT_VERIFY);
        }

        if ($lint) {
            $this->lintPhpFiles($this->root, ($state['mode'] ?? null) === 'disabled');
        }
    }

    private function stateIssues(array $state): array
    {
        $issues = [];
        if (($state['schema'] ?? null) !== 1) {
            $issues[] = 'Unsupported state schema.';
        }
        if (($state['package_id'] ?? null) !== $this->manifest['package_id']) {
            $issues[] = 'State package ID does not match this package.';
        }
        if (($state['root'] ?? null) !== $this->root) {
            $issues[] = 'State belongs to a different ITFlow root.';
        }

        $mode = $state['mode'] ?? null;
        if (!in_array($mode, ['enabled', 'disabled'], true)) {
            $issues[] = 'Recorded mode is invalid.';
            return $issues;
        }

        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            $metadata = $state['files'][$relative] ?? null;
            if (!is_array($metadata)) {
                $issues[] = "$relative: state metadata missing.";
                continue;
            }

            if ($mode === 'enabled') {
                if (!is_file($target)) {
                    $issues[] = "$relative: managed file is missing.";
                } elseif ($this->hashFile($target) !== $entry['payload_sha256']) {
                    $issues[] = "$relative: managed file changed after installation.";
                }
                continue;
            }

            if ($metadata['original_exists']) {
                if (!is_file($target)) {
                    $issues[] = "$relative: original file is missing while disabled.";
                } elseif ($this->hashFile($target) !== $metadata['original_sha256']) {
                    $issues[] = "$relative: original file changed while the theme was disabled.";
                }
            } elseif (file_exists($target)) {
                $issues[] = "$relative: theme-owned file should be absent while disabled.";
            }
        }
        return $issues;
    }

    /*
     * Classify every managed file against what it is supposed to be.
     *
     * ITFlow 26.09 changed how it updates itself: `php scripts/update_cli.php`
     * forces the file update and discards local edits to shipped files, and
     * Maintenance > Update hands that same job to cron. Nexus overlays shipped
     * files, so a routine ITFlow update silently reverts the whole overlay.
     *
     * A plain hash mismatch cannot tell that apart from an administrator editing
     * a managed file by hand, and the two want opposite responses: the first is
     * safe to re-apply unattended, the second must not be overwritten without
     * someone looking at it. So the comparison is three-way - payload, the
     * recorded ITFlow baseline, or neither.
     *
     * Returns one entry per drifted file; an empty array means no drift.
     */
    private function driftReport(array $state): array
    {
        if (($state['mode'] ?? null) !== 'enabled') {
            return [];
        }

        $drift = [];
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            $ownedByPackage = $entry['baseline_sha256'] === null;

            if (!is_file($target)) {
                /* A theme-owned file has no upstream counterpart, so ITFlow
                   cannot have rewritten it - only a wholesale file replace
                   removes one. Either way re-applying restores it safely. */
                $drift[] = [
                    'path' => $relative,
                    'kind' => $ownedByPackage ? 'reverted' : 'missing',
                    'detail' => $ownedByPackage
                        ? 'Theme-owned file was removed, which an ITFlow file update does.'
                        : 'Managed file is missing from the ITFlow tree.',
                ];
                continue;
            }

            $hash = $this->hashFile($target);
            if ($hash === $entry['payload_sha256']) {
                continue;
            }

            if (!$ownedByPackage && $hash === $entry['baseline_sha256']) {
                $drift[] = [
                    'path' => $relative,
                    'kind' => 'reverted',
                    'detail' => 'File matches the supported ITFlow baseline, so an ITFlow update replaced it.',
                ];
                continue;
            }

            $drift[] = [
                'path' => $relative,
                'kind' => 'modified',
                'detail' => 'File matches neither this package nor the supported ITFlow baseline.',
            ];
        }
        return $drift;
    }

    private function driftCounts(array $drift): array
    {
        $counts = ['reverted' => 0, 'modified' => 0, 'missing' => 0];
        foreach ($drift as $item) {
            $counts[$item['kind']] = ($counts[$item['kind']] ?? 0) + 1;
        }
        return $counts;
    }

    /*
     * ITFlow's own version string, for explaining a compatibility failure in the
     * terms an administrator can act on. Best effort: a tree without the file,
     * or with an unreadable one, simply yields null and the caller falls back to
     * the hash-level detail.
     */
    private function detectITFlowVersion(): ?string
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'app_version.php';
        if (!is_file($path) || is_link($path) || filesize($path) > 65536) {
            return null;
        }
        $source = @file_get_contents($path);
        if ($source === false) {
            return null;
        }
        return preg_match('/APP_VERSION"?\s*,\s*"([0-9][0-9A-Za-z.\-]{0,31})"/', $source, $match) === 1
            ? $match[1]
            : null;
    }

    /* The pinned release as it appears in an ITFlow version string: "26.09"
       matches 26.09, 26.09.1, 26.09.2 and so on, but not 26.08 or 26.10. */
    private function itflowVersionMatchesPin(?string $version): bool
    {
        if ($version === null) {
            return true;
        }
        $release = (string)$this->manifest['compatible_itflow']['release'];
        return $version === $release || str_starts_with($version, $release . '.');
    }

    private function assertBaselineCompatible(): void
    {
        $issues = [];
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            $baselineHash = $entry['baseline_sha256'];

            if ($baselineHash === null) {
                if (file_exists($target)) {
                    $issues[] = "$relative already exists but is owned by this package on a clean baseline.";
                }
                continue;
            }

            if (!is_file($target)) {
                $issues[] = "$relative is missing.";
            } elseif ($this->hashFile($target) !== $baselineHash) {
                $issues[] = "$relative does not match the supported ITFlow baseline.";
            }
        }

        if ($issues !== []) {
            /* Lead with the version when that is the actual reason. Sixteen
               "does not match the supported ITFlow baseline" lines tell an
               administrator nothing they can act on; "this is 26.08, the package
               is for 26.09" tells them exactly what to do. */
            $detected = $this->detectITFlowVersion();
            $preamble = 'Compatibility check failed. No files were changed:';
            if (!$this->itflowVersionMatchesPin($detected)) {
                $preamble = sprintf(
                    "This ITFlow installation is %s, but this package supports ITFlow %s.\n" .
                    "Update ITFlow to %s first, or install the Nexus release that targets %s.\n" .
                    'No files were changed. Underlying detail:',
                    $detected,
                    $this->manifest['compatible_itflow']['release'],
                    $this->manifest['compatible_itflow']['release'],
                    $detected
                );
            }
            throw new ThemeManagerException(
                $preamble . "\n- " . implode("\n- ", $issues),
                NEXUS_EXIT_CONFLICT
            );
        }
    }

    private function assertPayloadActive(): void
    {
        $issues = [];
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $target = $this->targetPath($relative);
            if (!is_file($target)) {
                $issues[] = "$relative is missing.";
            } elseif ($this->hashFile($target) !== $entry['payload_sha256']) {
                $issues[] = "$relative does not match this package's theme payload.";
            }
        }
        if ($issues !== []) {
            throw new ThemeManagerException(
                "Adoption check failed. No files were changed:\n- " . implode("\n- ", $issues),
                NEXUS_EXIT_CONFLICT
            );
        }
    }

    private function assertPackageIntegrity(): void
    {
        $issues = [];
        foreach ($this->manifest['files'] as $entry) {
            $relative = $entry['path'];
            $payload = $this->packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . $this->nativePath($relative);
            if (!is_file($payload) || $this->hashFile($payload) !== $entry['payload_sha256']) {
                $issues[] = "payload/$relative is missing or failed its checksum.";
            }

            if ($entry['baseline_sha256'] !== null) {
                $baseline = $this->packageRoot . DIRECTORY_SEPARATOR . 'baseline' . DIRECTORY_SEPARATOR . $this->nativePath($relative);
                if (!is_file($baseline) || $this->hashFile($baseline) !== $entry['baseline_sha256']) {
                    $issues[] = "baseline/$relative is missing or failed its checksum.";
                }
            }
        }

        if ($issues !== []) {
            throw new ThemeManagerException("Package integrity check failed:\n- " . implode("\n- ", $issues), NEXUS_EXIT_VERIFY);
        }
    }

    private function assertITFlowRoot(): void
    {
        foreach (['config.php', 'login.php', 'includes/header.php', 'client/includes/header.php'] as $required) {
            if (!is_file($this->targetPath($required))) {
                throw new ThemeManagerException("Not an initialized ITFlow root; missing $required.", NEXUS_EXIT_USAGE);
            }
        }
        if (!is_dir($this->root . DIRECTORY_SEPARATOR . 'css')) {
            throw new ThemeManagerException('Not an initialized ITFlow root; css directory is missing.', NEXUS_EXIT_USAGE);
        }
    }

    private function lintPhpFiles(string $base, bool $allowMissingOwned = false): void
    {
        foreach ($this->manifest['files'] as $entry) {
            if (!$entry['php']) {
                continue;
            }
            $path = $base . DIRECTORY_SEPARATOR . $this->nativePath($entry['path']);
            if (!is_file($path)) {
                if ($allowMissingOwned && $entry['baseline_sha256'] === null) {
                    continue;
                }
                throw new ThemeManagerException("Cannot lint missing PHP file: $path", NEXUS_EXIT_VERIFY);
            }
            [$exitCode, $output] = $this->runProcess([PHP_BINARY, '-l', $path]);
            if ($exitCode !== 0) {
                throw new ThemeManagerException("PHP lint failed for {$entry['path']}: $output", NEXUS_EXIT_VERIFY);
            }
        }
    }

    private function runProcess(array $command): array
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new ThemeManagerException('Could not start PHP lint process.', NEXUS_EXIT_VERIFY);
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return [$exitCode, trim($stdout . "\n" . $stderr)];
    }

    private function clearWebThemeControlState(): void
    {
        $marker = $this->root
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . '.nexus-theme-disabled';

        if (!file_exists($marker) && !is_link($marker)) {
            return;
        }
        if (is_dir($marker) && !is_link($marker)) {
            throw new ThemeManagerException('The Nexus web theme control marker is unexpectedly a directory.');
        }
        if (!@unlink($marker)) {
            throw new ThemeManagerException('Could not clear the Nexus web theme control state.');
        }
    }

    private function loadManifest(): array
    {
        $path = $this->packageRoot . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($path)) {
            throw new ThemeManagerException('manifest.json is missing.', NEXUS_EXIT_VERIFY);
        }
        $manifest = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        foreach (['schema', 'package_id', 'package_version', 'theme_version', 'compatible_itflow', 'files'] as $key) {
            if (!array_key_exists($key, $manifest)) {
                throw new ThemeManagerException("Manifest key is missing: $key", NEXUS_EXIT_VERIFY);
            }
        }
        if ($manifest['schema'] !== 1 || !is_array($manifest['files']) || count($manifest['files']) !== NEXUS_MANAGED_FILE_COUNT) {
            throw new ThemeManagerException('Manifest schema or file count is invalid.', NEXUS_EXIT_VERIFY);
        }

        $seen = [];
        foreach ($manifest['files'] as $entry) {
            if (!isset($entry['path'], $entry['payload_sha256'], $entry['php']) ||
                !array_key_exists('baseline_sha256', $entry) ||
                !preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#', $entry['path']) ||
                !preg_match('/^[a-f0-9]{64}$/', $entry['payload_sha256']) ||
                ($entry['baseline_sha256'] !== null && !preg_match('/^[a-f0-9]{64}$/', $entry['baseline_sha256']))) {
                throw new ThemeManagerException('Manifest contains an invalid file entry.', NEXUS_EXIT_VERIFY);
            }
            if (isset($seen[$entry['path']])) {
                throw new ThemeManagerException('Manifest contains a duplicate path: ' . $entry['path'], NEXUS_EXIT_VERIFY);
            }
            $seen[$entry['path']] = true;
        }
        return $manifest;
    }

    private function loadState(): array
    {
        if (!is_file($this->stateFile)) {
            throw new ThemeManagerException('Theme manager is not installed for this ITFlow root.', NEXUS_EXIT_CONFLICT);
        }
        try {
            return json_decode((string)file_get_contents($this->stateFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new ThemeManagerException('State file is unreadable or corrupt: ' . $error->getMessage(), NEXUS_EXIT_VERIFY);
        }
    }

    private function writeState(array $state): void
    {
        $this->ensureDirectory($this->stateDir, 0700);
        $temporary = tempnam($this->stateDir, '.state-');
        if ($temporary === false) {
            throw new ThemeManagerException('Could not allocate a temporary state file.');
        }
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            @unlink($temporary);
            throw new ThemeManagerException('Could not write manager state.');
        }
        @chmod($temporary, 0600);
        $this->replaceFile($temporary, $this->stateFile);
    }

    private function atomicCopy(string $source, string $destination, array $metadata): void
    {
        if (!is_file($source)) {
            throw new ThemeManagerException("Source file is missing: $source", NEXUS_EXIT_VERIFY);
        }
        $this->ensureDirectory(dirname($destination), 0755);
        $temporary = tempnam(dirname($destination), '.nexus-');
        if ($temporary === false) {
            throw new ThemeManagerException("Could not allocate a temporary file for $destination.");
        }
        if (!copy($source, $temporary)) {
            @unlink($temporary);
            throw new ThemeManagerException("Could not stage $destination.");
        }

        @chmod($temporary, (int)$metadata['mode']);
        if (function_exists('chown') && isset($metadata['uid']) && is_int($metadata['uid'])) {
            @chown($temporary, $metadata['uid']);
        }
        if (function_exists('chgrp') && isset($metadata['gid']) && is_int($metadata['gid'])) {
            @chgrp($temporary, $metadata['gid']);
        }
        $this->replaceFile($temporary, $destination);
    }

    private function replaceFile(string $temporary, string $destination): void
    {
        if (PHP_OS_FAMILY === 'Windows' && file_exists($destination) && !@unlink($destination)) {
            @unlink($temporary);
            throw new ThemeManagerException("Could not replace $destination.");
        }
        if (!@rename($temporary, $destination)) {
            @unlink($temporary);
            throw new ThemeManagerException("Could not activate $destination.");
        }
    }

    private function fileMetadata(string $path): array
    {
        $stat = stat($path);
        if ($stat === false) {
            throw new ThemeManagerException("Could not read metadata for $path.");
        }
        return [
            'mode' => $stat['mode'] & 0777,
            'uid' => $stat['uid'],
            'gid' => $stat['gid'],
        ];
    }

    private function newFileMetadata(string $target): array
    {
        $parentStat = stat(dirname($target));
        return [
            'mode' => 0644,
            'uid' => $parentStat === false ? null : $parentStat['uid'],
            'gid' => $parentStat === false ? null : $parentStat['gid'],
        ];
    }

    private function withLock(callable $operation): void
    {
        $this->ensureDirectory($this->stateRoot, 0700);
        $lockPath = $this->stateRoot . DIRECTORY_SEPARATOR . $this->instanceId . '.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new ThemeManagerException("Could not open lifecycle lock: $lockPath");
        }
        @chmod($lockPath, 0600);
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new ThemeManagerException('Another theme-manager operation is already running.', NEXUS_EXIT_CONFLICT);
        }
        try {
            $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function confirm(string $question, bool $yes): void
    {
        if ($yes) {
            return;
        }
        $interactive = function_exists('stream_isatty') && stream_isatty(STDIN);
        if (!$interactive) {
            throw new ThemeManagerException('Mutation requires --yes in a non-interactive session.', NEXUS_EXIT_USAGE);
        }
        fwrite(STDOUT, $question . ' [y/N] ');
        $answer = strtolower(trim((string)fgets(STDIN)));
        if (!in_array($answer, ['y', 'yes'], true)) {
            throw new ThemeManagerException('Operation cancelled.', NEXUS_EXIT_USAGE);
        }
    }

    private function ensureDirectory(string $path, int $mode): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, $mode, true) && !is_dir($path)) {
            throw new ThemeManagerException("Could not create directory: $path");
        }
        @chmod($path, $mode);
    }

    private function removeTree(string $path): void
    {
        $normalized = rtrim($path, DIRECTORY_SEPARATOR);
        $statePrefix = rtrim($this->stateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($normalized === '' || $normalized === $this->stateRoot || !str_starts_with($normalized, $statePrefix)) {
            throw new ThemeManagerException("Refusing to remove unsafe state path: $path");
        }
        if (!file_exists($normalized)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($normalized, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                if (!@rmdir($item->getPathname())) {
                    throw new ThemeManagerException('Could not remove state directory: ' . $item->getPathname());
                }
            } elseif (!@unlink($item->getPathname())) {
                throw new ThemeManagerException('Could not remove state file: ' . $item->getPathname());
            }
        }
        if (!@rmdir($normalized)) {
            throw new ThemeManagerException("Could not remove state directory: $normalized");
        }
    }

    private function targetPath(string $relative): string
    {
        if (!preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#', $relative)) {
            throw new ThemeManagerException("Unsafe relative path: $relative", NEXUS_EXIT_VERIFY);
        }
        return $this->root . DIRECTORY_SEPARATOR . $this->nativePath($relative);
    }

    private function nativePath(string $relative): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new ThemeManagerException("Could not hash file: $path", NEXUS_EXIT_VERIFY);
        }
        return $hash;
    }

    private function emit(array $result): void
    {
        if ($this->json) {
            fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            return;
        }
        fwrite(STDOUT, strtoupper((string)$result['status']) . ': ' . ($result['message'] ?? '') . "\n");
        foreach ($result as $key => $value) {
            if (in_array($key, ['status', 'message'], true) || $value === null || $value === [] || is_array($value)) {
                continue;
            }
            fwrite(STDOUT, str_replace('_', ' ', ucfirst($key)) . ': ' . (string)$value . "\n");
        }
        if (!empty($result['issues'])) {
            foreach ($result['issues'] as $issue) {
                fwrite(STDOUT, "- $issue\n");
            }
        }
    }
}

function nexusUsage(): void
{
    $usage = <<<'TEXT'
Nexus Theme Manager for IT Flow 4.1.0

Usage:
  php manager.php <command> --root /path/to/itflow [options]

Commands:
  doctor      Validate the package and target without changing files
  install     Back up originals, install the theme, and verify it
  adopt       Manage an existing exact manual installation without rewriting it
  status      Show installed mode, managed-file conflicts, and ITFlow-update drift
  reapply     Restore managed files an ITFlow update reverted
  verify      Verify package checksums, installed files, and PHP syntax
  disable     Restore original templates while retaining manager state
  enable      Reapply the managed theme after a disable
  uninstall   Restore originals and remove active manager state
  help        Show this help

Options:
  --root PATH        Required ITFlow document root
  --state-root PATH  Override state storage (default: /var/lib/nexus-itflow-theme)
  --yes              Approve a mutating command without an interactive prompt
  --purge            With uninstall, delete recovery state instead of archiving it
  --force            With reapply, overwrite managed files changed outside the manager
  --json             Emit machine-readable JSON

Exit codes: 0 success, 2 usage/cancelled, 3 conflict/incompatible, 4 verification, 5 operation failure.
TEXT;
    fwrite(STDOUT, $usage . "\n");
}

function nexusParseArguments(array $argv): array
{
    $command = $argv[1] ?? 'help';
    $options = [
        'root' => null,
        'state_root' => null,
        'yes' => false,
        'purge' => false,
        'force' => false,
        'json' => false,
    ];

    for ($index = 2, $count = count($argv); $index < $count; $index++) {
        $argument = $argv[$index];
        if ($argument === '--yes') {
            $options['yes'] = true;
        } elseif ($argument === '--purge') {
            $options['purge'] = true;
        } elseif ($argument === '--force') {
            $options['force'] = true;
        } elseif ($argument === '--json') {
            $options['json'] = true;
        } elseif (in_array($argument, ['--root', '--state-root'], true)) {
            if (!isset($argv[$index + 1])) {
                throw new ThemeManagerException("Missing value for $argument.", NEXUS_EXIT_USAGE);
            }
            $value = $argv[++$index];
            $options[$argument === '--root' ? 'root' : 'state_root'] = $value;
        } else {
            throw new ThemeManagerException("Unknown argument: $argument", NEXUS_EXIT_USAGE);
        }
    }
    return [$command, $options];
}

try {
    [$command, $options] = nexusParseArguments($argv);
    if (in_array($command, ['help', '--help', '-h'], true)) {
        nexusUsage();
        exit(NEXUS_EXIT_OK);
    }
    if ($options['root'] === null) {
        throw new ThemeManagerException('--root is required.', NEXUS_EXIT_USAGE);
    }

    $manager = new ThemeManager(__DIR__, $options['root'], $options['state_root'], $options['json']);
    match ($command) {
        'doctor' => $manager->doctor(),
        'install' => $manager->install($options['yes']),
        'adopt' => $manager->adopt($options['yes']),
        'status' => $manager->status(),
        'reapply' => $manager->reapply($options['yes'], $options['force']),
        'verify' => $manager->verify(),
        'disable' => $manager->disable($options['yes']),
        'enable' => $manager->enable($options['yes']),
        'uninstall' => $manager->uninstall($options['yes'], $options['purge']),
        default => throw new ThemeManagerException("Unknown command: $command", NEXUS_EXIT_USAGE),
    };
    exit(NEXUS_EXIT_OK);
} catch (ThemeManagerException $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit($error->exitCode);
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: Unexpected failure: ' . $error->getMessage() . "\n");
    exit(NEXUS_EXIT_OPERATION);
}

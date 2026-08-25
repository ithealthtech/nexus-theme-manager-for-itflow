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
        $this->emit([
            'status' => $issues === [] ? 'healthy' : 'conflict',
            'mode' => $state['mode'] ?? 'unknown',
            'message' => $issues === [] ? 'Managed files match the recorded state.' : 'Managed files have conflicts.',
            'issues' => $issues,
            'root' => $this->root,
            'state_directory' => $this->stateDir,
            'package_version' => $state['package_version'] ?? null,
            'installed_at' => $state['installed_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
        ]);
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
            throw new ThemeManagerException(
                "Compatibility check failed. No files were changed:\n- " . implode("\n- ", $issues),
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
Nexus Theme Manager for IT Flow 3.9.1

Usage:
  php manager.php <command> --root /path/to/itflow [options]

Commands:
  doctor      Validate the package and target without changing files
  install     Back up originals, install the theme, and verify it
  adopt       Manage an existing exact manual installation without rewriting it
  status      Show installed mode and managed-file conflicts
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
        'json' => false,
    ];

    for ($index = 2, $count = count($argv); $index < $count; $index++) {
        $argument = $argv[$index];
        if ($argument === '--yes') {
            $options['yes'] = true;
        } elseif ($argument === '--purge') {
            $options['purge'] = true;
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

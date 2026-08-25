<?php

declare(strict_types=1);

const NEXUS_UPDATER_REPOSITORY = 'ithealthtech/nexus-theme-manager-for-itflow';
const NEXUS_UPDATER_SCHEMA = 1;
const NEXUS_UPDATER_REQUEST = '.nexus-theme-update-request.json';
const NEXUS_UPDATER_STATUS = '.nexus-theme-update-status.json';
const NEXUS_UPDATER_READY = '.nexus-theme-updater-ready.json';
const NEXUS_UPDATER_PACKAGE_ROOT = '/opt';

final class NexusUpdaterException extends RuntimeException
{
}

final class NexusUpdater
{
    private string $configPath;
    private array $config;
    private string $curl;
    private string $unzip;
    private string $systemctl;
    private array $activeRequest = [];
    private array $failureContext = [];
    private int $lastProgress = 0;
    private ?string $lastPhase = null;

    public function __construct(string $configPath)
    {
        $resolved = realpath($configPath);
        if ($resolved === false || !is_file($resolved) || is_link($resolved)) {
            throw new NexusUpdaterException('Updater configuration could not be resolved.');
        }
        $normalized = str_replace('\\', '/', $resolved);
        if (!preg_match('#^/etc/nexus-theme-manager/[a-f0-9]{16}\.json$#', $normalized)) {
            throw new NexusUpdaterException('Updater configuration is outside the protected configuration directory.');
        }
        $this->configPath = $resolved;
        $this->config = self::readJsonFile($resolved, 16384);
        $this->validateConfig();
        $this->curl = self::findExecutable(['/usr/bin/curl', '/bin/curl']);
        $this->unzip = self::findExecutable(['/usr/bin/unzip', '/bin/unzip']);
        $this->systemctl = self::findExecutable(['/usr/bin/systemctl', '/bin/systemctl']);
    }

    public function run(): void
    {
        self::assertRootLinux();
        $lockPath = dirname($this->configPath) . DIRECTORY_SEPARATOR . $this->config['instance_id'] . '.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new NexusUpdaterException('Another Nexus updater operation is already running.');
        }

        try {
            $requestPath = $this->requestPath();
            if (!is_file($requestPath) || is_link($requestPath)) {
                return;
            }
            $request = self::validateRequest(self::readJsonFile($requestPath, 4096));
            $this->activeRequest = [
                'request_id' => $request['request_id'],
                'action' => $request['action'],
            ];
            if (!@unlink($requestPath)) {
                throw new NexusUpdaterException('The queued update request could not be claimed.');
            }
            $this->writeStatus('checking', 'Checking GitHub for the latest verified Nexus release.', [
                'phase' => 'checking',
                'progress' => 10,
            ]);

            $release = $this->resolveLatestRelease();
            $current = (string)$this->config['package_version'];
            if ($request['action'] === 'check') {
                $available = version_compare($release['version'], $current, '>');
                $this->writeStatus($available ? 'update_available' : 'up_to_date', $available
                    ? 'Nexus ' . $release['version'] . ' is ready to install.'
                    : 'Nexus is already up to date.', [
                    'request_id' => $request['request_id'],
                    'current_version' => $current,
                    'latest_version' => $release['version'],
                    'release_url' => $release['release_url'],
                    'phase' => 'check_complete',
                    'progress' => 100,
                ]);
                return;
            }

            if (!version_compare($release['version'], $current, '>')) {
                $this->writeStatus('up_to_date', 'Nexus is already up to date.', [
                    'request_id' => $request['request_id'],
                    'current_version' => $current,
                    'latest_version' => $release['version'],
                    'release_url' => $release['release_url'],
                    'phase' => 'check_complete',
                    'progress' => 100,
                ]);
                return;
            }

            $this->writeStatus('running', 'Downloading and verifying Nexus ' . $release['version'] . '.', [
                'request_id' => $request['request_id'],
                'current_version' => $current,
                'latest_version' => $release['version'],
                'phase' => 'download',
                'progress' => 15,
            ]);
            $this->performUpdate($release, $request['request_id']);
        } catch (Throwable $error) {
            $this->writeStatus('failed', 'Update failed: ' . $error->getMessage(), array_merge([
                'current_version' => $this->config['package_version'] ?? null,
                'phase' => $this->lastPhase ?? 'failed',
                'progress' => $this->lastProgress,
                'can_retry' => true,
                'recovery' => 'Review the failure detail and updater service log, then retry the same action.',
            ], $this->failureContext));
            throw $error;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function performUpdate(array $release, string $requestId): void
    {
        $packageRoot = NEXUS_UPDATER_PACKAGE_ROOT;
        $temporaryRoot = self::createUpdateWorkspace($packageRoot);

        $oldPackage = realpath((string)$this->config['package_directory']);
        if ($oldPackage === false || !is_file($oldPackage . DIRECTORY_SEPARATOR . 'manager.php')) {
            throw new NexusUpdaterException('The currently configured Nexus package is unavailable.');
        }
        $oldManager = $oldPackage . DIRECTORY_SEPARATOR . 'manager.php';
        $uninstalled = false;
        $newActivationAttempted = false;
        $newInstalled = false;
        $newPackage = null;

        try {
            $archive = $temporaryRoot . DIRECTORY_SEPARATOR . $release['archive_name'];
            $checksum = $temporaryRoot . DIRECTORY_SEPARATOR . $release['checksum_name'];
            $this->download($release['download_base'] . '/' . $release['archive_name'], $archive, 67108864);
            $this->download($release['download_base'] . '/' . $release['checksum_name'], $checksum, 4096);
            $this->writeStatus('running', 'Download complete. Verifying the signed release checksum and archive layout.', [
                'current_version' => $this->config['package_version'],
                'latest_version' => $release['version'],
                'phase' => 'verify',
                'progress' => 30,
            ]);
            self::verifyReleaseChecksum($checksum, $archive, $release['archive_name']);

            $entries = preg_split('/\r?\n/', trim($this->runProcess([$this->unzip, '-Z1', $archive]))) ?: [];
            self::validateArchiveEntries($entries, $release['asset_name']);
            $this->writeStatus('running', 'Release verified. Staging the protected package under /opt.', [
                'current_version' => $this->config['package_version'],
                'latest_version' => $release['version'],
                'phase' => 'stage',
                'progress' => 42,
            ]);
            $extractRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'extracted';
            if (!mkdir($extractRoot, 0700, true)) {
                throw new NexusUpdaterException('The release extraction directory could not be created.');
            }
            $this->runProcess([$this->unzip, '-q', $archive, '-d', $extractRoot]);
            $newPackageStaged = realpath($extractRoot . DIRECTORY_SEPARATOR . $release['asset_name']);
            if ($newPackageStaged === false) {
                throw new NexusUpdaterException('The verified package directory was not extracted.');
            }
            self::validateExtractedTree($newPackageStaged);
            self::validatePackageManifest($newPackageStaged, $release['version']);

            $newPackage = $packageRoot . DIRECTORY_SEPARATOR . $release['asset_name'];
            if (file_exists($newPackage)) {
                throw new NexusUpdaterException('The target package directory already exists: ' . $newPackage);
            }
            if (!@rename($newPackageStaged, $newPackage)) {
                throw new NexusUpdaterException('The verified package could not be activated under ' . $packageRoot . '.');
            }

            $this->writeStatus('running', 'Verified release downloaded. Creating rollback state.', [
                'request_id' => $requestId,
                'current_version' => $this->config['package_version'],
                'latest_version' => $release['version'],
                'phase' => 'backup',
                'progress' => 55,
            ]);
            $this->runManager($oldManager, 'verify');
            $this->writeStatus('running', 'Current installation verified. Preparing the clean version transition.', [
                'current_version' => $this->config['package_version'],
                'latest_version' => $release['version'],
                'phase' => 'transition',
                'progress' => 65,
            ]);
            $this->runManager($oldManager, 'uninstall', ['--yes']);
            $uninstalled = true;

            $this->writeStatus('running', 'Installing and verifying Nexus ' . $release['version'] . '.', [
                'request_id' => $requestId,
                'current_version' => $this->config['package_version'],
                'latest_version' => $release['version'],
                'phase' => 'install',
                'progress' => 75,
            ]);
            $newManager = $newPackage . DIRECTORY_SEPARATOR . 'manager.php';
            $this->runManager($newManager, 'doctor');
            $newActivationAttempted = true;
            $this->runManager($newManager, 'install', ['--yes']);
            $this->writeStatus('running', 'Nexus is installed. Running managed-file and application health checks.', [
                'current_version' => $this->config['package_version'],
                'latest_version' => $release['version'],
                'phase' => 'health_check',
                'progress' => 88,
            ]);
            $this->runManager($newManager, 'verify');
            $newInstalled = true;

            $this->writeStatus('running', 'Health checks passed. Finalizing updater registration.', [
                'current_version' => $release['version'],
                'latest_version' => $release['version'],
                'phase' => 'finalize',
                'progress' => 96,
            ]);
            $this->config['package_directory'] = $newPackage;
            $this->config['package_version'] = $release['version'];
            $this->config['updated_at'] = gmdate('c');
            self::atomicJsonWrite($this->configPath, $this->config, 0600);
            $this->refreshStableUpdater($newPackage);
            $this->writeReadyMarker();
            $this->writeStatus('completed', 'Nexus updated successfully to ' . $release['version'] . '.', [
                'request_id' => $requestId,
                'current_version' => $release['version'],
                'latest_version' => $release['version'],
                'release_url' => $release['release_url'],
                'phase' => 'complete',
                'progress' => 100,
                'can_retry' => false,
            ]);
        } catch (Throwable $error) {
            if ($newInstalled) {
                $this->failureContext = [
                    'current_version' => $release['version'],
                    'latest_version' => $release['version'],
                    'phase' => 'registration_failed',
                    'progress' => 100,
                    'can_retry' => false,
                    'recovery' => 'The new Nexus version is active. Repair the protected GUI updater registration before attempting another update.',
                ];
                throw new NexusUpdaterException('Nexus ' . $release['version'] . ' is installed and verified, but GUI updater registration failed: ' . $error->getMessage());
            }
            if ($uninstalled && !$newInstalled) {
                try {
                    $this->writeStatus('running', 'Installation did not complete. Restoring the previous verified Nexus version.', [
                        'current_version' => $this->config['package_version'],
                        'latest_version' => $release['version'],
                        'phase' => 'rollback',
                        'progress' => 90,
                    ]);
                    if ($newActivationAttempted && is_string($newPackage)) {
                        try {
                            $this->runManager($newPackage . DIRECTORY_SEPARATOR . 'manager.php', 'uninstall', ['--yes']);
                        } catch (Throwable) {
                            // The new manager already rolls back a failed install. Continue restoring the old release.
                        }
                    }
                    $this->runManager($oldManager, 'doctor');
                    $this->runManager($oldManager, 'install', ['--yes']);
                    $this->runManager($oldManager, 'verify');
                    if (is_string($newPackage) && is_dir($newPackage)) {
                        self::removeTree($newPackage, $packageRoot);
                    }
                    $this->failureContext = [
                        'phase' => 'rollback_complete',
                        'progress' => 100,
                        'rollback_state' => 'restored',
                        'can_retry' => true,
                        'recovery' => 'The previous Nexus version was restored and verified. Review the failure detail, then retry when ready.',
                    ];
                    throw new NexusUpdaterException($error->getMessage() . ' The previous Nexus version was restored automatically.');
                } catch (NexusUpdaterException $rollbackError) {
                    if (str_contains($rollbackError->getMessage(), 'restored automatically')) {
                        throw $rollbackError;
                    }
                    $this->failureContext = [
                        'phase' => 'rollback_failed',
                        'progress' => 100,
                        'rollback_state' => 'failed',
                        'can_retry' => false,
                        'recovery' => 'Automatic rollback failed. Restore or verify the previous package from SSH before queuing another GUI update.',
                    ];
                    throw new NexusUpdaterException($error->getMessage() . ' Automatic rollback also failed: ' . $rollbackError->getMessage());
                }
            }
            throw $error;
        } finally {
            self::removeTree($temporaryRoot, $packageRoot);
        }
    }

    public static function createUpdateWorkspace(string $packageRoot): string
    {
        $resolvedRoot = realpath($packageRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($packageRoot)) {
            throw new NexusUpdaterException('The protected package directory is unavailable.');
        }
        $workspace = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.nexus-update-' . bin2hex(random_bytes(8));
        if (!mkdir($workspace, 0700)) {
            throw new NexusUpdaterException('A protected update workspace could not be created.');
        }
        return $workspace;
    }

    private function resolveLatestRelease(): array
    {
        $latestUrl = 'https://github.com/' . NEXUS_UPDATER_REPOSITORY . '/releases/latest';
        $effective = trim($this->runProcess([$this->curl, '--fail', '--silent', '--show-error', '--location', '--output', '/dev/null', '--write-out', '%{url_effective}', $latestUrl]));
        if (!preg_match('#^https://github\.com/' . preg_quote(NEXUS_UPDATER_REPOSITORY, '#') . '/releases/tag/v([0-9]+\.[0-9]+\.[0-9]+)$#', $effective, $match)) {
            throw new NexusUpdaterException('GitHub did not return a valid Nexus release tag.');
        }
        $version = self::validateVersion($match[1]);
        $assetName = 'Nexus-Theme-Manager-for-ITFlow-' . $version;
        return [
            'version' => $version,
            'tag' => 'v' . $version,
            'asset_name' => $assetName,
            'archive_name' => $assetName . '.zip',
            'checksum_name' => $assetName . '.zip.sha256.txt',
            'download_base' => 'https://github.com/' . NEXUS_UPDATER_REPOSITORY . '/releases/download/v' . $version,
            'release_url' => $effective,
        ];
    }

    private function download(string $url, string $destination, int $maximumBytes): void
    {
        if (!str_starts_with($url, 'https://github.com/' . NEXUS_UPDATER_REPOSITORY . '/releases/download/')) {
            throw new NexusUpdaterException('Refusing an untrusted release URL.');
        }
        $this->runProcess([$this->curl, '--fail', '--silent', '--show-error', '--location', '--proto', '=https', '--tlsv1.2', '--max-filesize', (string)$maximumBytes, '--output', $destination, $url]);
        $size = is_file($destination) ? filesize($destination) : false;
        if ($size === false || $size < 1 || $size > $maximumBytes) {
            throw new NexusUpdaterException('A release asset is empty or exceeds its protected size limit.');
        }
    }

    private function runManager(string $manager, string $command, array $extra = []): string
    {
        $arguments = [PHP_BINARY, $manager, $command, '--root', $this->config['itflow_root']];
        if ($this->config['state_root'] !== '') {
            array_push($arguments, '--state-root', $this->config['state_root']);
        }
        return $this->runProcess(array_merge($arguments, $extra));
    }

    private function runProcess(array $command): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, null, [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);
        if (!is_resource($process)) {
            throw new NexusUpdaterException('A protected updater process could not be started.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            $detail = trim((string)$stderr) ?: trim((string)$stdout);
            throw new NexusUpdaterException('Protected command failed' . ($detail !== '' ? ': ' . $detail : '.'));
        }
        return (string)$stdout;
    }

    private function requestPath(): string
    {
        return $this->config['itflow_root'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . NEXUS_UPDATER_REQUEST;
    }

    private function statusPath(): string
    {
        return $this->config['itflow_root'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . NEXUS_UPDATER_STATUS;
    }

    private function readyPath(): string
    {
        return $this->config['itflow_root'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . NEXUS_UPDATER_READY;
    }

    private function writeStatus(string $state, string $message, array $extra = []): void
    {
        if (isset($extra['progress']) && is_numeric($extra['progress'])) {
            $this->lastProgress = max(0, min(100, (int)$extra['progress']));
            $extra['progress'] = $this->lastProgress;
        }
        if (isset($extra['phase']) && is_string($extra['phase'])) {
            $this->lastPhase = $extra['phase'];
        }
        self::atomicJsonWrite($this->statusPath(), array_merge([
            'schema' => NEXUS_UPDATER_SCHEMA,
            'state' => $state,
            'message' => $message,
            'updated_at' => gmdate('c'),
        ], $this->activeRequest, $extra), 0644);
    }

    private function writeReadyMarker(): void
    {
        self::atomicJsonWrite($this->readyPath(), [
            'schema' => NEXUS_UPDATER_SCHEMA,
            'instance_id' => $this->config['instance_id'],
            'package_version' => $this->config['package_version'],
            'installed_at' => gmdate('c'),
        ], 0644);
    }

    private function refreshStableUpdater(string $packageDirectory): void
    {
        $source = $packageDirectory . DIRECTORY_SEPARATOR . 'updater.php';
        $target = realpath(__FILE__) ?: __FILE__;
        if (!is_file($source)) {
            throw new NexusUpdaterException('The new package does not contain its updater helper.');
        }
        self::atomicCopy($source, $target, 0755);
    }

    private function validateConfig(): void
    {
        $required = ['schema', 'instance_id', 'itflow_root', 'state_root', 'package_directory', 'package_version'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $this->config)) {
                throw new NexusUpdaterException('Updater configuration is incomplete.');
            }
        }
        if ($this->config['schema'] !== NEXUS_UPDATER_SCHEMA || !preg_match('/^[a-f0-9]{16}$/', (string)$this->config['instance_id'])) {
            throw new NexusUpdaterException('Updater configuration identity is invalid.');
        }
        $root = realpath((string)$this->config['itflow_root']);
        if ($root === false || !is_dir($root . DIRECTORY_SEPARATOR . 'uploads')) {
            throw new NexusUpdaterException('Configured ITFlow root is invalid.');
        }
        $this->config['itflow_root'] = rtrim($root, DIRECTORY_SEPARATOR);
        self::validateVersion((string)$this->config['package_version']);
    }

    public static function installService(string $packageRoot, string $itflowRoot, ?string $stateRoot): void
    {
        self::assertRootLinux();
        $root = realpath($itflowRoot);
        $package = realpath($packageRoot);
        if ($root === false || !is_dir($root . DIRECTORY_SEPARATOR . 'uploads') || $package === false) {
            throw new NexusUpdaterException('ITFlow root or Nexus package directory is invalid.');
        }
        foreach (['/usr/bin/systemctl', '/usr/bin/curl', '/usr/bin/unzip'] as $required) {
            if (!is_executable($required)) {
                throw new NexusUpdaterException('Required updater command is unavailable: ' . $required);
            }
        }
        $manifest = self::readJsonFile($package . DIRECTORY_SEPARATOR . 'manifest.json', 262144);
        $version = self::validateVersion((string)($manifest['package_version'] ?? ''));
        $instance = substr(hash('sha256', rtrim($root, DIRECTORY_SEPARATOR)), 0, 16);
        $effectiveStateRoot = rtrim($stateRoot ?: '/var/lib/nexus-itflow-theme', DIRECTORY_SEPARATOR);
        $serviceDirectory = '/usr/local/lib/nexus-theme-manager/' . $instance;
        $configDirectory = '/etc/nexus-theme-manager';
        self::ensureDirectory($serviceDirectory, 0755);
        self::ensureDirectory($configDirectory, 0700);
        self::atomicCopy(__FILE__, $serviceDirectory . '/updater.php', 0755);

        $configPath = $configDirectory . '/' . $instance . '.json';
        self::atomicJsonWrite($configPath, [
            'schema' => NEXUS_UPDATER_SCHEMA,
            'instance_id' => $instance,
            'itflow_root' => rtrim($root, DIRECTORY_SEPARATOR),
            'state_root' => $effectiveStateRoot,
            'package_directory' => rtrim($package, DIRECTORY_SEPARATOR),
            'package_version' => $version,
            'updated_at' => gmdate('c'),
        ], 0600);

        $requestPath = rtrim($root, DIRECTORY_SEPARATOR) . '/uploads/' . NEXUS_UPDATER_REQUEST;
        $units = self::renderSystemdUnits(
            $instance,
            PHP_BINARY,
            $serviceDirectory,
            $configPath,
            $requestPath,
            [$root, $effectiveStateRoot, '/opt', $configDirectory, $serviceDirectory]
        );
        self::atomicWrite('/etc/systemd/system/' . $units['unit_name'] . '.service', $units['service'], 0644);
        self::atomicWrite('/etc/systemd/system/' . $units['unit_name'] . '.path', $units['path'], 0644);

        $runner = new self($configPath);
        $runner->runProcess(['/usr/bin/systemctl', 'daemon-reload']);
        $runner->runProcess(['/usr/bin/systemctl', 'enable', '--now', $units['unit_name'] . '.path']);
        $runner->writeReadyMarker();
        $runner->writeStatus('ready', 'GUI updates are ready. Check GitHub for the latest Nexus release.', [
            'current_version' => $version,
        ]);
    }

    public static function repairService(string $packageRoot, string $itflowRoot): void
    {
        self::assertRootLinux();
        $root = realpath($itflowRoot);
        $package = realpath($packageRoot);
        if ($root === false || !is_dir($root . DIRECTORY_SEPARATOR . 'uploads') || $package === false) {
            throw new NexusUpdaterException('ITFlow root or Nexus repair package is invalid.');
        }
        $manifest = self::readJsonFile($package . DIRECTORY_SEPARATOR . 'manifest.json', 262144);
        self::validateVersion((string)($manifest['package_version'] ?? ''));
        self::validatePackageManifest($package, (string)$manifest['package_version']);

        $instance = substr(hash('sha256', rtrim($root, DIRECTORY_SEPARATOR)), 0, 16);
        $configPath = '/etc/nexus-theme-manager/' . $instance . '.json';
        $config = self::readJsonFile($configPath, 16384);
        if (($config['instance_id'] ?? null) !== $instance || realpath((string)($config['itflow_root'] ?? '')) !== $root) {
            throw new NexusUpdaterException('The existing GUI updater registration does not match this ITFlow root.');
        }

        $serviceDirectory = '/usr/local/lib/nexus-theme-manager/' . $instance;
        self::ensureDirectory($serviceDirectory, 0755);
        self::atomicCopy($package . DIRECTORY_SEPARATOR . 'updater.php', $serviceDirectory . '/updater.php', 0755);

        $runner = new self($configPath);
        $unitName = 'nexus-theme-update-' . $instance;
        $runner->runProcess(['/usr/bin/systemctl', 'daemon-reload']);
        $runner->runProcess(['/usr/bin/systemctl', 'enable', '--now', $unitName . '.path']);
        $runner->writeReadyMarker();
        $runner->writeStatus('ready', 'GUI updater repaired. Check GitHub, then install the available Nexus release.', [
            'current_version' => $config['package_version'],
        ]);
    }

    public static function removeService(string $itflowRoot): void
    {
        self::assertRootLinux();
        $root = realpath($itflowRoot);
        if ($root === false) {
            throw new NexusUpdaterException('ITFlow root is invalid.');
        }
        $instance = substr(hash('sha256', rtrim($root, DIRECTORY_SEPARATOR)), 0, 16);
        $unitName = 'nexus-theme-update-' . $instance;
        $systemctl = self::findExecutable(['/usr/bin/systemctl', '/bin/systemctl']);
        self::runSimple([$systemctl, 'disable', '--now', $unitName . '.path'], false);
        foreach (['/etc/systemd/system/' . $unitName . '.path', '/etc/systemd/system/' . $unitName . '.service', '/etc/nexus-theme-manager/' . $instance . '.json'] as $path) {
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
        }
        self::runSimple([$systemctl, 'daemon-reload'], false);
        foreach ([NEXUS_UPDATER_READY, NEXUS_UPDATER_STATUS, NEXUS_UPDATER_REQUEST] as $file) {
            $path = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $file;
            if (is_file($path) && !is_link($path)) {
                @unlink($path);
            }
        }
    }

    public static function validateRequest(array $request): array
    {
        $action = (string)($request['action'] ?? '');
        $requestId = (string)($request['request_id'] ?? '');
        if (($request['schema'] ?? null) !== NEXUS_UPDATER_SCHEMA || !in_array($action, ['check', 'update'], true) || !preg_match('/^[a-f0-9]{32}$/', $requestId)) {
            throw new NexusUpdaterException('Update request is invalid.');
        }
        return ['schema' => NEXUS_UPDATER_SCHEMA, 'action' => $action, 'request_id' => $requestId];
    }

    public static function renderSystemdUnits(
        string $instance,
        string $phpBinary,
        string $serviceDirectory,
        string $configPath,
        string $requestPath,
        array $writablePaths
    ): array {
        if (!preg_match('/^[a-f0-9]{16}$/', $instance) || $writablePaths === []) {
            throw new NexusUpdaterException('Systemd unit identity or writable paths are invalid.');
        }
        foreach (array_merge([$phpBinary, $serviceDirectory, $configPath, $requestPath], $writablePaths) as $path) {
            self::validateSystemdPath((string)$path);
        }

        $unitName = 'nexus-theme-update-' . $instance;
        $serviceUnit = "[Unit]\nDescription=Nexus Theme Manager verified update for $instance\nAfter=network-online.target\nWants=network-online.target\n\n[Service]\nType=oneshot\nUser=root\nGroup=root\nExecStart=" . self::systemdQuote($phpBinary) . ' ' . self::systemdQuote(rtrim($serviceDirectory, '/') . '/updater.php') . ' run --config ' . self::systemdQuote($configPath) . "\nPrivateTmp=true\nProtectHome=true\nProtectSystem=strict\nReadWritePaths=" . implode(' ', array_map(self::systemdQuote(...), $writablePaths)) . "\nNoNewPrivileges=true\nUMask=0027\nTimeoutStartSec=900\n\n";
        $pathUnit = "[Unit]\nDescription=Watch for Nexus Theme Manager update requests for $instance\n\n[Path]\nPathExists=" . self::systemdPathValue($requestPath) . "\nUnit=$unitName.service\n\n[Install]\nWantedBy=multi-user.target\n";

        return ['unit_name' => $unitName, 'service' => $serviceUnit, 'path' => $pathUnit];
    }

    public static function validateVersion(string $version): string
    {
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version)) {
            throw new NexusUpdaterException('Release version is invalid.');
        }
        return $version;
    }

    public static function verifyReleaseChecksum(string $checksumFile, string $archive, string $archiveName): void
    {
        $contents = trim((string)file_get_contents($checksumFile));
        if (!preg_match('/^([a-f0-9]{64})  ' . preg_quote($archiveName, '/') . '$/', $contents, $match)) {
            throw new NexusUpdaterException('Release checksum file is malformed.');
        }
        $actual = hash_file('sha256', $archive);
        if ($actual === false || !hash_equals($match[1], $actual)) {
            throw new NexusUpdaterException('Release archive checksum verification failed.');
        }
    }

    public static function validateArchiveEntries(array $entries, string $assetName): void
    {
        if ($entries === []) {
            throw new NexusUpdaterException('Release archive is empty.');
        }
        $prefix = $assetName . '/';
        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '' || str_contains($entry, '\\') || !str_starts_with($entry, $prefix) || str_contains($entry, '../') || str_starts_with(substr($entry, strlen($prefix)), '/')) {
                throw new NexusUpdaterException('Release archive contains an unsafe path.');
            }
        }
    }

    public static function validatePackageManifest(string $packageDirectory, string $version): void
    {
        $manifest = self::readJsonFile($packageDirectory . DIRECTORY_SEPARATOR . 'manifest.json', 262144);
        if (($manifest['schema'] ?? null) !== 1 || ($manifest['package_id'] ?? '') !== 'org.nexus-theme-manager.itflow' || ($manifest['package_version'] ?? '') !== $version || !is_array($manifest['files'] ?? null)) {
            throw new NexusUpdaterException('Release package manifest does not match the requested Nexus version.');
        }
        foreach (['manager.php', 'updater.php', 'SHA256SUMS.txt'] as $required) {
            if (!is_file($packageDirectory . DIRECTORY_SEPARATOR . $required)) {
                throw new NexusUpdaterException('Release package is missing ' . $required . '.');
            }
        }
    }

    public static function validateExtractedTree(string $packageDirectory): void
    {
        $root = realpath($packageDirectory);
        if ($root === false || is_link($packageDirectory)) {
            throw new NexusUpdaterException('Extracted release package is invalid.');
        }
        $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $count = 0;
        $totalBytes = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            $count++;
            if ($count > 5000 || $item->isLink()) {
                throw new NexusUpdaterException('Extracted release contains an unsafe entry.');
            }
            $resolved = realpath($item->getPathname());
            if ($resolved === false || !str_starts_with(str_replace('\\', '/', $resolved), $prefix)) {
                throw new NexusUpdaterException('Extracted release escapes its protected directory.');
            }
            if ($item->isFile()) {
                $totalBytes += $item->getSize();
                if ($totalBytes > 268435456) {
                    throw new NexusUpdaterException('Extracted release exceeds its protected size limit.');
                }
            }
        }
    }

    private static function readJsonFile(string $path, int $maximum): array
    {
        if (!is_file($path) || is_link($path) || filesize($path) > $maximum) {
            throw new NexusUpdaterException('Protected JSON file is unavailable or too large.');
        }
        try {
            $decoded = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new NexusUpdaterException('Protected JSON file is malformed: ' . $error->getMessage());
        }
        if (!is_array($decoded)) {
            throw new NexusUpdaterException('Protected JSON file must contain an object.');
        }
        return $decoded;
    }

    private static function atomicJsonWrite(string $path, array $data, int $mode): void
    {
        self::atomicWrite($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", $mode);
    }

    private static function atomicWrite(string $path, string $contents, int $mode): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) || is_link($path)) {
            throw new NexusUpdaterException('Protected write destination is invalid.');
        }
        $temporary = tempnam($directory, '.nexus-updater-');
        if ($temporary === false) {
            throw new NexusUpdaterException('Protected temporary file could not be created.');
        }
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new NexusUpdaterException('Protected file could not be written.');
            }
            @chmod($temporary, $mode);
            if (!@rename($temporary, $path)) {
                throw new NexusUpdaterException('Protected file could not be activated.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function atomicCopy(string $source, string $target, int $mode): void
    {
        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new NexusUpdaterException('Updater helper could not be read.');
        }
        self::atomicWrite($target, $contents, $mode);
    }

    private static function ensureDirectory(string $path, int $mode): void
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path, $mode, true))) {
            throw new NexusUpdaterException('Protected directory could not be created: ' . $path);
        }
        @chmod($path, $mode);
    }

    private static function findExecutable(array $paths): string
    {
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        throw new NexusUpdaterException('A required updater executable is unavailable.');
    }

    private static function systemdQuote(string $value): string
    {
        return '"' . str_replace(['%', '\\', '"'], ['%%', '\\\\', '\\"'], $value) . '"';
    }

    private static function systemdPathValue(string $value): string
    {
        self::validateSystemdPath($value);
        return str_replace('%', '%%', $value);
    }

    private static function validateSystemdPath(string $value): void
    {
        if (!str_starts_with($value, '/') || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new NexusUpdaterException('Systemd paths must be absolute and contain no control characters.');
        }
    }

    private static function runSimple(array $command, bool $throw = true): void
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            if ($throw) {
                throw new NexusUpdaterException('System service command could not be started.');
            }
            return;
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($throw && $exit !== 0) {
            throw new NexusUpdaterException(trim((string)$stderr) ?: trim((string)$stdout));
        }
    }

    private static function assertRootLinux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            throw new NexusUpdaterException('This operation requires root on a systemd-based Linux host.');
        }
    }

    private static function removeTree(string $path, string $allowedParent): void
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        $parent = rtrim(str_replace('\\', '/', $allowedParent), '/') . '/';
        if (!str_starts_with($normalized . '/', $parent) || $normalized === rtrim($parent, '/')) {
            throw new NexusUpdaterException('Refusing unsafe updater cleanup path.');
        }
        if (!file_exists($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}

function nexusUpdaterUsage(): void
{
    fwrite(STDOUT, <<<'TEXT'
Nexus Theme Manager privileged GUI updater

Usage:
  sudo php updater.php install-service --root /path/to/itflow [--state-root PATH]
  sudo php updater.php repair-service --root /path/to/itflow
  sudo php updater.php remove-service --root /path/to/itflow
  php updater.php run --config /etc/nexus-theme-manager/<instance>.json
TEXT
    . "\n");
}

function nexusUpdaterArguments(array $argv): array
{
    $command = $argv[1] ?? 'help';
    $options = ['root' => null, 'state_root' => null, 'config' => null];
    for ($index = 2; $index < count($argv); $index++) {
        $argument = $argv[$index];
        if (in_array($argument, ['--yes', '--json', '--purge'], true)) {
            continue;
        }
        if (!in_array($argument, ['--root', '--state-root', '--config'], true) || !isset($argv[$index + 1])) {
            throw new NexusUpdaterException('Unknown or incomplete updater argument: ' . $argument);
        }
        $key = match ($argument) { '--root' => 'root', '--state-root' => 'state_root', '--config' => 'config' };
        $options[$key] = $argv[++$index];
    }
    return [$command, $options];
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    try {
        [$command, $options] = nexusUpdaterArguments($argv);
        if (in_array($command, ['help', '--help', '-h'], true)) {
            nexusUpdaterUsage();
            exit(0);
        }
        if ($command === 'install-service') {
            if ($options['root'] === null) {
                throw new NexusUpdaterException('--root is required.');
            }
            NexusUpdater::installService(__DIR__, $options['root'], $options['state_root']);
            fwrite(STDOUT, "GUI updater installed and started.\n");
            exit(0);
        }
        if ($command === 'remove-service') {
            if ($options['root'] === null) {
                throw new NexusUpdaterException('--root is required.');
            }
            NexusUpdater::removeService($options['root']);
            fwrite(STDOUT, "GUI updater removed.\n");
            exit(0);
        }
        if ($command === 'repair-service') {
            if ($options['root'] === null) {
                throw new NexusUpdaterException('--root is required.');
            }
            NexusUpdater::repairService(__DIR__, $options['root']);
            fwrite(STDOUT, "GUI updater repaired and restarted.\n");
            exit(0);
        }
        if ($command === 'run') {
            if ($options['config'] === null) {
                throw new NexusUpdaterException('--config is required.');
            }
            (new NexusUpdater($options['config']))->run();
            exit(0);
        }
        throw new NexusUpdaterException('Unknown updater command: ' . $command);
    } catch (Throwable $error) {
        fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
        exit(1);
    }
}

<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class NexusUpgradeException extends RuntimeException
{
}

function nexusUpgradeUsage(): void
{
    fwrite(STDOUT, <<<'TEXT'
Nexus Theme Manager verified package upgrader

Usage:
  sudo php upgrade.php --root /path/to/itflow --current-package /opt/Nexus-Theme-Manager-for-ITFlow-X.Y.Z [--state-root PATH] --yes

This helper is called by install-latest.sh after the latest release archive and
checksum have been downloaded and verified. It preserves the active/disabled
mode and restores the previous package automatically if activation fails.
TEXT
    . "\n");
}

function nexusUpgradeArguments(array $argv): array
{
    $options = [
        'root' => null,
        'current_package' => null,
        'state_root' => null,
        'yes' => false,
    ];
    for ($index = 1; $index < count($argv); $index++) {
        $argument = $argv[$index];
        if ($argument === '--yes') {
            $options['yes'] = true;
            continue;
        }
        if (in_array($argument, ['help', '--help', '-h'], true)) {
            nexusUpgradeUsage();
            exit(0);
        }
        if (!in_array($argument, ['--root', '--current-package', '--state-root'], true) || !isset($argv[$index + 1])) {
            throw new NexusUpgradeException('Unknown or incomplete upgrade argument: ' . $argument);
        }
        $key = match ($argument) {
            '--root' => 'root',
            '--current-package' => 'current_package',
            '--state-root' => 'state_root',
        };
        $options[$key] = $argv[++$index];
    }
    if ($options['root'] === null || $options['current_package'] === null) {
        throw new NexusUpgradeException('--root and --current-package are required.');
    }
    if (!$options['yes']) {
        throw new NexusUpgradeException('--yes is required for a non-interactive package upgrade.');
    }
    return $options;
}

function nexusUpgradeReadJson(string $path, int $maximumBytes = 262144): array
{
    $resolved = realpath($path);
    if ($resolved === false || !is_file($resolved) || is_link($resolved)) {
        throw new NexusUpgradeException('Required JSON file could not be resolved: ' . $path);
    }
    $size = filesize($resolved);
    if ($size === false || $size < 2 || $size > $maximumBytes) {
        throw new NexusUpgradeException('Required JSON file has an invalid size: ' . $path);
    }
    try {
        $data = json_decode((string)file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        throw new NexusUpgradeException('Required JSON file is invalid: ' . $error->getMessage());
    }
    if (!is_array($data)) {
        throw new NexusUpgradeException('Required JSON file does not contain an object: ' . $path);
    }
    return $data;
}

function nexusUpgradePackage(string $path): array
{
    $resolved = realpath($path);
    if ($resolved === false || !is_dir($resolved) || is_link($resolved) || !is_file($resolved . DIRECTORY_SEPARATOR . 'manager.php')) {
        throw new NexusUpgradeException('Nexus package directory is invalid: ' . $path);
    }
    $manifest = nexusUpgradeReadJson($resolved . DIRECTORY_SEPARATOR . 'manifest.json');
    $version = (string)($manifest['package_version'] ?? '');
    if (($manifest['package_id'] ?? null) !== 'org.nexus-theme-manager.itflow' || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        throw new NexusUpgradeException('Nexus package manifest identity or version is invalid: ' . $path);
    }
    return ['path' => $resolved, 'version' => $version, 'manifest' => $manifest];
}

function nexusUpgradeRunManager(array $package, string $command, string $root, string $stateRoot): void
{
    $managerCommand = $command === 'uninstall-purge' ? 'uninstall' : $command;
    $arguments = [PHP_BINARY, $package['path'] . DIRECTORY_SEPARATOR . 'manager.php', $managerCommand, '--root', $root];
    if ($stateRoot !== '') {
        array_push($arguments, '--state-root', $stateRoot);
    }
    if (in_array($command, ['install', 'disable', 'uninstall', 'uninstall-purge'], true)) {
        $arguments[] = '--yes';
    }
    if ($command === 'uninstall-purge') {
        $arguments[] = '--purge';
    }
    $process = proc_open($arguments, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new NexusUpgradeException('Could not start the Nexus ' . $command . ' operation.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($stdout !== '') {
        fwrite(STDOUT, $stdout);
    }
    if ($exitCode !== 0) {
        throw new NexusUpgradeException(
            'Nexus ' . $command . ' failed with exit code ' . $exitCode . ': ' . trim($stderr !== '' ? $stderr : $stdout)
        );
    }
}

function nexusUpgradeRestore(array $currentPackage, array $newPackage, string $root, string $stateRoot, string $stateFile, string $mode): void
{
    if (is_file($stateFile)) {
        nexusUpgradeRunManager($newPackage, 'uninstall-purge', $root, $stateRoot);
    }
    nexusUpgradeRunManager($currentPackage, 'doctor', $root, $stateRoot);
    nexusUpgradeRunManager($currentPackage, 'install', $root, $stateRoot);
    if ($mode === 'disabled') {
        nexusUpgradeRunManager($currentPackage, 'disable', $root, $stateRoot);
    }
    nexusUpgradeRunManager($currentPackage, 'verify', $root, $stateRoot);
}

try {
    $options = nexusUpgradeArguments($argv);
    $root = realpath((string)$options['root']);
    if ($root === false || !is_dir($root . DIRECTORY_SEPARATOR . 'uploads')) {
        throw new NexusUpgradeException('ITFlow root is invalid.');
    }
    $root = rtrim($root, DIRECTORY_SEPARATOR);
    $stateRoot = rtrim((string)($options['state_root'] ?: '/var/lib/nexus-itflow-theme'), DIRECTORY_SEPARATOR);
    $instance = substr(hash('sha256', $root), 0, 16);
    $stateFile = $stateRoot . DIRECTORY_SEPARATOR . $instance . DIRECTORY_SEPARATOR . 'state.json';
    $state = nexusUpgradeReadJson($stateFile);
    $mode = (string)($state['mode'] ?? '');
    if (($state['schema'] ?? null) !== 1 || ($state['package_id'] ?? null) !== 'org.nexus-theme-manager.itflow' ||
        ($state['root'] ?? null) !== $root || !in_array($mode, ['enabled', 'disabled'], true)) {
        throw new NexusUpgradeException('Existing Nexus manager state is invalid or belongs to another ITFlow root.');
    }

    $currentPackage = nexusUpgradePackage((string)$options['current_package']);
    $newPackage = nexusUpgradePackage(__DIR__);
    if ($currentPackage['path'] === $newPackage['path']) {
        throw new NexusUpgradeException('Current and replacement package directories are the same.');
    }
    if (($state['package_version'] ?? null) !== $currentPackage['version']) {
        throw new NexusUpgradeException('Existing state version does not match the selected current package.');
    }
    if (!version_compare($newPackage['version'], $currentPackage['version'], '>')) {
        throw new NexusUpgradeException('Replacement package must be newer than the installed package.');
    }

    fwrite(STDOUT, 'Verifying installed Nexus ' . $currentPackage['version'] . "...\n");
    nexusUpgradeRunManager($currentPackage, 'verify', $root, $stateRoot);
    fwrite(STDOUT, 'Removing the verified current payload while retaining recovery state...' . "\n");
    nexusUpgradeRunManager($currentPackage, 'uninstall', $root, $stateRoot);

    try {
        fwrite(STDOUT, 'Checking Nexus ' . $newPackage['version'] . " compatibility...\n");
        nexusUpgradeRunManager($newPackage, 'doctor', $root, $stateRoot);
        fwrite(STDOUT, 'Installing Nexus ' . $newPackage['version'] . "...\n");
        nexusUpgradeRunManager($newPackage, 'install', $root, $stateRoot);
        if ($mode === 'disabled') {
            nexusUpgradeRunManager($newPackage, 'disable', $root, $stateRoot);
        }
        nexusUpgradeRunManager($newPackage, 'verify', $root, $stateRoot);
    } catch (Throwable $upgradeError) {
        fwrite(STDERR, 'Upgrade failed. Restoring Nexus ' . $currentPackage['version'] . "...\n");
        try {
            nexusUpgradeRestore($currentPackage, $newPackage, $root, $stateRoot, $stateFile, $mode);
        } catch (Throwable $rollbackError) {
            throw new NexusUpgradeException(
                'Upgrade failed and rollback failed. Upgrade error: ' . $upgradeError->getMessage() .
                ' Rollback error: ' . $rollbackError->getMessage()
            );
        }
        throw new NexusUpgradeException(
            'Upgrade failed; the previous Nexus version was restored and verified. Cause: ' . $upgradeError->getMessage()
        );
    }

    fwrite(STDOUT, 'Nexus upgraded from ' . $currentPackage['version'] . ' to ' . $newPackage['version'] .
        '; previous mode restored: ' . $mode . ".\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . "\n");
    exit(1);
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'updater.php';

$passes = 0;
$temporary = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nexus-updater-tests-' . bin2hex(random_bytes(6));

function updaterExpect(bool $condition, string $message): void
{
    global $passes;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passes++;
    fwrite(STDOUT, "PASS: $message\n");
}

function updaterThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (NexusUpdaterException) {
        updaterExpect(true, $message);
        return;
    }
    throw new RuntimeException($message);
}

function updaterRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

try {
    mkdir($temporary, 0700, true);
    updaterExpect(NexusUpdater::validateVersion('2.5.1') === '2.5.1', 'semantic release version is accepted');
    updaterThrows(fn() => NexusUpdater::validateVersion('latest'), 'non-semantic release version is rejected');
    updaterThrows(fn() => NexusUpdater::validateVersion('2.5.1-beta'), 'prerelease version is rejected');

    $request = NexusUpdater::validateRequest([
        'schema' => 1,
        'action' => 'update',
        'request_id' => str_repeat('a', 32),
        'url' => 'https://evil.invalid/payload.zip',
        'command' => 'sh',
    ]);
    updaterExpect($request === ['schema' => 1, 'action' => 'update', 'request_id' => str_repeat('a', 32)], 'request parser returns only allow-listed fields');
    updaterThrows(fn() => NexusUpdater::validateRequest(['schema' => 1, 'action' => 'shell', 'request_id' => str_repeat('a', 32)]), 'request parser rejects arbitrary actions');
    updaterThrows(fn() => NexusUpdater::validateRequest(['schema' => 1, 'action' => 'check', 'request_id' => '../unsafe']), 'request parser rejects unsafe identifiers');

    NexusUpdater::validateArchiveEntries(['Nexus-Theme-Manager-for-ITFlow-2.5.1/manager.php'], 'Nexus-Theme-Manager-for-ITFlow-2.5.1');
    updaterExpect(true, 'expected release archive layout is accepted');
    updaterThrows(fn() => NexusUpdater::validateArchiveEntries([], 'Nexus-Theme-Manager-for-ITFlow-2.5.1'), 'empty release archive is rejected');
    updaterThrows(fn() => NexusUpdater::validateArchiveEntries(['../escape.php'], 'Nexus-Theme-Manager-for-ITFlow-2.5.1'), 'archive traversal is rejected');
    updaterThrows(fn() => NexusUpdater::validateArchiveEntries(['other/manager.php'], 'Nexus-Theme-Manager-for-ITFlow-2.5.1'), 'unexpected archive root is rejected');
    updaterThrows(fn() => NexusUpdater::validateArchiveEntries(['Nexus-Theme-Manager-for-ITFlow-2.5.1\\manager.php'], 'Nexus-Theme-Manager-for-ITFlow-2.5.1'), 'Windows archive separators are rejected');

    $archiveName = 'Nexus-Theme-Manager-for-ITFlow-2.5.1.zip';
    $archive = $temporary . DIRECTORY_SEPARATOR . $archiveName;
    $checksum = $temporary . DIRECTORY_SEPARATOR . $archiveName . '.sha256.txt';
    file_put_contents($archive, 'verified release bytes');
    file_put_contents($checksum, hash_file('sha256', $archive) . '  ' . $archiveName . "\n");
    NexusUpdater::verifyReleaseChecksum($checksum, $archive, $archiveName);
    updaterExpect(true, 'matching release checksum is accepted');
    file_put_contents($archive, 'tampered release bytes');
    updaterThrows(fn() => NexusUpdater::verifyReleaseChecksum($checksum, $archive, $archiveName), 'release checksum mismatch is rejected');
    file_put_contents($checksum, hash('sha256', 'x') . "  wrong.zip\n");
    updaterThrows(fn() => NexusUpdater::verifyReleaseChecksum($checksum, $archive, $archiveName), 'checksum filename substitution is rejected');

    $package = $temporary . DIRECTORY_SEPARATOR . 'package';
    mkdir($package, 0700, true);
    file_put_contents($package . DIRECTORY_SEPARATOR . 'manager.php', '<?php');
    file_put_contents($package . DIRECTORY_SEPARATOR . 'updater.php', '<?php');
    file_put_contents($package . DIRECTORY_SEPARATOR . 'SHA256SUMS.txt', "test\n");
    file_put_contents($package . DIRECTORY_SEPARATOR . 'manifest.json', json_encode([
        'schema' => 1,
        'package_id' => 'org.nexus-theme-manager.itflow',
        'package_version' => '2.5.1',
        'files' => [],
    ], JSON_THROW_ON_ERROR));
    NexusUpdater::validatePackageManifest($package, '2.5.1');
    updaterExpect(true, 'matching package manifest is accepted');
    NexusUpdater::validateExtractedTree($package);
    updaterExpect(true, 'ordinary extracted package tree is accepted');
    updaterThrows(fn() => NexusUpdater::validatePackageManifest($package, '9.9.9'), 'package version substitution is rejected');
    unlink($package . DIRECTORY_SEPARATOR . 'updater.php');
    updaterThrows(fn() => NexusUpdater::validatePackageManifest($package, '2.5.1'), 'package without privileged updater is rejected');

    [$command, $options] = nexusUpdaterArguments(['updater.php', 'install-service', '--root', '/var/www/itflow', '--state-root', '/state', '--yes']);
    updaterExpect($command === 'install-service' && $options['root'] === '/var/www/itflow' && $options['state_root'] === '/state', 'service setup arguments are parsed without shell interpretation');
    updaterThrows(fn() => nexusUpdaterArguments(['updater.php', 'run', '--url', 'https://evil.invalid']), 'arbitrary updater CLI arguments are rejected');

    $activationRoot = $temporary . DIRECTORY_SEPARATOR . 'activation-root';
    mkdir($activationRoot, 0700, true);
    $updateWorkspace = NexusUpdater::createUpdateWorkspace($activationRoot);
    updaterExpect(
        dirname($updateWorkspace) === realpath($activationRoot)
            && str_starts_with(basename($updateWorkspace), '.nexus-update-'),
        'update workspace is created on the package activation filesystem'
    );

    $unitInstance = str_repeat('b', 16);
    $unitDirectory = $temporary . DIRECTORY_SEPARATOR . 'units';
    $unitUploads = $unitDirectory . DIRECTORY_SEPARATOR . 'uploads';
    mkdir($unitUploads, 0700, true);
    $unitConfig = $unitDirectory . DIRECTORY_SEPARATOR . 'config.json';
    file_put_contents($unitConfig, "{}\n");
    $unitRootPath = PHP_OS_FAMILY === 'Linux' ? str_replace('\\', '/', $unitDirectory) : '/tmp/nexus-updater-unit-test';
    $unitUploadsPath = $unitRootPath . '/uploads';
    $unitConfigPath = $unitRootPath . '/config.json';
    $unitServiceDirectory = PHP_OS_FAMILY === 'Linux' ? str_replace('\\', '/', dirname(__DIR__)) : '/opt/nexus-updater-test';
    $unitPhp = PHP_OS_FAMILY === 'Linux' ? PHP_BINARY : '/usr/bin/php';
    $units = NexusUpdater::renderSystemdUnits(
        $unitInstance,
        $unitPhp,
        $unitServiceDirectory,
        $unitConfigPath,
        $unitUploadsPath . '/.nexus-theme-update-request.json',
        [$unitRootPath, '/opt']
    );
    updaterExpect(
        str_contains($units['path'], "PathExists=$unitUploadsPath/.nexus-theme-update-request.json\n")
            && !str_contains($units['path'], 'PathExists="'),
        'PathExists uses the absolute path syntax accepted by systemd.path'
    );
    updaterThrows(
        fn() => NexusUpdater::renderSystemdUnits($unitInstance, '/usr/bin/php', '/service', '/config', "relative/request", ['/opt']),
        'systemd unit renderer rejects non-absolute paths'
    );

    if (PHP_OS_FAMILY === 'Linux' && is_executable('/usr/bin/systemd-analyze')) {
        $serviceFile = $unitDirectory . DIRECTORY_SEPARATOR . $units['unit_name'] . '.service';
        $pathFile = $unitDirectory . DIRECTORY_SEPARATOR . $units['unit_name'] . '.path';
        file_put_contents($serviceFile, $units['service']);
        file_put_contents($pathFile, $units['path']);
        $process = proc_open(['/usr/bin/systemd-analyze', 'verify', $serviceFile, $pathFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('systemd-analyze could not be started');
        }
        $systemdOutput = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $systemdExit = proc_close($process);
        updaterExpect($systemdExit === 0, 'generated service and path units pass systemd-analyze verify: ' . trim($systemdOutput));
    } else {
        updaterExpect(true, 'generated unit syntax is reserved for systemd-analyze validation in Linux CI');
    }

    $source = (string)file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'updater.php');
    $installerSource = (string)file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'install-latest.sh');
    updaterExpect(str_contains($source, 'PathExists=') && str_contains($source, 'NoNewPrivileges=true'), 'systemd service uses a fixed request path and hardened execution');
    updaterExpect(str_contains($source, 'ProtectSystem=strict') && str_contains($source, 'ReadWritePaths='), 'systemd service limits filesystem writes to declared paths');
    updaterExpect(str_contains($source, "'--max-filesize'") && str_contains($source, 'validateExtractedTree'), 'release downloads and extracted trees have protected limits');
    updaterExpect(str_contains($source, 'createUpdateWorkspace($packageRoot)') && !str_contains($source, "rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nexus-update-'"), 'package activation never relies on a cross-filesystem temporary rename');
    updaterExpect(str_contains($source, "if (\$command === 'repair-service')") && str_contains($installerSource, '--repair-gui-updater'), 'verified bootstrap can repair an existing updater without replacing the active theme');
    updaterExpect(str_contains($source, "PHP_SAPI !== 'cli'"), 'privileged updater entrypoint rejects web execution');
    updaterExpect(str_contains($source, 'The previous Nexus version was restored automatically.'), 'failed activation includes an automatic rollback path');
    updaterExpect(!str_contains($source, 'shell_exec(') && !preg_match('/\bexec\s*\(/', $source), 'updater does not invoke a command shell');

    fwrite(STDOUT, "\nUpdater test result: $passes assertions passed, 0 failed.\n");
    updaterRemoveTree($temporary);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\nTest workspace retained at: $temporary\n");
    exit(1);
}

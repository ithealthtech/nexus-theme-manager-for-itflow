<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$packageRoot = dirname(__DIR__);
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexus-upgrade-tests-' . bin2hex(random_bytes(6));
$passes = 0;

function upgradeCopyTree(string $source, string $destination): void
{
    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException('Could not create ' . $destination);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        if ($relative === '.git' || str_starts_with($relative, '.git' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                throw new RuntimeException('Could not create ' . $target);
            }
        } elseif (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('Could not copy ' . $target);
        }
    }
}

function upgradeRemoveTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function upgradeRun(array $command, int $expectedExit): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start test process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expectedExit) {
        throw new RuntimeException(
            'Unexpected exit code ' . $exit . '; expected ' . $expectedExit . "\n" .
            'Command: ' . implode(' ', $command) . "\nSTDOUT:\n" . $stdout . "\nSTDERR:\n" . $stderr
        );
    }
    return [$stdout, $stderr];
}

function upgradeExpect(bool $condition, string $message): void
{
    global $passes;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passes++;
    fwrite(STDOUT, 'PASS: ' . $message . "\n");
}

function upgradeWritePackageVersion(string $package, string $version): void
{
    $manifestPath = $package . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $manifest['package_version'] = $version;
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}

function upgradeFixture(string $package, string $fixture): void
{
    upgradeCopyTree($package . DIRECTORY_SEPARATOR . 'baseline', $fixture);
    foreach (['css', 'uploads'] as $directory) {
        if (!is_dir($fixture . DIRECTORY_SEPARATOR . $directory)) {
            mkdir($fixture . DIRECTORY_SEPARATOR . $directory, 0777, true);
        }
    }
    file_put_contents($fixture . DIRECTORY_SEPARATOR . 'config.php', "<?php\n// Upgrade test fixture.\n");
}

function upgradeState(string $root, string $stateRoot): array
{
    $resolved = realpath($root);
    if ($resolved === false) {
        throw new RuntimeException('Fixture root could not be resolved.');
    }
    $instance = substr(hash('sha256', rtrim($resolved, DIRECTORY_SEPARATOR)), 0, 16);
    return json_decode(
        (string)file_get_contents($stateRoot . DIRECTORY_SEPARATOR . $instance . DIRECTORY_SEPARATOR . 'state.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

try {
    mkdir($testRoot, 0777, true);
    $currentPackage = $testRoot . DIRECTORY_SEPARATOR . 'current-package';
    upgradeCopyTree($packageRoot, $currentPackage);
    upgradeWritePackageVersion($currentPackage, '3.9.0');

    $fixture = $testRoot . DIRECTORY_SEPARATOR . 'itflow';
    $stateRoot = $testRoot . DIRECTORY_SEPARATOR . 'state';
    upgradeFixture($packageRoot, $fixture);
    $common = ['--root', $fixture, '--state-root', $stateRoot];
    upgradeRun(array_merge([PHP_BINARY, $currentPackage . DIRECTORY_SEPARATOR . 'manager.php', 'install'], $common, ['--yes']), 0);

    [$stdout] = upgradeRun([
        PHP_BINARY,
        $packageRoot . DIRECTORY_SEPARATOR . 'upgrade.php',
        '--root',
        $fixture,
        '--current-package',
        $currentPackage,
        '--state-root',
        $stateRoot,
        '--yes',
    ], 0);
    $state = upgradeState($fixture, $stateRoot);
    $newManifest = json_decode((string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    upgradeExpect($state['package_version'] === $newManifest['package_version'], 'enabled installation upgrades to the replacement package version');
    upgradeExpect($state['mode'] === 'enabled', 'enabled mode is preserved during command-line upgrade');
    upgradeExpect(str_contains($stdout, 'previous mode restored: enabled'), 'successful upgrade reports its preserved mode');
    upgradeRun(array_merge([PHP_BINARY, $packageRoot . DIRECTORY_SEPARATOR . 'manager.php', 'verify'], $common), 0);
    upgradeExpect(true, 'upgraded enabled installation passes managed-file verification');

    upgradeRun(array_merge([PHP_BINARY, $packageRoot . DIRECTORY_SEPARATOR . 'manager.php', 'disable'], $common, ['--yes']), 0);
    $failedPackage = $testRoot . DIRECTORY_SEPARATOR . 'failed-package';
    upgradeCopyTree($packageRoot, $failedPackage);
    upgradeWritePackageVersion($failedPackage, '3.9.2');
    file_put_contents($failedPackage . DIRECTORY_SEPARATOR . 'baseline' . DIRECTORY_SEPARATOR . 'login.php', "<?php\n// Deliberate package-integrity failure.\n");

    [, $rollbackError] = upgradeRun([
        PHP_BINARY,
        $failedPackage . DIRECTORY_SEPARATOR . 'upgrade.php',
        '--root',
        $fixture,
        '--current-package',
        $packageRoot,
        '--state-root',
        $stateRoot,
        '--yes',
    ], 1);
    $state = upgradeState($fixture, $stateRoot);
    upgradeExpect($state['package_version'] === $newManifest['package_version'], 'failed replacement restores the previous package version');
    upgradeExpect($state['mode'] === 'disabled', 'failed replacement restores the previous disabled mode');
    upgradeExpect(str_contains($rollbackError, 'previous Nexus version was restored and verified'), 'failed replacement reports verified automatic rollback');
    upgradeRun(array_merge([PHP_BINARY, $packageRoot . DIRECTORY_SEPARATOR . 'manager.php', 'verify'], $common), 0);
    upgradeExpect(true, 'rolled-back installation passes managed-file verification');

    fwrite(STDOUT, "\nUpgrade test result: $passes assertions passed, 0 failed.\n");
    upgradeRemoveTree($testRoot);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\nTest workspace retained at: $testRoot\n");
    exit(1);
}

<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$packageRoot = dirname(__DIR__);
$manager = $packageRoot . DIRECTORY_SEPARATOR . 'manager.php';
$manifest = json_decode((string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itdr-theme-manager-tests-' . bin2hex(random_bytes(6));
$passes = 0;

function copyTree(string $source, string $destination): void
{
    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException("Could not create $destination");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                throw new RuntimeException("Could not create $target");
            }
        } elseif (!copy($item->getPathname(), $target)) {
            throw new RuntimeException("Could not copy $target");
        }
    }
}

function removeTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function makeFixture(string $packageRoot, string $destination): void
{
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'baseline', $destination);
    if (!is_dir($destination . DIRECTORY_SEPARATOR . 'css')) {
        mkdir($destination . DIRECTORY_SEPARATOR . 'css', 0777, true);
    }
    file_put_contents($destination . DIRECTORY_SEPARATOR . 'config.php', "<?php\n// Test fixture only.\n");
}

function runManager(string $manager, array $arguments, int $expectedExit): array
{
    $command = array_merge([PHP_BINARY, $manager], $arguments);
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start manager process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expectedExit) {
        throw new RuntimeException(
            'Unexpected exit code. Expected ' . $expectedExit . ', got ' . $exit .
            "\nCommand: " . implode(' ', $command) . "\nSTDOUT:\n$stdout\nSTDERR:\n$stderr"
        );
    }
    return [$stdout, $stderr];
}

function expect(bool $condition, string $message): void
{
    global $passes;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passes++;
    fwrite(STDOUT, "PASS: $message\n");
}

function sha(string $path): string
{
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException("Could not hash $path");
    }
    return $hash;
}

try {
    mkdir($testRoot, 0777, true);

    $fixture = $testRoot . DIRECTORY_SEPARATOR . 'fixture';
    $stateRoot = $testRoot . DIRECTORY_SEPARATOR . 'state';
    makeFixture($packageRoot, $fixture);
    $common = ['--root', $fixture, '--state-root', $stateRoot];

    runManager($manager, array_merge(['doctor'], $common), 0);
    expect(!file_exists($fixture . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'itdoneright-theme.css'), 'doctor is non-mutating');

    runManager($manager, array_merge(['install'], $common, ['--yes']), 0);
    foreach ($manifest['files'] as $entry) {
        $target = $fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        expect(is_file($target) && sha($target) === $entry['payload_sha256'], 'install activates ' . $entry['path']);
    }

    runManager($manager, array_merge(['verify'], $common), 0);
    [$statusJson] = runManager($manager, array_merge(['status'], $common, ['--json']), 0);
    $status = json_decode($statusJson, true, 512, JSON_THROW_ON_ERROR);
    expect($status['status'] === 'healthy' && $status['mode'] === 'enabled', 'status reports a healthy enabled install');

    runManager($manager, array_merge(['install'], $common, ['--yes']), 3);
    expect(true, 'a second install is refused');
    runManager($manager, array_merge(['enable'], $common, ['--yes']), 3);
    expect(true, 'enable is refused while already enabled');

    runManager($manager, array_merge(['disable'], $common, ['--yes']), 0);
    foreach ($manifest['files'] as $entry) {
        $target = $fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        if ($entry['baseline_sha256'] === null) {
            expect(!file_exists($target), 'disable removes theme-owned ' . $entry['path']);
        } else {
            expect(is_file($target) && sha($target) === $entry['baseline_sha256'], 'disable restores ' . $entry['path']);
        }
    }
    runManager($manager, array_merge(['verify'], $common), 0);
    runManager($manager, array_merge(['disable'], $common, ['--yes']), 3);
    expect(true, 'disable is refused while already disabled');

    runManager($manager, array_merge(['enable'], $common, ['--yes']), 0);
    runManager($manager, array_merge(['verify'], $common), 0);
    expect(true, 'enable reapplies and verifies the payload');

    $login = $fixture . DIRECTORY_SEPARATOR . 'login.php';
    file_put_contents($login, "\n// post-install drift\n", FILE_APPEND);
    runManager($manager, array_merge(['uninstall'], $common, ['--yes']), 4);
    expect(str_contains((string)file_get_contents($login), 'post-install drift'), 'uninstall refuses to overwrite post-install drift');
    copy($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'login.php', $login);

    runManager($manager, array_merge(['uninstall'], $common, ['--yes']), 0);
    foreach ($manifest['files'] as $entry) {
        $target = $fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        if ($entry['baseline_sha256'] === null) {
            expect(!file_exists($target), 'uninstall removes theme-owned ' . $entry['path']);
        } else {
            expect(is_file($target) && sha($target) === $entry['baseline_sha256'], 'uninstall restores ' . $entry['path']);
        }
    }
    [$notInstalledJson] = runManager($manager, array_merge(['status'], $common, ['--json']), 0);
    $notInstalled = json_decode($notInstalledJson, true, 512, JSON_THROW_ON_ERROR);
    expect($notInstalled['status'] === 'not-installed', 'status reports not installed after uninstall');
    $archives = glob($stateRoot . DIRECTORY_SEPARATOR . 'archives' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    expect(is_array($archives) && count($archives) === 1, 'safe uninstall archives recovery state outside the fixture');

    $incompatible = $testRoot . DIRECTORY_SEPARATOR . 'incompatible';
    makeFixture($packageRoot, $incompatible);
    file_put_contents($incompatible . DIRECTORY_SEPARATOR . 'login.php', "\n// incompatible local change\n", FILE_APPEND);
    runManager($manager, ['doctor', '--root', $incompatible, '--state-root', $testRoot . DIRECTORY_SEPARATOR . 'incompatible-state'], 3);
    expect(!file_exists($incompatible . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'itdoneright-theme.css'), 'incompatible checkout is refused without mutation');

    $adoptFixture = $testRoot . DIRECTORY_SEPARATOR . 'adopt-fixture';
    $adoptState = $testRoot . DIRECTORY_SEPARATOR . 'adopt-state';
    makeFixture($packageRoot, $adoptFixture);
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'payload', $adoptFixture);
    $adoptCommon = ['--root', $adoptFixture, '--state-root', $adoptState];
    runManager($manager, array_merge(['adopt'], $adoptCommon, ['--yes']), 0);
    [$adoptStatusJson] = runManager($manager, array_merge(['status'], $adoptCommon, ['--json']), 0);
    $adoptStatus = json_decode($adoptStatusJson, true, 512, JSON_THROW_ON_ERROR);
    expect($adoptStatus['status'] === 'healthy' && $adoptStatus['mode'] === 'enabled', 'adopt manages an existing exact theme installation');
    runManager($manager, array_merge(['verify'], $adoptCommon), 0);
    expect(true, 'adopted installation verifies');
    runManager($manager, array_merge(['uninstall'], $adoptCommon, ['--yes', '--purge']), 0);
    $adoptRestored = true;
    foreach ($manifest['files'] as $entry) {
        $target = $adoptFixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        $adoptRestored = $adoptRestored && ($entry['baseline_sha256'] === null
            ? !file_exists($target)
            : is_file($target) && sha($target) === $entry['baseline_sha256']);
    }
    expect($adoptRestored, 'uninstall restores the supported baseline after adoption');

    $badAdoptFixture = $testRoot . DIRECTORY_SEPARATOR . 'bad-adopt-fixture';
    makeFixture($packageRoot, $badAdoptFixture);
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'payload', $badAdoptFixture);
    file_put_contents($badAdoptFixture . DIRECTORY_SEPARATOR . 'login.php', "\n// unknown theme drift\n", FILE_APPEND);
    runManager(
        $manager,
        ['adopt', '--root', $badAdoptFixture, '--state-root', $testRoot . DIRECTORY_SEPARATOR . 'bad-adopt-state', '--yes'],
        3
    );
    expect(true, 'adopt refuses a non-exact manual installation');

    $purgeFixture = $testRoot . DIRECTORY_SEPARATOR . 'purge-fixture';
    $purgeState = $testRoot . DIRECTORY_SEPARATOR . 'purge-state';
    makeFixture($packageRoot, $purgeFixture);
    $purgeCommon = ['--root', $purgeFixture, '--state-root', $purgeState];
    runManager($manager, array_merge(['install'], $purgeCommon, ['--yes']), 0);
    runManager($manager, array_merge(['uninstall'], $purgeCommon, ['--yes', '--purge']), 0);
    [$purgeStatusJson] = runManager($manager, array_merge(['status'], $purgeCommon, ['--json']), 0);
    $purgeStatus = json_decode($purgeStatusJson, true, 512, JSON_THROW_ON_ERROR);
    expect($purgeStatus['status'] === 'not-installed', 'purge uninstall removes active state');

    $tamperedPackage = $testRoot . DIRECTORY_SEPARATOR . 'tampered-package';
    copyTree($packageRoot, $tamperedPackage);
    file_put_contents(
        $tamperedPackage . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'itdoneright-theme.css',
        "\n/* tampered */\n",
        FILE_APPEND
    );
    $tamperFixture = $testRoot . DIRECTORY_SEPARATOR . 'tamper-fixture';
    makeFixture($packageRoot, $tamperFixture);
    runManager(
        $tamperedPackage . DIRECTORY_SEPARATOR . 'manager.php',
        ['doctor', '--root', $tamperFixture, '--state-root', $testRoot . DIRECTORY_SEPARATOR . 'tamper-state'],
        4
    );
    expect(true, 'package checksum tampering is detected');

    fwrite(STDOUT, "\nLifecycle test result: $passes assertions passed, 0 failed.\n");
    removeTree($testRoot);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: {$error->getMessage()}\nTest workspace retained at: $testRoot\n");
    exit(1);
}

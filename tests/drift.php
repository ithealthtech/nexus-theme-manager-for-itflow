<?php

declare(strict_types=1);

/*
 * Drift detection and re-apply.
 *
 * ITFlow 26.09 updates itself with `git fetch --all` then `git reset --hard`, so a
 * routine ITFlow update puts every overlaid template back on the upstream blob.
 * Theme-owned files are untracked and survive, which is why nothing noticed. These
 * tests cover the three outcomes that matter: a reverted overlay is detected and
 * restored unattended, an administrator's own edit is never overwritten without
 * --force, and an ITFlow tree from the wrong release is refused with a message
 * that names the version rather than sixteen hash mismatches.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$packageRoot = dirname(__DIR__);
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexus-drift-tests-' . bin2hex(random_bytes(6));
$passes = 0;

function driftCopyTree(string $source, string $destination): void
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

function driftRemoveTree(string $path): void
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

function driftRun(array $command, int $expectedExit): array
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

function driftExpect(bool $condition, string $message): void
{
    global $passes;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passes++;
    fwrite(STDOUT, 'PASS: ' . $message . "\n");
}

/* An ITFlow tree at exactly the release this package supports: the baseline
   directory is, by definition, those files. */
function driftFixture(string $package, string $fixture): void
{
    driftCopyTree($package . DIRECTORY_SEPARATOR . 'baseline', $fixture);
    foreach (['css', 'uploads', 'includes'] as $directory) {
        if (!is_dir($fixture . DIRECTORY_SEPARATOR . $directory)) {
            mkdir($fixture . DIRECTORY_SEPARATOR . $directory, 0777, true);
        }
    }
    file_put_contents($fixture . DIRECTORY_SEPARATOR . 'config.php', "<?php\n// Drift test fixture.\n");

    $manifest = json_decode(
        (string)file_get_contents($package . DIRECTORY_SEPARATOR . 'manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    file_put_contents(
        $fixture . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'app_version.php',
        "<?php\nDEFINE(\"APP_VERSION\", \"" . $manifest['compatible_itflow']['release'] . ".0\");\n"
    );
}

function driftStatusJson(string $manager, array $common): array
{
    [$stdout] = driftRun(array_merge([PHP_BINARY, $manager, 'status'], $common, ['--json']), 0);
    return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
}

try {
    mkdir($testRoot, 0777, true);
    $manager = $packageRoot . DIRECTORY_SEPARATOR . 'manager.php';
    $fixture = $testRoot . DIRECTORY_SEPARATOR . 'itflow';
    $stateRoot = $testRoot . DIRECTORY_SEPARATOR . 'state';
    driftFixture($packageRoot, $fixture);
    $common = ['--root', $fixture, '--state-root', $stateRoot];

    driftRun(array_merge([PHP_BINARY, $manager, 'install'], $common, ['--yes']), 0);
    $status = driftStatusJson($manager, $common);
    driftExpect($status['drift'] === [], 'a fresh install reports no drift');
    driftExpect($status['reapply_recommended'] === false, 'a fresh install does not recommend reapply');
    driftExpect($status['itflow_version_supported'] === true, 'status reports the fixture ITFlow version as supported');

    /* An ITFlow update, plus the one case that is not one.
       `git reset --hard` puts tracked files back on the upstream blob, which is
       the login.php case below. It does NOT remove untracked files, so a
       theme-owned file going missing is something else entirely - a redeploy, a
       zip reinstall, a manual clean - and is covered here because it is
       restorable on the same terms, not because ITFlow causes it. */
    $manifest = json_decode(
        (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $reverted = 'login.php';
    copy(
        $packageRoot . DIRECTORY_SEPARATOR . 'baseline' . DIRECTORY_SEPARATOR . $reverted,
        $fixture . DIRECTORY_SEPARATOR . $reverted
    );
    $ownedPath = 'includes/nexus_theme.php';
    unlink($fixture . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'nexus_theme.php');

    $status = driftStatusJson($manager, $common);
    $kinds = array_column($status['drift'], 'kind', 'path');
    driftExpect(($kinds[$reverted] ?? null) === 'reverted', 'a file restored to the ITFlow baseline is classified as reverted');
    driftExpect(($kinds[$ownedPath] ?? null) === 'reverted', 'an absent theme-owned file is classified as reverted');
    driftExpect($status['reapply_recommended'] === true, 'purely reverted drift recommends reapply');
    driftExpect($status['drift_counts']['modified'] === 0, 'an ITFlow hard reset produces no externally-modified files');

    driftRun(array_merge([PHP_BINARY, $manager, 'reapply'], $common, ['--yes']), 0);
    $status = driftStatusJson($manager, $common);
    driftExpect($status['drift'] === [], 'reapply restores every reverted managed file');
    driftRun(array_merge([PHP_BINARY, $manager, 'verify'], $common), 0);
    driftExpect(true, 'a reapplied installation passes managed-file verification');

    [$idempotentStdout] = driftRun(array_merge([PHP_BINARY, $manager, 'reapply'], $common, ['--yes', '--json']), 0);
    $idempotent = json_decode($idempotentStdout, true, 512, JSON_THROW_ON_ERROR);
    driftExpect($idempotent['status'] === 'healthy', 'reapply on a clean installation is a no-op');

    // An administrator's own edit must survive a refusal untouched.
    $edited = $fixture . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'tickets.php';
    file_put_contents($edited, file_get_contents($edited) . "\n<?php /* local edit */ ?>\n");
    $editedHash = hash_file('sha256', $edited);
    copy(
        $packageRoot . DIRECTORY_SEPARATOR . 'baseline' . DIRECTORY_SEPARATOR . $reverted,
        $fixture . DIRECTORY_SEPARATOR . $reverted
    );

    $status = driftStatusJson($manager, $common);
    driftExpect($status['drift_counts']['modified'] === 1, 'an edit outside the manager is classified as modified');
    driftExpect($status['reapply_recommended'] === false, 'mixed drift does not recommend an unattended reapply');

    [, $refusal] = driftRun(array_merge([PHP_BINARY, $manager, 'reapply'], $common, ['--yes']), 3);
    driftExpect(str_contains($refusal, 'client/tickets.php'), 'refusal names the externally modified file');
    driftExpect(!str_contains($refusal, $reverted), 'refusal does not blame files an ITFlow update reverted');
    driftExpect(hash_file('sha256', $edited) === $editedHash, 'a refused reapply leaves the external edit untouched');

    driftRun(array_merge([PHP_BINARY, $manager, 'reapply'], $common, ['--yes', '--force']), 0);
    driftExpect(hash_file('sha256', $edited) !== $editedHash, 'force overwrites an externally modified file');
    driftRun(array_merge([PHP_BINARY, $manager, 'verify'], $common), 0);
    driftExpect(true, 'a forced reapply passes managed-file verification');

    // Re-apply is meaningless while disabled, and must not quietly re-enable.
    driftRun(array_merge([PHP_BINARY, $manager, 'disable'], $common, ['--yes']), 0);
    [, $disabledError] = driftRun(array_merge([PHP_BINARY, $manager, 'reapply'], $common, ['--yes']), 3);
    driftExpect(str_contains($disabledError, 'disabled'), 'reapply refuses while the theme is disabled');
    $disabledStatus = driftStatusJson($manager, $common);
    driftExpect($disabledStatus['mode'] === 'disabled', 'a refused reapply leaves the theme disabled');

    // The version gate: a tree from the wrong ITFlow release is named as such.
    $wrongRelease = $testRoot . DIRECTORY_SEPARATOR . 'itflow-old';
    driftFixture($packageRoot, $wrongRelease);
    file_put_contents(
        $wrongRelease . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'app_version.php',
        "<?php\nDEFINE(\"APP_VERSION\", \"26.01.7\");\n"
    );
    file_put_contents($wrongRelease . DIRECTORY_SEPARATOR . 'login.php', "<?php\n// A different ITFlow release.\n");
    [, $gateError] = driftRun([
        PHP_BINARY,
        $manager,
        'doctor',
        '--root',
        $wrongRelease,
        '--state-root',
        $testRoot . DIRECTORY_SEPARATOR . 'state-old',
    ], 3);
    driftExpect(str_contains($gateError, '26.01.7'), 'the compatibility gate names the detected ITFlow version');
    driftExpect(
        str_contains($gateError, 'supports ITFlow ' . $manifest['compatible_itflow']['release']),
        'the compatibility gate names the supported ITFlow release'
    );
    driftExpect(str_contains($gateError, 'No files were changed'), 'the compatibility gate confirms nothing was changed');

    // A tampered tree at the right release keeps the original hash-level message.
    $tampered = $testRoot . DIRECTORY_SEPARATOR . 'itflow-tampered';
    driftFixture($packageRoot, $tampered);
    file_put_contents(
        $tampered . DIRECTORY_SEPARATOR . 'login.php',
        file_get_contents($tampered . DIRECTORY_SEPARATOR . 'login.php') . "\n// tampered\n"
    );
    [, $tamperError] = driftRun([
        PHP_BINARY,
        $manager,
        'doctor',
        '--root',
        $tampered,
        '--state-root',
        $testRoot . DIRECTORY_SEPARATOR . 'state-tampered',
    ], 3);
    driftExpect(
        str_contains($tamperError, 'Compatibility check failed') && !str_contains($tamperError, 'supports ITFlow'),
        'a supported-release tree with edited templates keeps the plain compatibility message'
    );

    fwrite(STDOUT, "\nDrift test result: $passes assertions passed, 0 failed.\n");
    driftRemoveTree($testRoot);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDOUT, 'FAIL: ' . $error->getMessage() . "\n");
    fwrite(STDOUT, 'Test workspace retained at: ' . $testRoot . "\n");
    exit(1);
}

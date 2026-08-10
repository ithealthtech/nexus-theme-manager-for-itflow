<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'updater.php';

if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "Linux activation test must run as root.\n");
    exit(1);
}

$suffix = bin2hex(random_bytes(6));
$workspace = null;
$target = NEXUS_UPDATER_PACKAGE_ROOT . DIRECTORY_SEPARATOR . 'Nexus-Theme-Manager-for-ITFlow-ci-' . $suffix;

function removeLinuxActivationTree(string $path): void
{
    $normalized = str_replace('\\', '/', $path);
    if (!str_starts_with($normalized, '/opt/.nexus-update-') && !str_starts_with($normalized, '/opt/Nexus-Theme-Manager-for-ITFlow-ci-')) {
        throw new RuntimeException('Refusing unsafe Linux activation test cleanup.');
    }
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

try {
    $workspace = NexusUpdater::createUpdateWorkspace(NEXUS_UPDATER_PACKAGE_ROOT);
    $staged = $workspace . DIRECTORY_SEPARATOR . 'extracted' . DIRECTORY_SEPARATOR . basename($target);
    if (!mkdir($staged, 0700, true) || file_put_contents($staged . DIRECTORY_SEPARATOR . 'manager.php', "<?php\n") === false) {
        throw new RuntimeException('Could not create the staged Linux package fixture.');
    }

    $workspaceDevice = stat($workspace)['dev'] ?? null;
    $targetDevice = stat(NEXUS_UPDATER_PACKAGE_ROOT)['dev'] ?? null;
    if ($workspaceDevice === null || $workspaceDevice !== $targetDevice) {
        throw new RuntimeException('Updater workspace is not on the package activation filesystem.');
    }
    if (!rename($staged, $target) || !is_file($target . DIRECTORY_SEPARATOR . 'manager.php')) {
        throw new RuntimeException('Same-filesystem package activation failed.');
    }

    fwrite(STDOUT, "PASS: verified package staging and atomic activation share the /opt filesystem.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    if (is_string($target)) {
        removeLinuxActivationTree($target);
    }
    if (is_string($workspace)) {
        removeLinuxActivationTree($workspace);
    }
}

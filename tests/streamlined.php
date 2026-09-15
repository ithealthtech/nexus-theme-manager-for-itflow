<?php

declare(strict_types=1);

/*
 * Nexus 5 hook lifecycle, against a throwaway fixture.
 *
 * The contract that matters most: install adds exactly one line to each target,
 * and uninstall leaves every file byte-identical to what was there before - in
 * any line-ending or indentation style - so an ITFlow file never drifts from
 * ITFlow's own git history because of Nexus.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$repo = dirname(__DIR__);
$passes = 0;
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexus5-' . bin2hex(random_bytes(5));

function check(bool $ok, string $label): void
{
    global $passes;
    if (!$ok) {
        throw new RuntimeException($label);
    }
    $passes++;
    echo "PASS: $label\n";
}

function copyTree(string $from, string $to): void
{
    @mkdir($to, 0777, true);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $to . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($from) + 1);
        $item->isDir() ? @mkdir($target, 0777, true) : copy($item->getPathname(), $target);
    }
}

function removeTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function cli(string $root, string ...$args): array
{
    $cmd = array_merge([PHP_BINARY, "$root/nexus/cli.php"], $args);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), $out];
}

/* Header shapes that exist in ITFlow 26.09, plus the awkward ones that must still round-trip. */
$fixtures = [
    'includes/header.php' => "<?php\nheader('X');\n?>\n<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <link rel=\"stylesheet\" href=\"/css/itflow_custom.css\">\n</head>\n<body class=\"layout-fixed\">\n",
    'client/includes/header.php' => "<?php header('X'); ?>\n<html>\n<head>\n        <title>Portal</title>\n        </head>\n<body>\n",
    'guest/includes/guest_header.php' => "<!DOCTYPE html>\r\n<html>\r\n<head>\r\n  <title>Guest</title>\r\n</head>\r\n<body>\r\n",
    'login.php' => "<?php\n\$x = 1;\n?>\n<html><head><title>Login</title></head><body class=\"login-page\">\n",
    'client/login_reset.php' => "<?php ?>\n<head>\n<title>Reset</title>\n</head>\n",
    'agent/user/mfa_enforcement.php' => "<?php ?>\n<html>\n<head>\n\t<title>MFA</title>\n</head>\n",
];

try {
    mkdir($tmp, 0777, true);
    foreach ($fixtures as $relative => $contents) {
        @mkdir(dirname("$tmp/$relative"), 0777, true);
        file_put_contents("$tmp/$relative", $contents);
    }
    file_put_contents("$tmp/config.php", "<?php\n");
    copyTree("$repo/nexus", "$tmp/nexus");
    removeTree("$tmp/nexus/data");
    mkdir("$tmp/nexus/data");

    [$rc, $out] = cli($tmp, 'status');
    check($rc === 3 && substr_count($out, 'unhooked') === 6, 'status reports all six pages unhooked before install');

    [$rc, $out] = cli($tmp, 'install');
    check($rc === 0, 'install succeeds');
    foreach ($fixtures as $relative => $original) {
        $now = file_get_contents("$tmp/$relative");
        check(substr_count($now, 'nexus-theme-hook') === 1, "install adds exactly one hook to $relative");
        check(str_replace(explode("\n", $now)[0] === '' ? '' : '', '', $now) !== $original, "install changed $relative");
        $lint = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$tmp/$relative") . ' 2>&1', $lint, $lintRc);
        check($lintRc === 0, "$relative is still valid PHP after install");
    }

    $afterFirst = array_map(static fn(string $r): string => (string)file_get_contents("$tmp/$r"), array_combine(array_keys($fixtures), array_keys($fixtures)));
    [$rc] = cli($tmp, 'install');
    $afterSecond = array_map(static fn(string $r): string => (string)file_get_contents("$tmp/$r"), array_combine(array_keys($fixtures), array_keys($fixtures)));
    check($rc === 0 && $afterFirst === $afterSecond, 'running install twice changes nothing the second time');

    [$rc, $out] = cli($tmp, 'status');
    check($rc === 0 && str_contains($out, 'All pages hooked.'), 'status reports all six pages hooked');

    [$rc] = cli($tmp, 'uninstall');
    check($rc === 0, 'uninstall succeeds');
    foreach ($fixtures as $relative => $original) {
        check(file_get_contents("$tmp/$relative") === $original, "uninstall leaves $relative byte-identical to the original");
    }
    check(is_dir("$tmp/nexus"), 'uninstall without --purge keeps the nexus folder');

    // A hook inside a PHP string must be refused, not spliced into code.
    file_put_contents("$tmp/login.php", "<?php echo '<head></head>'; ?>\n");
    [$rc, $out] = cli($tmp, 'install');
    check($rc === 3 && str_contains($out, 'inside PHP code'), 'install refuses a </head> that is inside PHP code');
    check(substr_count((string)file_get_contents("$tmp/includes/header.php"), 'nexus-theme-hook') === 0, 'a refused install changes no file at all');
    file_put_contents("$tmp/login.php", $fixtures['login.php']);

    cli($tmp, 'install');
    [$rc] = cli($tmp, 'uninstall', '--purge');
    check($rc === 0 && !is_dir("$tmp/nexus"), 'uninstall --purge deletes the nexus folder');
    foreach ($fixtures as $relative => $original) {
        check(file_get_contents("$tmp/$relative") === $original, "purge leaves $relative byte-identical");
    }

    // The hook must be harmless once the folder is gone: the guard skips it.
    file_put_contents("$tmp/probe.php", "<?php \$_SERVER['DOCUMENT_ROOT'] = __DIR__; ?>\n<?php /* nexus-theme-hook */ if (is_file(\$_SERVER['DOCUMENT_ROOT'] . '/nexus/hook.php')) { include \$_SERVER['DOCUMENT_ROOT'] . '/nexus/hook.php'; } ?>\nOK");
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/probe.php") . ' 2>&1', $probe, $probeRc);
    check($probeRc === 0 && trim(implode("\n", $probe)) === 'OK', 'a leftover hook line is harmless when nexus/ has been deleted');

    echo "\nStreamlined test result: $passes assertions passed, 0 failed.\n";
    removeTree($tmp);
    exit(0);
} catch (Throwable $error) {
    echo "FAIL: {$error->getMessage()}\nWorkspace kept at: $tmp\n";
    exit(1);
}

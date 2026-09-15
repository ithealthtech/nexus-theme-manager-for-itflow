<?php
/*
 * Nexus Theme Manager 5 - command line.
 *
 *   php nexus/cli.php status             what is hooked, what is not
 *   php nexus/cli.php install            add the six page hooks (safe to re-run)
 *   php nexus/cli.php uninstall          remove the six page hooks
 *   php nexus/cli.php uninstall --purge  remove the hooks and delete the nexus/ folder
 *
 * Run it as the user that owns the ITFlow files (www-data on Debian and Ubuntu), or
 * as root. There is no state to go stale: each hook is one self-identifying line, so
 * every command works out what to do by reading the six files as they are now.
 *
 * An ITFlow update resets those six files and so removes the hooks, but it leaves
 * the untracked nexus/ folder alone. After an ITFlow update, run install again.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const NEXUS_HOOK_MARKER = 'nexus-theme-hook';
const NEXUS_HOOK_LINE = "<?php /* nexus-theme-hook */ if (is_file(\$_SERVER['DOCUMENT_ROOT'] . '/nexus/hook.php')) { include \$_SERVER['DOCUMENT_ROOT'] . '/nexus/hook.php'; } ?>";

/* The ITFlow files that open their own <head>, by the page area they serve. */
const NEXUS_HOOK_TARGETS = [
    'includes/header.php' => 'Technician and admin pages',
    'client/includes/header.php' => 'Client portal',
    'guest/includes/guest_header.php' => 'Guest links (invoices, quotes, approvals)',
    'login.php' => 'Sign-in page',
    'client/login_reset.php' => 'Portal password reset',
    'agent/user/mfa_enforcement.php' => 'MFA enrolment',
];

function out(string $line = ''): void
{
    fwrite(STDOUT, $line . "\n");
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "ERROR: $message\n");
    exit($code);
}

function itflowRoot(): string
{
    // nexus/ sits directly in the ITFlow web root.
    $root = dirname(__DIR__);
    if (!is_file("$root/config.php") || !is_dir("$root/includes") || !is_file("$root/login.php")) {
        fail("nexus/ must be placed in the ITFlow web root. $root does not look like one.");
    }
    return $root;
}

/** @return array{state: string, detail: string} */
function hookState(string $root, string $relative): array
{
    $path = "$root/$relative";
    if (!is_file($path)) {
        return ['state' => 'missing', 'detail' => 'file not found in this ITFlow version'];
    }
    $source = (string)file_get_contents($path);
    $hooks = substr_count($source, NEXUS_HOOK_MARKER);
    $heads = preg_match_all('#</head>#i', $source);
    if ($hooks === 1) {
        return ['state' => 'hooked', 'detail' => ''];
    }
    if ($hooks > 1) {
        return ['state' => 'duplicate', 'detail' => "$hooks hook lines"];
    }
    if ($heads !== 1) {
        return ['state' => 'unhookable', 'detail' => "expected one </head>, found $heads"];
    }
    // The hook is a PHP block, so the </head> it goes in front of must be plain HTML,
    // not text inside a PHP string. Ask PHP's own tokenizer rather than guessing.
    $inHtml = 0;
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && $token[0] === T_INLINE_HTML) {
            $inHtml += preg_match_all('#</head>#i', $token[1]);
        }
    }
    if ($inHtml !== 1) {
        return ['state' => 'unhookable', 'detail' => '</head> is inside PHP code, not HTML'];
    }
    return ['state' => 'unhooked', 'detail' => ''];
}

function writeKeepingOwner(string $path, string $contents): void
{
    $temporary = $path . '.nexus-tmp';
    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        fail("Could not write $path. Run as the ITFlow file owner or root.");
    }
    $stat = stat($path);
    if ($stat !== false) {
        @chmod($temporary, $stat['mode'] & 0777);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($temporary, $stat['uid']);
            @chgrp($temporary, $stat['gid']);
        }
    }
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        fail("Could not replace $path.");
    }
}

function removeHookLines(string $source): string
{
    // Remove each hook line together with its own line ending, and nothing else.
    return (string)preg_replace('#^[^\r\n]*' . NEXUS_HOOK_MARKER . '[^\r\n]*(?:\r\n|\n)#m', '', $source);
}

function cmdStatus(string $root): int
{
    $hooked = 0;
    out('Nexus Theme Manager 5 - ' . $root);
    out();
    foreach (NEXUS_HOOK_TARGETS as $relative => $label) {
        $s = hookState($root, $relative);
        if ($s['state'] === 'hooked') {
            $hooked++;
        }
        out(sprintf('  %-11s %-34s %s%s', $s['state'], $relative, $label, $s['detail'] !== '' ? " ({$s['detail']})" : ''));
    }
    out();
    $enabled = false;
    try {
        require_once __DIR__ . '/lib/engine.php';
        $enabled = nexusThemeIsEnabled();
        $health = nexusThemeHealthReport();
        out('Theme:  ' . ($enabled ? 'enabled' : 'disabled in Theme Studio'));
        out('Health: ' . ($health['healthy'] ? 'ok' : 'failing - ' . implode(', ', $health['failed'])));
    } catch (Throwable $error) {
        out('Engine: failed to load - ' . $error->getMessage());
    }
    out();
    if ($hooked === count(NEXUS_HOOK_TARGETS)) {
        out('All pages hooked.');
        return 0;
    }
    out("$hooked of " . count(NEXUS_HOOK_TARGETS) . ' pages hooked. Run: php nexus/cli.php install');
    return 3;
}

function cmdInstall(string $root): int
{
    $problems = [];
    foreach (NEXUS_HOOK_TARGETS as $relative => $label) {
        $s = hookState($root, $relative);
        if (in_array($s['state'], ['unhookable', 'duplicate'], true)) {
            $problems[] = "$relative: {$s['detail']}";
        }
        if (!is_writable("$root/$relative") && $s['state'] === 'unhooked') {
            $problems[] = "$relative: not writable by this user";
        }
    }
    if ($problems !== []) {
        fail("Nothing was changed:\n  " . implode("\n  ", $problems), 3);
    }

    $data = __DIR__ . '/data';
    $media = __DIR__ . '/media';
    foreach ([$data, $media] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            fail("Could not create $dir.");
        }
    }

    foreach (NEXUS_HOOK_TARGETS as $relative => $label) {
        $path = "$root/$relative";
        $s = hookState($root, $relative);
        if ($s['state'] === 'missing') {
            out("  skipped  $relative (not in this ITFlow version)");
            continue;
        }
        if ($s['state'] === 'hooked') {
            out("  ok       $relative");
            continue;
        }
        $source = (string)file_get_contents($path);
        // A whole line of its own, above the line holding </head>, so uninstall can
        // take away exactly that line and leave the file byte-identical to ITFlow's.
        $eol = str_contains($source, "\r\n") ? "\r\n" : "\n";
        $hooked = (string)preg_replace('#^([^\r\n]*</head>)#mi', NEXUS_HOOK_LINE . $eol . '$1', $source, 1);
        writeKeepingOwner($path, $hooked);
        if (@exec('php -l ' . escapeshellarg($path) . ' 2>&1', $lint, $rc) !== false && $rc !== 0) {
            writeKeepingOwner($path, $source);
            fail("Hooking $relative produced invalid PHP, so it was restored untouched.");
        }
        out("  hooked   $relative");
    }
    out();
    out('Installed. Reload PHP (for example: systemctl reload apache2) so pages pick it up.');
    out('Theme Studio: /nexus/studio/  (ITFlow administrators only)');
    return 0;
}

function cmdUninstall(string $root, bool $purge): int
{
    foreach (NEXUS_HOOK_TARGETS as $relative => $label) {
        $path = "$root/$relative";
        if (!is_file($path)) {
            continue;
        }
        $source = (string)file_get_contents($path);
        if (!str_contains($source, NEXUS_HOOK_MARKER)) {
            out("  clean    $relative");
            continue;
        }
        writeKeepingOwner($path, removeHookLines($source));
        out("  removed  $relative");
    }

    if ($purge) {
        $home = __DIR__;
        if (basename($home) !== 'nexus' || dirname($home) !== $root) {
            fail("Refusing to delete $home: not <ITFlow root>/nexus.");
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($home, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($home);
        out(is_dir($home) ? "  WARNING: could not fully delete $home" : "  deleted  $home");
    }

    out();
    out('Uninstalled. Reload PHP so stock pages are served.');
    if (!$purge) {
        out('The nexus/ folder (settings and logos) was kept. Delete it, or re-run with --purge.');
    }
    return 0;
}

$command = $argv[1] ?? 'status';
$root = itflowRoot();

exit(match ($command) {
    'status' => cmdStatus($root),
    'install' => cmdInstall($root),
    'uninstall' => cmdUninstall($root, in_array('--purge', $argv, true)),
    default => (function () use ($command): int {
        out("Unknown command: $command");
        out('Usage: php nexus/cli.php status|install|uninstall [--purge]');
        return 2;
    })(),
});

<?php
/*
 * Nexus page hook.
 *
 * Included by a single line that `php nexus/cli.php install` adds to six ITFlow page
 * headers. It prints three external tags - the theme stylesheet, the generated
 * settings stylesheet and the runtime script - and nothing else.
 *
 * External files only, never inline <style> or <script>: ITFlow sends
 * `Content-Security-Policy: default-src 'self'` on login.php and every client
 * portal page, which blocks inline code outright but allows same-origin files.
 *
 * It must never break ITFlow. Any failure - a missing or corrupt file, bad
 * settings, the theme switched off - prints nothing, and the page renders as stock.
 */

/*
 * Which part of ITFlow the page belongs to. The six hooked headers map to four
 * surfaces: sign-in pages, the client portal, guest links, and everything else
 * (the technician and admin shell).
 */
// Only meaningful when included by an ITFlow page; requested on its own it is a 404.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    return;
}

if (!function_exists("nexusHookSurface")) {
function nexusHookSurface(string $scriptName): string
{
    $path = '/' . ltrim(str_replace('\\', '/', $scriptName), '/');

    if (in_array($path, ['/login.php', '/client/login_reset.php', '/agent/user/mfa_enforcement.php'], true)) {
        return 'auth';
    }
    if (str_starts_with($path, '/client/')) {
        return 'client';
    }
    if (str_starts_with($path, '/guest/')) {
        return 'guest';
    }
    return 'technician';
}
}

(static function (): void {
    try {
        $home = __DIR__;
        if (!is_file($home . '/lib/engine.php')) {
            return;
        }
        require_once $home . '/lib/engine.php';

        nexusThemeApplyDueSchedule();
        if (!nexusThemeRuntimeEnabled()) {
            return;
        }

        $surface = nexusHookSurface((string)($_SERVER['SCRIPT_NAME'] ?? ''));

        // Navigation visibility is presentation only; ITFlow still enforces access.
        $role = !empty($GLOBALS['session_is_admin']) ? 'admin' : 'tech';

        $settingsVersion = nexusThemeSettingsVersion();
        $themeVersion = (string)@filemtime($home . '/assets/theme.css');

        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        echo "\n<!-- Nexus Theme Manager " . $e(NEXUS_MANAGER_VERSION) . " -->\n";
        echo '<link rel="stylesheet" href="/nexus/assets/theme.css?v=' . $e($themeVersion) . '">' . "\n";
        echo '<link rel="stylesheet" href="/nexus/style.php?v=' . $e($settingsVersion) . '">' . "\n";
        echo '<script src="/nexus/runtime.php?surface=' . $e($surface) . '&amp;role=' . $e($role)
            . '&amp;v=' . $e($settingsVersion) . '"></script>' . "\n";

        $settings = nexusThemeSettings();
        $favicon = nexusThemeFaviconUrl($settings, '');
        if ($favicon !== '') {
            echo '<link rel="icon" href="' . $e(nexusThemeVersionedAssetUrl($favicon, $settings)) . '">' . "\n";
        }
    } catch (Throwable $error) {
        error_log('Nexus Theme Manager: hook skipped - ' . $error->getMessage());
    }
})();

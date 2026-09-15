<?php
/*
 * The Nexus runtime script, loaded from the <head> of every hooked ITFlow page.
 *
 * It runs before the page body is parsed, so the only element it can reach is
 * <html>. That is where every Nexus class goes - surface, layout options and colour
 * mode - and the stylesheet is written against <html> classes for that reason.
 * It also keeps Bootstrap's data-bs-theme in step with Nexus dark mode, and
 * applies the navigation builder once the sidebar exists.
 *
 * An external file, never inline: ITFlow's Content-Security-Policy on sign-in and
 * portal pages blocks inline scripts.
 */

declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// Colour mode depends on the visitor's own cookie, so this must not be cached.
header('Cache-Control: no-store');

try {
    require_once __DIR__ . '/lib/engine.php';
    if (!nexusThemeRuntimeEnabled()) {
        exit;
    }

    $surfaces = ['technician' => 'nexus-agent', 'client' => 'nexus-client', 'guest' => 'nexus-guest', 'auth' => 'nexus-auth'];
    $surface = (string)($_GET['surface'] ?? 'technician');
    if (!isset($surfaces[$surface])) {
        $surface = 'technician';
    }
    $role = ($_GET['role'] ?? '') === 'admin' ? 'admin' : 'tech';

    $settings = nexusThemeSettings();
    $model = nexusThemePresentationModel($settings, 'ITFlow', $surface);

    $classes = trim('nexus-theme ' . $surfaces[$surface] . ' ' . $model['body_classes']);
    $classes = preg_split('/\s+/', $classes) ?: [];
    $classes = array_values(array_unique(array_filter(
        $classes,
        static fn(string $c): bool => preg_match('/^[a-z0-9-]+$/', $c) === 1
    )));

    echo '(function(){"use strict";var r=document.documentElement;r.classList.add.apply(r.classList,'
        . json_encode($classes, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR)
        . ');})();' . "\n";

    echo nexusThemeColorModeScript($settings) . "\n";

    if ($surface === 'technician') {
        echo nexusThemeNavigationScript($settings, $role) . "\n";
    }
} catch (Throwable $error) {
    error_log('Nexus Theme Manager: runtime skipped - ' . $error->getMessage());
}

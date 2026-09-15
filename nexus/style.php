<?php
/*
 * The stylesheet generated from Theme Studio settings: palette, radius, fonts,
 * dark-mode colours and logo images. Served as a file rather than inline CSS so
 * ITFlow's `default-src 'self'` Content-Security-Policy allows it.
 */

declare(strict_types=1);

header('Content-Type: text/css; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
// The URL carries the settings version, so a saved change is picked up at once.
header('Cache-Control: private, max-age=300');

try {
    require_once __DIR__ . '/lib/engine.php';
    if (nexusThemeRuntimeEnabled()) {
        echo nexusThemeCustomCss();
    }
} catch (Throwable $error) {
    error_log('Nexus Theme Manager: style skipped - ' . $error->getMessage());
}

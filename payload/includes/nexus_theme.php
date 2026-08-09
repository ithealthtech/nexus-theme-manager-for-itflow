<?php

declare(strict_types=1);

const NEXUS_MANAGER_VERSION = '2.3.0';
const NEXUS_THEME_VERSION = '26.08.4';
const NEXUS_ITFLOW_COMMIT = '89b080b430aaafba5d520c4e52c57b28a9559085';
const NEXUS_THEME_DISABLED_MARKER = '.nexus-theme-disabled';

function nexusThemeDocumentRoot(?string $root = null): string
{
    $candidate = $root ?? ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
    $resolved = realpath($candidate);

    if ($resolved === false || !is_dir($resolved)) {
        throw new RuntimeException('The ITFlow document root could not be resolved.');
    }

    return rtrim($resolved, DIRECTORY_SEPARATOR);
}

function nexusThemeControlPath(?string $root = null): string
{
    return nexusThemeDocumentRoot($root)
        . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . NEXUS_THEME_DISABLED_MARKER;
}

function nexusThemeIsEnabled(?string $root = null): bool
{
    return !is_file(nexusThemeControlPath($root));
}

function nexusThemeControlIsWritable(?string $root = null): bool
{
    $marker = nexusThemeControlPath($root);
    $uploads = dirname($marker);

    if (!is_dir($uploads) || is_link($marker)) {
        return false;
    }

    return file_exists($marker) ? is_writable($marker) && is_writable($uploads) : is_writable($uploads);
}

function nexusThemeSetEnabled(bool $enabled, ?string $root = null): void
{
    $documentRoot = nexusThemeDocumentRoot($root);
    $uploads = $documentRoot . DIRECTORY_SEPARATOR . 'uploads';
    $resolvedUploads = realpath($uploads);

    if ($resolvedUploads === false || !is_dir($resolvedUploads)) {
        throw new RuntimeException('The ITFlow uploads directory does not exist.');
    }

    $rootPrefix = $documentRoot . DIRECTORY_SEPARATOR;
    if (!str_starts_with(rtrim($resolvedUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $rootPrefix)) {
        throw new RuntimeException('The theme control path is outside the ITFlow document root.');
    }

    $marker = $resolvedUploads . DIRECTORY_SEPARATOR . NEXUS_THEME_DISABLED_MARKER;
    if (is_link($marker)) {
        throw new RuntimeException('The theme control marker cannot be a symbolic link.');
    }

    if ($enabled) {
        if (is_file($marker) && !@unlink($marker)) {
            throw new RuntimeException('The disabled marker could not be removed.');
        }
        clearstatcache(true, $marker);
        return;
    }

    if (is_file($marker)) {
        return;
    }

    if (!is_writable($resolvedUploads)) {
        throw new RuntimeException('The ITFlow uploads directory is not writable by the web service.');
    }

    $temporary = tempnam($resolvedUploads, '.nexus-state-');
    if ($temporary === false) {
        throw new RuntimeException('A temporary theme control file could not be created.');
    }

    try {
        if (file_put_contents($temporary, "disabled\n", LOCK_EX) === false) {
            throw new RuntimeException('The theme control state could not be written.');
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, $marker)) {
            throw new RuntimeException('The theme control state could not be activated.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }

    clearstatcache(true, $marker);
}

<?php

declare(strict_types=1);

const NEXUS_MANAGER_VERSION = '2.5.2';
const NEXUS_THEME_VERSION = '26.08.7';
const NEXUS_ITFLOW_COMMIT = '89b080b430aaafba5d520c4e52c57b28a9559085';
const NEXUS_THEME_DISABLED_MARKER = '.nexus-theme-disabled';
const NEXUS_THEME_SETTINGS_FILE = '.nexus-theme-settings.json';
const NEXUS_THEME_ASSET_DIRECTORY = 'nexus-theme';
const NEXUS_THEME_MAX_LOGO_BYTES = 3145728;
const NEXUS_UPDATER_REQUEST_FILE = '.nexus-theme-update-request.json';
const NEXUS_UPDATER_STATUS_FILE = '.nexus-theme-update-status.json';
const NEXUS_UPDATER_READY_FILE = '.nexus-theme-updater-ready.json';

function nexusThemeDocumentRoot(?string $root = null): string
{
    $candidate = $root ?? ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
    $resolved = realpath($candidate);

    if ($resolved === false || !is_dir($resolved)) {
        throw new RuntimeException('The ITFlow document root could not be resolved.');
    }

    return rtrim($resolved, DIRECTORY_SEPARATOR);
}

function nexusThemeUploadsPath(?string $root = null): string
{
    $documentRoot = nexusThemeDocumentRoot($root);
    $uploads = realpath($documentRoot . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploads === false || !is_dir($uploads)) {
        throw new RuntimeException('The ITFlow uploads directory does not exist.');
    }

    $rootPrefix = $documentRoot . DIRECTORY_SEPARATOR;
    $uploads = rtrim($uploads, DIRECTORY_SEPARATOR);
    if (!str_starts_with($uploads . DIRECTORY_SEPARATOR, $rootPrefix)) {
        throw new RuntimeException('The Nexus data path is outside the ITFlow document root.');
    }

    return $uploads;
}

function nexusThemeControlPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_DISABLED_MARKER;
}

function nexusThemeSettingsPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_SETTINGS_FILE;
}

function nexusThemeAssetPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_ASSET_DIRECTORY;
}

function nexusUpdaterControlPath(string $file, ?string $root = null): string
{
    if (!in_array($file, [NEXUS_UPDATER_REQUEST_FILE, NEXUS_UPDATER_STATUS_FILE, NEXUS_UPDATER_READY_FILE], true)) {
        throw new RuntimeException('Unknown Nexus updater control file.');
    }
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . $file;
}

function nexusUpdaterReadJson(string $file, ?string $root = null): ?array
{
    $path = nexusUpdaterControlPath($file, $root);
    if (!is_file($path) || is_link($path) || filesize($path) > 16384) {
        return null;
    }
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    } catch (JsonException) {
        return null;
    }
}

function nexusUpdaterReady(?string $root = null): bool
{
    $ready = nexusUpdaterReadJson(NEXUS_UPDATER_READY_FILE, $root);
    return ($ready['schema'] ?? null) === 1
        && preg_match('/^[a-f0-9]{16}$/', (string)($ready['instance_id'] ?? '')) === 1;
}

function nexusUpdaterStatus(?string $root = null): array
{
    $fallback = [
        'state' => nexusUpdaterReady($root) ? 'ready' : 'not_configured',
        'message' => nexusUpdaterReady($root)
            ? 'GUI updates are ready. Check for the latest Nexus release.'
            : 'Install the protected updater service to enable GUI updates.',
        'current_version' => NEXUS_MANAGER_VERSION,
        'latest_version' => null,
        'updated_at' => null,
        'release_url' => null,
        'phase' => null,
    ];
    $saved = nexusUpdaterReadJson(NEXUS_UPDATER_STATUS_FILE, $root);
    if ($saved === null) {
        return $fallback;
    }
    $states = ['ready', 'checking', 'update_available', 'up_to_date', 'running', 'completed', 'failed'];
    $state = in_array($saved['state'] ?? '', $states, true) ? $saved['state'] : $fallback['state'];
    $updatedAt = nexusThemeCleanText($saved['updated_at'] ?? '', 40);
    $updatedTimestamp = $updatedAt !== '' ? strtotime($updatedAt) : false;
    $message = nexusThemeCleanText($saved['message'] ?? $fallback['message'], 280);
    if (in_array($state, ['checking', 'running'], true)
        && ($updatedTimestamp === false || $updatedTimestamp < time() - 1200)) {
        $state = 'failed';
        $message = 'The updater stopped reporting progress. Check the systemd service log, then retry.';
    }
    $version = static function (mixed $value): ?string {
        $candidate = (string)$value;
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $candidate) === 1 ? $candidate : null;
    };
    $releaseUrl = (string)($saved['release_url'] ?? '');
    if (!str_starts_with($releaseUrl, 'https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/tag/v')) {
        $releaseUrl = '';
    }
    return [
        'state' => $state,
        'message' => $message,
        'current_version' => $version($saved['current_version'] ?? '') ?? NEXUS_MANAGER_VERSION,
        'latest_version' => $version($saved['latest_version'] ?? ''),
        'updated_at' => $updatedAt,
        'release_url' => $releaseUrl !== '' ? $releaseUrl : null,
        'phase' => nexusThemeCleanText($saved['phase'] ?? '', 32),
    ];
}

function nexusUpdaterQueue(string $action, ?string $root = null): string
{
    if (!in_array($action, ['check', 'update'], true)) {
        throw new RuntimeException('Invalid Nexus updater action.');
    }
    if (!nexusUpdaterReady($root)) {
        throw new RuntimeException('The protected Nexus updater service is not installed.');
    }
    $status = nexusUpdaterStatus($root);
    if (in_array($status['state'], ['checking', 'running'], true)) {
        throw new RuntimeException('A Nexus updater operation is already running.');
    }
    $path = nexusUpdaterControlPath(NEXUS_UPDATER_REQUEST_FILE, $root);
    if (is_link($path) || is_file($path)) {
        throw new RuntimeException('A Nexus updater request is already queued.');
    }
    $requestId = bin2hex(random_bytes(16));
    $request = json_encode([
        'schema' => 1,
        'action' => $action,
        'request_id' => $requestId,
        'requested_at' => gmdate('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    nexusThemeAtomicWrite($path, $request, 0640);
    return $requestId;
}

function nexusThemeDefaults(): array
{
    return [
        'schema' => 1,
        'preset' => 'aurora',
        'branding' => [
            'brand_name' => '',
            'tagline' => 'Secure support, beautifully connected.',
            'logo_path' => '',
            'logo_alt' => '',
            'show_login_logo' => true,
            'show_portal_logo' => true,
        ],
        'content' => [
            'login_eyebrow' => 'Secure support portal',
            'login_heading' => 'Welcome back',
            'login_message' => 'Sign in to request support, follow updates, or manage your ITFlow workspace.',
            'portal_heading' => 'Support center',
            'portal_message' => 'Request support and follow your latest updates.',
        ],
        'colors' => [
            'primary' => '#69bff5',
            'secondary' => '#7888ff',
            'sidebar' => '#121124',
            'auth_background' => '#0b0a17',
            'page' => '#f3f4fa',
            'surface' => '#ffffff',
            'text' => '#121124',
        ],
        'appearance' => [
            'radius' => 'balanced',
            'density' => 'comfortable',
            'font_scale' => 100,
            'reduce_motion' => false,
        ],
    ];
}

function nexusThemePresets(): array
{
    return [
        'aurora' => ['primary' => '#69bff5', 'secondary' => '#7888ff', 'sidebar' => '#121124', 'auth_background' => '#0b0a17', 'page' => '#f3f4fa', 'surface' => '#ffffff', 'text' => '#121124'],
        'ocean' => ['primary' => '#38bdf8', 'secondary' => '#2563eb', 'sidebar' => '#082f49', 'auth_background' => '#071a2b', 'page' => '#f0f9ff', 'surface' => '#ffffff', 'text' => '#0c4a6e'],
        'emerald' => ['primary' => '#34d399', 'secondary' => '#14b8a6', 'sidebar' => '#064e3b', 'auth_background' => '#022c22', 'page' => '#f0fdf4', 'surface' => '#ffffff', 'text' => '#064e3b'],
        'ember' => ['primary' => '#fb923c', 'secondary' => '#ef4444', 'sidebar' => '#431407', 'auth_background' => '#1c0a05', 'page' => '#fff7ed', 'surface' => '#ffffff', 'text' => '#431407'],
        'slate' => ['primary' => '#a3e635', 'secondary' => '#22d3ee', 'sidebar' => '#1e293b', 'auth_background' => '#0f172a', 'page' => '#f1f5f9', 'surface' => '#ffffff', 'text' => '#0f172a'],
    ];
}

function nexusThemeSettings(?string $root = null): array
{
    $defaults = nexusThemeDefaults();
    $path = nexusThemeSettingsPath($root);
    if (!is_file($path) || is_link($path)) {
        return $defaults;
    }

    $contents = file_get_contents($path);
    if ($contents === false || strlen($contents) > 65536) {
        return $defaults;
    }

    try {
        $saved = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        return is_array($saved) ? nexusThemeValidateSettings($saved) : $defaults;
    } catch (JsonException) {
        return $defaults;
    }
}

function nexusThemeSettingsVersion(?string $root = null): string
{
    $path = nexusThemeSettingsPath($root);
    $hash = is_file($path) ? hash_file('sha256', $path) : false;
    return $hash === false ? 'default' : substr($hash, 0, 12);
}

function nexusThemeIsEnabled(?string $root = null): bool
{
    return !is_file(nexusThemeControlPath($root));
}

function nexusThemeControlIsWritable(?string $root = null): bool
{
    $uploads = nexusThemeUploadsPath($root);
    $paths = [nexusThemeControlPath($root), nexusThemeSettingsPath($root)];
    foreach ($paths as $path) {
        if (is_link($path) || (file_exists($path) && !is_writable($path))) {
            return false;
        }
    }
    return is_writable($uploads);
}

function nexusThemeAtomicWrite(string $path, string $contents, int $mode = 0640): void
{
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory) || is_link($path)) {
        throw new RuntimeException('The Nexus settings location is not writable.');
    }

    $temporary = tempnam($directory, '.nexus-write-');
    if ($temporary === false) {
        throw new RuntimeException('A temporary Nexus settings file could not be created.');
    }

    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The Nexus settings could not be written.');
        }
        @chmod($temporary, $mode);
        if (!@rename($temporary, $path)) {
            throw new RuntimeException('The Nexus settings could not be activated.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
    clearstatcache(true, $path);
}

function nexusThemeSetEnabled(bool $enabled, ?string $root = null): void
{
    $marker = nexusThemeControlPath($root);
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
    if (!is_file($marker)) {
        nexusThemeAtomicWrite($marker, "disabled\n");
    }
}

function nexusThemeCleanText(mixed $value, int $maximum, bool $multiline = false): string
{
    $text = trim(strip_tags((string)$value));
    if (!$multiline) {
        $text = preg_replace('/[\r\n]+/', ' ', $text) ?? '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maximum);
    }
    return substr($text, 0, $maximum);
}

function nexusThemeCleanColor(mixed $value, string $fallback): string
{
    $color = strtolower(trim((string)$value));
    return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : $fallback;
}

function nexusThemeValidateSettings(array $input): array
{
    $defaults = nexusThemeDefaults();
    $presets = nexusThemePresets();
    foreach (['branding', 'content', 'colors', 'appearance'] as $section) {
        if (!isset($input[$section]) || !is_array($input[$section])) {
            $input[$section] = [];
        }
    }
    $preset = isset($input['preset']) && array_key_exists((string)$input['preset'], $presets) ? (string)$input['preset'] : 'custom';
    $result = $defaults;
    $result['preset'] = $preset;

    if ($preset !== 'custom') {
        $input['colors'] = $presets[$preset];
    }

    foreach ($defaults['colors'] as $key => $fallback) {
        $result['colors'][$key] = nexusThemeCleanColor($input['colors'][$key] ?? '', $fallback);
    }

    $result['branding']['brand_name'] = nexusThemeCleanText($input['branding']['brand_name'] ?? '', 80);
    $result['branding']['tagline'] = nexusThemeCleanText($input['branding']['tagline'] ?? '', 140);
    $result['branding']['logo_path'] = nexusThemeCleanText($input['branding']['logo_path'] ?? '', 160);
    if ($result['branding']['logo_path'] !== '' && preg_match('#^/uploads/nexus-theme/logo\.(?:png|jpe?g|webp)$#', $result['branding']['logo_path']) !== 1) {
        $result['branding']['logo_path'] = '';
    }
    $result['branding']['logo_alt'] = nexusThemeCleanText($input['branding']['logo_alt'] ?? '', 120);
    $result['branding']['show_login_logo'] = !empty($input['branding']['show_login_logo']);
    $result['branding']['show_portal_logo'] = !empty($input['branding']['show_portal_logo']);

    $result['content']['login_eyebrow'] = nexusThemeCleanText($input['content']['login_eyebrow'] ?? '', 60);
    $result['content']['login_heading'] = nexusThemeCleanText($input['content']['login_heading'] ?? '', 80);
    $result['content']['login_message'] = nexusThemeCleanText($input['content']['login_message'] ?? '', 240, true);
    $result['content']['portal_heading'] = nexusThemeCleanText($input['content']['portal_heading'] ?? '', 80);
    $result['content']['portal_message'] = nexusThemeCleanText($input['content']['portal_message'] ?? '', 180, true);

    $result['appearance']['radius'] = in_array($input['appearance']['radius'] ?? '', ['sharp', 'balanced', 'rounded'], true) ? $input['appearance']['radius'] : 'balanced';
    $result['appearance']['density'] = in_array($input['appearance']['density'] ?? '', ['compact', 'comfortable', 'spacious'], true) ? $input['appearance']['density'] : 'comfortable';
    $result['appearance']['font_scale'] = max(90, min(110, (int)($input['appearance']['font_scale'] ?? 100)));
    $result['appearance']['reduce_motion'] = !empty($input['appearance']['reduce_motion']);
    return $result;
}

function nexusThemeSaveSettings(array $settings, ?string $root = null): array
{
    $validated = nexusThemeValidateSettings($settings);
    $json = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    nexusThemeAtomicWrite(nexusThemeSettingsPath($root), $json);
    return $validated;
}

function nexusThemeResetSettings(?string $root = null): void
{
    $settingsPath = nexusThemeSettingsPath($root);
    if (is_link($settingsPath)) {
        throw new RuntimeException('The Nexus settings file cannot be a symbolic link.');
    }
    if (is_file($settingsPath) && !@unlink($settingsPath)) {
        throw new RuntimeException('The Nexus settings could not be reset.');
    }
    nexusThemeRemoveLogo($root);
}

function nexusThemeStoreLogo(array $upload, ?string $root = null): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a PNG, JPEG, or WebP logo to upload.');
    }
    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > NEXUS_THEME_MAX_LOGO_BYTES || !is_uploaded_file($temporary)) {
        throw new RuntimeException('The logo must be a valid upload no larger than 3 MB.');
    }

    $image = @getimagesize($temporary);
    if ($image === false || $image[0] < 16 || $image[1] < 16 || $image[0] > 4000 || $image[1] > 2000) {
        throw new RuntimeException('The logo dimensions must be between 16x16 and 4000x2000 pixels.');
    }
    $types = [IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_WEBP => ['image/webp', 'webp']];
    if (!isset($types[$image[2]])) {
        throw new RuntimeException('Only PNG, JPEG, and WebP logos are supported.');
    }
    [$expectedMime, $extension] = $types[$image[2]];
    $detectedMime = (string)($image['mime'] ?? image_type_to_mime_type($image[2]));
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string)$finfo->file($temporary);
    }
    if ($detectedMime !== $expectedMime) {
        throw new RuntimeException('The uploaded logo content does not match its image type.');
    }

    $assetDirectory = nexusThemeAssetPath($root);
    if (is_link($assetDirectory)) {
        throw new RuntimeException('The Nexus asset directory cannot be a symbolic link.');
    }
    if (!is_dir($assetDirectory) && !@mkdir($assetDirectory, 0755, true)) {
        throw new RuntimeException('The Nexus asset directory could not be created.');
    }
    if (!is_writable($assetDirectory)) {
        throw new RuntimeException('The Nexus asset directory is not writable.');
    }

    $target = $assetDirectory . DIRECTORY_SEPARATOR . 'logo.' . $extension;
    if (is_link($target)) {
        throw new RuntimeException('The Nexus logo target cannot be a symbolic link.');
    }
    $staged = tempnam($assetDirectory, '.nexus-logo-');
    if ($staged === false || !@move_uploaded_file($temporary, $staged)) {
        throw new RuntimeException('The uploaded logo could not be staged.');
    }
    try {
        @chmod($staged, 0644);
        if (!@rename($staged, $target)) {
            throw new RuntimeException('The uploaded logo could not be activated.');
        }
    } finally {
        if (is_file($staged)) {
            @unlink($staged);
        }
    }
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExtension) {
        $old = $assetDirectory . DIRECTORY_SEPARATOR . 'logo.' . $oldExtension;
        if ($old !== $target && is_file($old) && !is_link($old)) {
            @unlink($old);
        }
    }
    return '/uploads/' . NEXUS_THEME_ASSET_DIRECTORY . '/logo.' . $extension;
}

function nexusThemeRemoveLogo(?string $root = null): void
{
    $assetDirectory = nexusThemeAssetPath($root);
    if (!is_dir($assetDirectory) || is_link($assetDirectory)) {
        return;
    }
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
        $path = $assetDirectory . DIRECTORY_SEPARATOR . 'logo.' . $extension;
        if (is_file($path) && !is_link($path) && !@unlink($path)) {
            throw new RuntimeException('The custom logo could not be removed.');
        }
    }
}

function nexusThemeBrandName(string $fallback, ?array $settings = null): string
{
    $settings ??= nexusThemeSettings();
    return $settings['branding']['brand_name'] !== '' ? $settings['branding']['brand_name'] : $fallback;
}

function nexusThemeLogoUrl(?array $settings = null, string $fallback = ''): string
{
    $settings ??= nexusThemeSettings();
    return $settings['branding']['logo_path'] !== '' ? $settings['branding']['logo_path'] : $fallback;
}

function nexusThemeRadiusValues(string $choice): array
{
    return match ($choice) {
        'sharp' => ['0rem', '0.15rem'],
        'rounded' => ['0.65rem', '1rem'],
        default => ['0.25rem', '0.5rem'],
    };
}

function nexusThemeContrastColor(string $hex): string
{
    $red = hexdec(substr($hex, 1, 2));
    $green = hexdec(substr($hex, 3, 2));
    $blue = hexdec(substr($hex, 5, 2));
    return (($red * 299 + $green * 587 + $blue * 114) / 1000) >= 145 ? '#0b0a17' : '#ffffff';
}

function nexusThemeMixColors(string $foreground, string $background, int $foregroundPercent): string
{
    $weight = max(0, min(100, $foregroundPercent)) / 100;
    $channels = [];
    for ($offset = 1; $offset <= 5; $offset += 2) {
        $front = hexdec(substr($foreground, $offset, 2));
        $back = hexdec(substr($background, $offset, 2));
        $channels[] = (int)round($front * $weight + $back * (1 - $weight));
    }
    return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
}

function nexusThemeHexRgba(string $hex, float $alpha): string
{
    return sprintf(
        'rgba(%d,%d,%d,%.2F)',
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2)),
        max(0, min(1, $alpha))
    );
}

function nexusThemeBodyClasses(?array $settings = null): string
{
    $settings ??= nexusThemeSettings();
    $classes = ['nexus-density-' . $settings['appearance']['density']];
    if ($settings['appearance']['reduce_motion']) {
        $classes[] = 'nexus-motion-reduced';
    }
    return implode(' ', $classes);
}

function nexusThemeCustomCss(?array $settings = null): string
{
    $settings ??= nexusThemeSettings();
    $colors = $settings['colors'];
    [$radius, $radiusLarge] = nexusThemeRadiusValues($settings['appearance']['radius']);
    $primaryContrast = nexusThemeContrastColor($colors['primary']);
    $variables = [
        '--nexus-cyan' => $colors['primary'], '--nexus-cyan-strong' => nexusThemeMixColors($colors['primary'], '#000000', 88),
        '--nexus-violet' => $colors['secondary'], '--nexus-gradient' => 'linear-gradient(110deg, ' . $colors['secondary'] . ' 0%, ' . $colors['primary'] . ' 100%)',
        '--nexus-night' => $colors['sidebar'], '--nexus-ink' => $colors['auth_background'], '--nexus-elevated' => nexusThemeMixColors($colors['sidebar'], '#ffffff', 88),
        '--nexus-page' => $colors['page'], '--nexus-surface' => $colors['surface'], '--nexus-surface-2' => $colors['page'],
        '--nexus-text' => $colors['text'], '--nexus-copy' => nexusThemeMixColors($colors['text'], $colors['surface'], 72), '--nexus-muted' => nexusThemeMixColors($colors['text'], $colors['surface'], 56),
        '--nexus-border' => nexusThemeMixColors($colors['text'], $colors['surface'], 18), '--nexus-border-dark' => nexusThemeMixColors($colors['sidebar'], '#ffffff', 78),
        '--nexus-cloud' => nexusThemeMixColors('#ffffff', $colors['sidebar'], 82), '--nexus-muted-dark' => nexusThemeMixColors('#ffffff', $colors['sidebar'], 64),
        '--nexus-focus' => '0 0 0 4px ' . nexusThemeHexRgba($colors['primary'], 0.32), '--nexus-info' => $colors['primary'], '--nexus-primary-contrast' => $primaryContrast,
        '--nexus-radius' => $radius, '--nexus-radius-lg' => $radiusLarge,
        '--nexus-font-scale' => (string)$settings['appearance']['font_scale'] . '%',
    ];
    $declarations = [];
    foreach ($variables as $name => $value) {
        $declarations[] = $name . ':' . $value;
    }
    $selector = '.nexus-theme,.nexus-theme.dark-mode,.nexus-theme.nexus-auth,.nexus-theme.nexus-client';
    return 'html{font-size:' . $settings['appearance']['font_scale'] . '%}' . $selector . '{' . implode(';', $declarations) . "}\n";
}

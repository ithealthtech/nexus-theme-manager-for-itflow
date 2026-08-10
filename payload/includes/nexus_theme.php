<?php

declare(strict_types=1);

const NEXUS_MANAGER_VERSION = '3.3.0';
const NEXUS_THEME_VERSION = '26.08.16';
const NEXUS_ITFLOW_COMMIT = '89b080b430aaafba5d520c4e52c57b28a9559085';
const NEXUS_THEME_DISABLED_MARKER = '.nexus-theme-disabled';
const NEXUS_THEME_SETTINGS_FILE = '.nexus-theme-settings.json';
const NEXUS_THEME_PREVIOUS_FILE = '.nexus-theme-settings.previous.json';
const NEXUS_THEME_SAVED_PRESETS_FILE = '.nexus-theme-presets.json';
const NEXUS_THEME_SCHEDULE_FILE = '.nexus-theme-schedule.json';
const NEXUS_THEME_ASSET_DIRECTORY = 'nexus-theme';
const NEXUS_THEME_MAX_LOGO_BYTES = 8388608;
const NEXUS_THEME_MAX_BACKGROUND_BYTES = 8388608;
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

function nexusThemePreviousSettingsPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_PREVIOUS_FILE;
}

function nexusThemeSavedPresetsPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_SAVED_PRESETS_FILE;
}

function nexusThemeSchedulePath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_SCHEDULE_FILE;
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
            'logo_light_path' => '',
            'logo_dark_path' => '',
            'asset_revision' => '0000000000000000',
            'logo_alt' => '',
            'logo_size' => 100,
            'logo_alignment' => 'left',
            'browser_title' => '',
            'favicon_path' => '',
            'login_background_path' => '',
            'login_background_position' => 'center',
            'login_background_overlay' => 55,
            'show_login_logo' => true,
            'show_agent_logo' => true,
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
            'header' => '#0b0a17',
            'header_text' => '#ffffff',
            'auth_background' => '#0b0a17',
            'page' => '#f3f4fa',
            'surface' => '#ffffff',
            'text' => '#121124',
        ],
        'appearance' => [
            'radius' => 'balanced',
            'density' => 'comfortable',
            'menu_density' => 'comfortable',
            'sidebar_width' => 250,
            'sidebar_compact' => false,
            'header_style' => 'solid',
            'navigation_style' => 'pill',
            'font_scale' => 100,
            'motion_style' => 'fluid',
            'reduce_motion' => false,
        ],
    ];
}

function nexusThemePresets(): array
{
    return [
        'aurora' => ['primary' => '#69bff5', 'secondary' => '#7888ff', 'sidebar' => '#121124', 'header' => '#0b0a17', 'header_text' => '#ffffff', 'auth_background' => '#0b0a17', 'page' => '#f3f4fa', 'surface' => '#ffffff', 'text' => '#121124'],
        'ocean' => ['primary' => '#38bdf8', 'secondary' => '#2563eb', 'sidebar' => '#082f49', 'header' => '#071a2b', 'header_text' => '#ffffff', 'auth_background' => '#071a2b', 'page' => '#f0f9ff', 'surface' => '#ffffff', 'text' => '#0c4a6e'],
        'emerald' => ['primary' => '#34d399', 'secondary' => '#14b8a6', 'sidebar' => '#064e3b', 'header' => '#022c22', 'header_text' => '#ffffff', 'auth_background' => '#022c22', 'page' => '#f0fdf4', 'surface' => '#ffffff', 'text' => '#064e3b'],
        'ember' => ['primary' => '#fb923c', 'secondary' => '#ef4444', 'sidebar' => '#431407', 'header' => '#2b0d06', 'header_text' => '#ffffff', 'auth_background' => '#1c0a05', 'page' => '#fff7ed', 'surface' => '#ffffff', 'text' => '#431407'],
        'slate' => ['primary' => '#a3e635', 'secondary' => '#22d3ee', 'sidebar' => '#1e293b', 'header' => '#0f172a', 'header_text' => '#ffffff', 'auth_background' => '#0f172a', 'page' => '#f1f5f9', 'surface' => '#ffffff', 'text' => '#0f172a'],
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
    $paths = [nexusThemeControlPath($root), nexusThemeSettingsPath($root), nexusThemePreviousSettingsPath($root), nexusThemeSavedPresetsPath($root), nexusThemeSchedulePath($root)];
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
    foreach (['logo_light_path' => ['logo-light', 'png|jpe?g|webp|gif'], 'logo_dark_path' => ['logo-dark', 'png|jpe?g|webp|gif'], 'favicon_path' => ['favicon', 'png|jpe?g|webp'], 'login_background_path' => ['login-background', 'png|jpe?g|webp']] as $key => [$file, $extensions]) {
        $legacyLogo = $key === 'logo_light_path' ? $result['branding']['logo_path'] : '';
        $result['branding'][$key] = nexusThemeCleanText($input['branding'][$key] ?? $legacyLogo, 180);
        if ($result['branding'][$key] !== '' && preg_match('#^/uploads/nexus-theme/' . preg_quote($file, '#') . '\\.(?:' . $extensions . ')$#', $result['branding'][$key]) !== 1) {
            $result['branding'][$key] = '';
        }
    }
    $result['branding']['logo_path'] = $result['branding']['logo_light_path'];
    $assetRevision = strtolower(nexusThemeCleanText($input['branding']['asset_revision'] ?? '', 16));
    $result['branding']['asset_revision'] = preg_match('/^[a-f0-9]{16}$/', $assetRevision) === 1
        ? $assetRevision
        : $defaults['branding']['asset_revision'];
    $result['branding']['logo_alt'] = nexusThemeCleanText($input['branding']['logo_alt'] ?? '', 120);
    $result['branding']['logo_size'] = max(50, min(180, (int)($input['branding']['logo_size'] ?? 100)));
    $result['branding']['logo_alignment'] = in_array($input['branding']['logo_alignment'] ?? '', ['left', 'center', 'right'], true) ? $input['branding']['logo_alignment'] : 'left';
    $result['branding']['browser_title'] = nexusThemeCleanText($input['branding']['browser_title'] ?? '', 80);
    $result['branding']['login_background_position'] = in_array($input['branding']['login_background_position'] ?? '', ['top', 'center', 'bottom'], true) ? $input['branding']['login_background_position'] : 'center';
    $result['branding']['login_background_overlay'] = max(0, min(90, (int)($input['branding']['login_background_overlay'] ?? 55)));
    $result['branding']['show_login_logo'] = !empty($input['branding']['show_login_logo']);
    $result['branding']['show_agent_logo'] = array_key_exists('show_agent_logo', $input['branding'])
        ? !empty($input['branding']['show_agent_logo'])
        : $defaults['branding']['show_agent_logo'];
    $result['branding']['show_portal_logo'] = !empty($input['branding']['show_portal_logo']);

    $result['content']['login_eyebrow'] = nexusThemeCleanText($input['content']['login_eyebrow'] ?? '', 60);
    $result['content']['login_heading'] = nexusThemeCleanText($input['content']['login_heading'] ?? '', 80);
    $result['content']['login_message'] = nexusThemeCleanText($input['content']['login_message'] ?? '', 240, true);
    $result['content']['portal_heading'] = nexusThemeCleanText($input['content']['portal_heading'] ?? '', 80);
    $result['content']['portal_message'] = nexusThemeCleanText($input['content']['portal_message'] ?? '', 180, true);

    $result['appearance']['radius'] = in_array($input['appearance']['radius'] ?? '', ['sharp', 'balanced', 'rounded'], true) ? $input['appearance']['radius'] : 'balanced';
    $result['appearance']['density'] = in_array($input['appearance']['density'] ?? '', ['compact', 'comfortable', 'spacious'], true) ? $input['appearance']['density'] : 'comfortable';
    $result['appearance']['menu_density'] = in_array($input['appearance']['menu_density'] ?? '', ['compact', 'comfortable', 'spacious'], true) ? $input['appearance']['menu_density'] : 'comfortable';
    $result['appearance']['sidebar_width'] = max(220, min(340, (int)($input['appearance']['sidebar_width'] ?? 250)));
    $result['appearance']['sidebar_compact'] = !empty($input['appearance']['sidebar_compact']);
    $result['appearance']['header_style'] = in_array($input['appearance']['header_style'] ?? '', ['solid', 'gradient', 'glass'], true) ? $input['appearance']['header_style'] : 'solid';
    $result['appearance']['navigation_style'] = in_array($input['appearance']['navigation_style'] ?? '', ['pill', 'rail', 'outline'], true) ? $input['appearance']['navigation_style'] : 'pill';
    $result['appearance']['font_scale'] = max(90, min(110, (int)($input['appearance']['font_scale'] ?? 100)));
    $result['appearance']['motion_style'] = in_array($input['appearance']['motion_style'] ?? '', ['subtle', 'fluid', 'snappy'], true) ? $input['appearance']['motion_style'] : 'fluid';
    $result['appearance']['reduce_motion'] = !empty($input['appearance']['reduce_motion']);
    return $result;
}

function nexusThemeSaveSettings(array $settings, ?string $root = null, bool $snapshot = true): array
{
    $validated = nexusThemeValidateSettings($settings);
    $json = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $settingsPath = nexusThemeSettingsPath($root);
    if ($snapshot && !is_link($settingsPath)) {
        $current = nexusThemeSettings($root);
        if ($current !== $validated) {
            nexusThemeAtomicWrite(nexusThemePreviousSettingsPath($root), json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        }
    }
    nexusThemeAtomicWrite($settingsPath, $json);
    return $validated;
}

function nexusThemeCanRollback(?string $root = null): bool
{
    $path = nexusThemePreviousSettingsPath($root);
    return is_file($path) && !is_link($path) && filesize($path) <= 65536;
}

function nexusThemeRollback(?string $root = null): array
{
    if (!nexusThemeCanRollback($root)) {
        throw new RuntimeException('No previous Nexus design is available.');
    }
    $previous = json_decode((string)file_get_contents(nexusThemePreviousSettingsPath($root)), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($previous)) {
        throw new RuntimeException('The previous Nexus design is invalid.');
    }
    $current = nexusThemeSettings($root);
    $previous['branding'] = is_array($previous['branding'] ?? null) ? $previous['branding'] : [];
    $previous['branding']['asset_revision'] = bin2hex(random_bytes(8));
    $current['branding']['asset_revision'] = bin2hex(random_bytes(8));
    $restored = nexusThemeSaveSettings($previous, $root, false);
    nexusThemeAtomicWrite(nexusThemePreviousSettingsPath($root), json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    return $restored;
}

function nexusThemeResetSettings(?string $root = null): void
{
    $settingsPath = nexusThemeSettingsPath($root);
    if (is_link($settingsPath)) {
        throw new RuntimeException('The Nexus settings file cannot be a symbolic link.');
    }
    if (is_file($settingsPath)) {
        nexusThemeAtomicWrite(nexusThemePreviousSettingsPath($root), json_encode(nexusThemeSettings($root), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }
    if (is_file($settingsPath) && !@unlink($settingsPath)) {
        throw new RuntimeException('The Nexus settings could not be reset.');
    }
    // Keep uploaded assets available so the immediately preceding design remains fully rollback-safe.
}

function nexusThemeAllowedImageTypes(string $slot): array
{
    $types = [
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
    ];
    if (in_array($slot, ['logo-light', 'logo-dark'], true)) {
        $types[IMAGETYPE_GIF] = ['image/gif', 'gif'];
    }
    return $types;
}

function nexusThemeStoreImage(array $upload, string $slot, ?string $root = null): string
{
    $slots = [
        'logo-light' => [NEXUS_THEME_MAX_LOGO_BYTES, 4000, 2000, 'logo'],
        'logo-dark' => [NEXUS_THEME_MAX_LOGO_BYTES, 4000, 2000, 'logo'],
        'favicon' => [NEXUS_THEME_MAX_LOGO_BYTES, 1024, 1024, 'favicon'],
        'login-background' => [NEXUS_THEME_MAX_BACKGROUND_BYTES, 8000, 8000, 'login background'],
    ];
    if (!isset($slots[$slot])) {
        throw new RuntimeException('Unknown Nexus image slot.');
    }
    [$maximumBytes, $maximumWidth, $maximumHeight, $label] = $slots[$slot];
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a supported image to upload for the ' . $label . '.');
    }
    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > $maximumBytes || !is_uploaded_file($temporary)) {
        throw new RuntimeException('The ' . $label . ' must be a valid upload no larger than ' . (int)ceil($maximumBytes / 1048576) . ' MB.');
    }

    $image = @getimagesize($temporary);
    if ($image === false || $image[0] < 16 || $image[1] < 16 || $image[0] > $maximumWidth || $image[1] > $maximumHeight) {
        throw new RuntimeException('The ' . $label . ' dimensions are outside the supported range.');
    }
    $types = nexusThemeAllowedImageTypes($slot);
    if (!isset($types[$image[2]])) {
        throw new RuntimeException(in_array($slot, ['logo-light', 'logo-dark'], true)
            ? 'Logos must be PNG, JPEG, WebP, or GIF. Animated GIF timing is preserved.'
            : 'Only PNG, JPEG, and WebP images are supported for this asset.');
    }
    [$expectedMime, $extension] = $types[$image[2]];
    $detectedMime = (string)($image['mime'] ?? image_type_to_mime_type($image[2]));
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string)$finfo->file($temporary);
    }
    if ($detectedMime !== $expectedMime) {
        throw new RuntimeException('The uploaded image content does not match its image type.');
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

    $target = $assetDirectory . DIRECTORY_SEPARATOR . $slot . '.' . $extension;
    if (is_link($target)) {
        throw new RuntimeException('The Nexus image target cannot be a symbolic link.');
    }
    $staged = tempnam($assetDirectory, '.nexus-image-');
    if ($staged === false || !@move_uploaded_file($temporary, $staged)) {
        throw new RuntimeException('The uploaded image could not be staged.');
    }
    try {
        @chmod($staged, 0644);
        if (!@rename($staged, $target)) {
            throw new RuntimeException('The uploaded image could not be activated.');
        }
    } finally {
        if (is_file($staged)) {
            @unlink($staged);
        }
    }
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $oldExtension) {
        $old = $assetDirectory . DIRECTORY_SEPARATOR . $slot . '.' . $oldExtension;
        if ($old !== $target && is_file($old) && !is_link($old)) {
            @unlink($old);
        }
    }
    return '/uploads/' . NEXUS_THEME_ASSET_DIRECTORY . '/' . $slot . '.' . $extension;
}

function nexusThemeStoreLogo(array $upload, ?string $root = null): string
{
    return nexusThemeStoreImage($upload, 'logo-light', $root);
}

function nexusThemeRemoveImage(string $slot, ?string $root = null): void
{
    if (!in_array($slot, ['logo-light', 'logo-dark', 'favicon', 'login-background', 'logo'], true)) {
        throw new RuntimeException('Unknown Nexus image slot.');
    }
    $assetDirectory = nexusThemeAssetPath($root);
    if (!is_dir($assetDirectory) || is_link($assetDirectory)) {
        return;
    }
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
        $path = $assetDirectory . DIRECTORY_SEPARATOR . $slot . '.' . $extension;
        if (is_file($path) && !is_link($path) && !@unlink($path)) {
            throw new RuntimeException('The custom image could not be removed.');
        }
    }
}

function nexusThemeRemoveLogo(?string $root = null): void
{
    nexusThemeRemoveImage('logo-light', $root);
    nexusThemeRemoveImage('logo-dark', $root);
    nexusThemeRemoveImage('logo', $root);
}

function nexusThemeBrandName(string $fallback, ?array $settings = null): string
{
    $settings ??= nexusThemeSettings();
    return $settings['branding']['brand_name'] !== '' ? $settings['branding']['brand_name'] : $fallback;
}

function nexusThemeLogoUrl(?array $settings = null, string $fallback = '', string $variant = 'light'): string
{
    $settings ??= nexusThemeSettings();
    $key = $variant === 'dark' ? 'logo_dark_path' : 'logo_light_path';
    $preferred = (string)($settings['branding'][$key] ?? '');
    $alternate = (string)($settings['branding'][$variant === 'dark' ? 'logo_light_path' : 'logo_dark_path'] ?? '');
    $legacy = (string)($settings['branding']['logo_path'] ?? '');
    return $preferred !== '' ? $preferred : ($alternate !== '' ? $alternate : ($legacy !== '' ? $legacy : $fallback));
}

function nexusThemeVersionedAssetUrl(string $url, ?array $settings = null): string
{
    if ($url === '' || preg_match('#^/uploads/nexus-theme/(?:logo-light|logo-dark|favicon|login-background)\.(?:png|jpe?g|webp|gif)$#', $url) !== 1) {
        return $url;
    }
    $settings ??= nexusThemeSettings();
    $revision = (string)($settings['branding']['asset_revision'] ?? '');
    if (preg_match('/^[a-f0-9]{16}$/', $revision) !== 1) {
        $revision = nexusThemeDefaults()['branding']['asset_revision'];
    }
    return $url . '?v=' . $revision;
}

function nexusThemeLogoVariantForColor(string $background): string
{
    return nexusThemeContrastColor($background) === '#0b0a17' ? 'dark' : 'light';
}

function nexusThemePageTitle(string $fallbackBrand, string $suffix = '', ?array $settings = null): string
{
    $settings ??= nexusThemeSettings();
    $base = $settings['branding']['browser_title'] !== '' ? $settings['branding']['browser_title'] : nexusThemeBrandName($fallbackBrand, $settings);
    return $suffix !== '' ? $base . ' | ' . $suffix : $base;
}

function nexusThemeFaviconUrl(?array $settings = null, string $fallback = ''): string
{
    $settings ??= nexusThemeSettings();
    return $settings['branding']['favicon_path'] !== '' ? $settings['branding']['favicon_path'] : $fallback;
}

function nexusThemeSavedPresets(?string $root = null): array
{
    $path = nexusThemeSavedPresetsPath($root);
    if (!is_file($path) || is_link($path) || filesize($path) > 262144) {
        return [];
    }
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 48, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    if (($decoded['schema'] ?? null) !== 1 || !is_array($decoded['presets'] ?? null)) {
        return [];
    }
    $result = [];
    foreach (array_slice($decoded['presets'], 0, 20) as $preset) {
        if (!is_array($preset) || preg_match('/^[a-f0-9]{16}$/', (string)($preset['id'] ?? '')) !== 1 || !is_array($preset['settings'] ?? null)) {
            continue;
        }
        $result[] = [
            'id' => (string)$preset['id'],
            'name' => nexusThemeCleanText($preset['name'] ?? 'Untitled preset', 50),
            'created_at' => nexusThemeCleanText($preset['created_at'] ?? '', 40),
            'settings' => nexusThemeValidateSettings($preset['settings']),
        ];
    }
    return $result;
}

function nexusThemeWriteSavedPresets(array $presets, ?string $root = null): void
{
    nexusThemeAtomicWrite(nexusThemeSavedPresetsPath($root), json_encode(['schema' => 1, 'presets' => array_values(array_slice($presets, 0, 20))], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}

function nexusThemeSavePreset(string $name, array $settings, ?string $root = null): string
{
    $name = nexusThemeCleanText($name, 50);
    if ($name === '') {
        throw new RuntimeException('Enter a name for the preset.');
    }
    $presets = nexusThemeSavedPresets($root);
    if (count($presets) >= 20) {
        throw new RuntimeException('Theme Studio supports up to 20 saved presets.');
    }
    $id = bin2hex(random_bytes(8));
    $presets[] = ['id' => $id, 'name' => $name, 'created_at' => gmdate('c'), 'settings' => nexusThemeValidateSettings($settings)];
    nexusThemeWriteSavedPresets($presets, $root);
    return $id;
}

function nexusThemeDeletePreset(string $id, ?string $root = null): void
{
    $presets = array_values(array_filter(nexusThemeSavedPresets($root), static fn(array $preset): bool => $preset['id'] !== $id));
    nexusThemeWriteSavedPresets($presets, $root);
}

function nexusThemeApplyPreset(string $id, ?string $root = null): array
{
    foreach (nexusThemeSavedPresets($root) as $preset) {
        if ($preset['id'] !== $id) {
            continue;
        }
        $current = nexusThemeSettings($root);
        foreach (['logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path', 'asset_revision'] as $asset) {
            $preset['settings']['branding'][$asset] = $current['branding'][$asset];
        }
        return nexusThemeSaveSettings($preset['settings'], $root);
    }
    throw new RuntimeException('The selected preset no longer exists.');
}

function nexusThemeExportPresets(?string $root = null): string
{
    return json_encode(['schema' => 1, 'kind' => 'nexus-theme-presets', 'presets' => nexusThemeSavedPresets($root)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function nexusThemeImportPresets(string $json, ?string $root = null): int
{
    $decoded = json_decode($json, true, 48, JSON_THROW_ON_ERROR);
    if (($decoded['schema'] ?? null) !== 1 || ($decoded['kind'] ?? null) !== 'nexus-theme-presets' || !is_array($decoded['presets'] ?? null)) {
        throw new RuntimeException('This is not a Nexus saved-presets export.');
    }
    $existing = nexusThemeSavedPresets($root);
    $count = 0;
    foreach ($decoded['presets'] as $preset) {
        if (count($existing) >= 20 || !is_array($preset) || !is_array($preset['settings'] ?? null)) {
            break;
        }
        $name = nexusThemeCleanText($preset['name'] ?? '', 50);
        if ($name === '') {
            continue;
        }
        $existing[] = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'created_at' => gmdate('c'), 'settings' => nexusThemeValidateSettings($preset['settings'])];
        $count++;
    }
    nexusThemeWriteSavedPresets($existing, $root);
    return $count;
}

function nexusThemeSchedule(?string $root = null): ?array
{
    $path = nexusThemeSchedulePath($root);
    if (!is_file($path) || is_link($path) || filesize($path) > 4096) {
        return null;
    }
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    $action = (string)($decoded['action'] ?? '');
    $activateAt = (string)($decoded['activate_at'] ?? '');
    if (($decoded['schema'] ?? null) !== 1 || !in_array($action, ['enable', 'disable'], true) || preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/', $activateAt) !== 1) {
        return null;
    }
    return ['schema' => 1, 'action' => $action, 'activate_at' => $activateAt, 'created_at' => nexusThemeCleanText($decoded['created_at'] ?? '', 40)];
}

function nexusThemeSetSchedule(string $action, string $activateAt, ?string $root = null): array
{
    if (!in_array($action, ['enable', 'disable'], true)) {
        throw new RuntimeException('Choose whether the scheduled action enables or pauses Nexus.');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/', $activateAt) !== 1) {
        throw new RuntimeException('The activation time must be a UTC timestamp supplied by Theme Studio.');
    }
    $timestamp = strtotime($activateAt);
    if ($timestamp === false || $timestamp <= time() || $timestamp > time() + 366 * 86400) {
        throw new RuntimeException('Choose a future activation time within the next year.');
    }
    $schedule = ['schema' => 1, 'action' => $action, 'activate_at' => gmdate('Y-m-d\\TH:i:s\\Z', $timestamp), 'created_at' => gmdate('c')];
    nexusThemeAtomicWrite(nexusThemeSchedulePath($root), json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    return $schedule;
}

function nexusThemeCancelSchedule(?string $root = null): void
{
    $path = nexusThemeSchedulePath($root);
    if (is_link($path) || (is_file($path) && !@unlink($path))) {
        throw new RuntimeException('The scheduled theme action could not be cancelled.');
    }
}

function nexusThemeApplyDueSchedule(?string $root = null): bool
{
    $schedule = nexusThemeSchedule($root);
    if ($schedule === null || strtotime($schedule['activate_at']) > time()) {
        return false;
    }
    try {
        nexusThemeSetEnabled($schedule['action'] === 'enable', $root);
        nexusThemeCancelSchedule($root);
        return true;
    } catch (Throwable) {
        // A control-file permission problem must never take the ITFlow request down.
        return false;
    }
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
    $classes = [
        'nexus-density-' . $settings['appearance']['density'],
        'nexus-menu-' . $settings['appearance']['menu_density'],
        'nexus-header-' . $settings['appearance']['header_style'],
        'nexus-navigation-' . $settings['appearance']['navigation_style'],
        'nexus-motion-' . $settings['appearance']['motion_style'],
    ];
    if ($settings['appearance']['sidebar_compact']) {
        $classes[] = 'nexus-sidebar-compact';
    }
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
        '--nexus-night' => $colors['sidebar'], '--nexus-header' => $colors['header'], '--nexus-header-text' => $colors['header_text'], '--nexus-ink' => $colors['auth_background'], '--nexus-elevated' => nexusThemeMixColors($colors['sidebar'], '#ffffff', 88),
        '--nexus-page' => $colors['page'], '--nexus-surface' => $colors['surface'], '--nexus-surface-2' => $colors['page'],
        '--nexus-text' => $colors['text'], '--nexus-copy' => nexusThemeMixColors($colors['text'], $colors['surface'], 72), '--nexus-muted' => nexusThemeMixColors($colors['text'], $colors['surface'], 56),
        '--nexus-border' => nexusThemeMixColors($colors['text'], $colors['surface'], 18), '--nexus-border-dark' => nexusThemeMixColors($colors['sidebar'], '#ffffff', 78),
        '--nexus-cloud' => nexusThemeMixColors('#ffffff', $colors['sidebar'], 82), '--nexus-muted-dark' => nexusThemeMixColors('#ffffff', $colors['sidebar'], 64),
        '--nexus-focus' => '0 0 0 4px ' . nexusThemeHexRgba($colors['primary'], 0.32), '--nexus-info' => $colors['primary'], '--nexus-primary-contrast' => $primaryContrast,
        '--nexus-radius' => $radius, '--nexus-radius-lg' => $radiusLarge,
        '--nexus-font-scale' => (string)$settings['appearance']['font_scale'] . '%',
        '--nexus-sidebar-width' => (string)$settings['appearance']['sidebar_width'] . 'px',
        '--nexus-logo-size' => (string)$settings['branding']['logo_size'] . '%',
        '--nexus-logo-position' => $settings['branding']['logo_alignment'],
    ];
    $declarations = [];
    foreach ($variables as $name => $value) {
        $declarations[] = $name . ':' . $value;
    }
    $selector = '.nexus-theme,.nexus-theme.dark-mode,.nexus-theme.nexus-auth,.nexus-theme.nexus-client';
    $css = 'html{font-size:' . $settings['appearance']['font_scale'] . '%}' . $selector . '{' . implode(';', $declarations) . "}\n";
    $logo = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', nexusThemeLogoVariantForColor($colors['header'])), $settings);
    if ($settings['branding']['show_agent_logo'] && $logo !== '') {
        $alignment = $settings['branding']['logo_alignment'];
        $justify = $alignment === 'center' ? 'center' : ($alignment === 'right' ? 'flex-end' : 'flex-start');
        $logoWidth = number_format(11 * $settings['branding']['logo_size'] / 100, 2, '.', '') . 'rem';
        $css .= '.nexus-agent .brand-link[href="/agent/dashboard.php"]{justify-content:' . $justify . '}.nexus-agent .brand-link[href="/agent/dashboard.php"] .brand-image{background-color:transparent;background-image:url("' . $logo . '");background-position:' . $alignment . ' center;background-repeat:no-repeat;background-size:contain;border-radius:0;flex:0 1 ' . $logoWidth . ';height:2.6rem;margin-right:0;max-width:100%;width:100%}'
            . '.nexus-agent .brand-link[href="/agent/dashboard.php"] .brand-image::before{content:none}'
            . '.nexus-agent .brand-link[href="/agent/dashboard.php"] .brand-text{clip:rect(0 0 0 0);clip-path:inset(50%);height:1px;overflow:hidden;position:absolute;white-space:nowrap;width:1px}'
            . '@media (min-width:992px){.nexus-agent.sidebar-collapse .brand-link[href="/agent/dashboard.php"] .brand-image{flex:0 0 2rem;height:2rem;width:2rem}}' . "\n";
    }
    $logoJustify = $settings['branding']['logo_alignment'] === 'center' ? 'center' : ($settings['branding']['logo_alignment'] === 'right' ? 'flex-end' : 'flex-start');
    $authLogoHeight = number_format(5.5 * $settings['branding']['logo_size'] / 100, 2, '.', '') . 'rem';
    $clientLogoHeight = number_format(3 * $settings['branding']['logo_size'] / 100, 2, '.', '') . 'rem';
    $clientLogoMargin = $settings['branding']['logo_alignment'] === 'center' ? '0 auto' : ($settings['branding']['logo_alignment'] === 'right' ? '0 0 0 auto' : '0 auto 0 0');
    $css .= '.nexus-auth .login-logo{align-items:center;display:flex;justify-content:' . $logoJustify . '}.nexus-auth .login-logo img{max-height:' . $authLogoHeight . '}.nexus-client .nexus-client-nav-logo{max-height:' . $clientLogoHeight . '}.nexus-client .nexus-client-logo{display:block;float:none!important;margin:' . $clientLogoMargin . ';max-height:' . $clientLogoHeight . ';object-fit:contain}' . "\n";
    if ($settings['branding']['login_background_path'] !== '') {
        $overlay = number_format($settings['branding']['login_background_overlay'] / 100, 2, '.', '');
        $background = nexusThemeVersionedAssetUrl($settings['branding']['login_background_path'], $settings);
        $position = $settings['branding']['login_background_position'];
        $css .= '.nexus-auth{background-image:linear-gradient(rgba(0,0,0,' . $overlay . '),rgba(0,0,0,' . $overlay . ')),url("' . $background . '")!important;background-position:center,' . $position . ' center!important;background-repeat:no-repeat!important;background-size:cover!important}' . "\n";
    }
    $css .= '@media (min-width:992px){.nexus-agent:not(.sidebar-collapse) .main-sidebar{width:var(--nexus-sidebar-width)}.nexus-agent:not(.sidebar-collapse) .content-wrapper,.nexus-agent:not(.sidebar-collapse) .main-header,.nexus-agent:not(.sidebar-collapse) .main-footer{margin-left:var(--nexus-sidebar-width)}}' . "\n";
    return $css;
}

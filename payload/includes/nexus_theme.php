<?php

declare(strict_types=1);

const NEXUS_MANAGER_VERSION = '3.9.1';
const NEXUS_THEME_VERSION = '26.08.24';
const NEXUS_ITFLOW_COMMIT = '89b080b430aaafba5d520c4e52c57b28a9559085';
const NEXUS_THEME_DISABLED_MARKER = '.nexus-theme-disabled';
const NEXUS_THEME_SETTINGS_FILE = '.nexus-theme-settings.json';
const NEXUS_THEME_DRAFT_FILE = '.nexus-theme-draft.json';
const NEXUS_THEME_PREVIOUS_FILE = '.nexus-theme-settings.previous.json';
const NEXUS_THEME_REVISIONS_FILE = '.nexus-theme-revisions.json';
const NEXUS_THEME_STATE_LOCK_FILE = '.nexus-theme-state.lock';
const NEXUS_THEME_SAVED_PRESETS_FILE = '.nexus-theme-presets.json';
const NEXUS_THEME_SCHEDULE_FILE = '.nexus-theme-schedule.json';
const NEXUS_THEME_ASSET_DIRECTORY = 'nexus-theme';
const NEXUS_THEME_MAX_REVISIONS = 50;
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

function nexusThemeDraftPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_DRAFT_FILE;
}

function nexusThemeRevisionsPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_REVISIONS_FILE;
}

function nexusThemeStateLockPath(?string $root = null): string
{
    return nexusThemeUploadsPath($root) . DIRECTORY_SEPARATOR . NEXUS_THEME_STATE_LOCK_FILE;
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
        'phase_label' => nexusUpdaterReady($root) ? 'Ready' : 'Setup required',
        'progress' => 0,
        'action' => null,
        'can_retry' => false,
        'recovery' => null,
        'rollback_state' => null,
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
    $stale = false;
    if (in_array($state, ['checking', 'running'], true)
        && ($updatedTimestamp === false || $updatedTimestamp < time() - 1200)) {
        $state = 'failed';
        $stale = true;
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
    $phaseLabels = [
        'checking' => 'Checking release',
        'check_complete' => 'Check complete',
        'download' => 'Downloading package',
        'verify' => 'Verifying release',
        'stage' => 'Staging package',
        'backup' => 'Protecting current version',
        'transition' => 'Preparing transition',
        'install' => 'Installing Nexus',
        'health_check' => 'Running health checks',
        'finalize' => 'Finalizing update',
        'complete' => 'Update complete',
        'rollback' => 'Restoring previous version',
        'rollback_complete' => 'Previous version restored',
        'rollback_failed' => 'Rollback needs attention',
        'registration_failed' => 'Updater repair required',
        'failed' => 'Update stopped',
    ];
    $phase = (string)($saved['phase'] ?? '');
    if (!array_key_exists($phase, $phaseLabels)) {
        $phase = '';
    }
    $defaultProgress = in_array($state, ['update_available', 'up_to_date', 'completed'], true) ? 100 : 0;
    $progress = is_int($saved['progress'] ?? null) ? max(0, min(100, $saved['progress'])) : $defaultProgress;
    $action = in_array($saved['action'] ?? '', ['check', 'update'], true) ? $saved['action'] : null;
    $rollbackState = in_array($saved['rollback_state'] ?? '', ['restored', 'failed'], true) ? $saved['rollback_state'] : null;
    $recovery = nexusThemeCleanText($saved['recovery'] ?? '', 320);
    if ($stale && $recovery === '') {
        $recovery = 'The protected updater stopped reporting. Confirm the service is available, then retry the previous action.';
    }
    return [
        'state' => $state,
        'message' => $message,
        'current_version' => $version($saved['current_version'] ?? '') ?? NEXUS_MANAGER_VERSION,
        'latest_version' => $version($saved['latest_version'] ?? ''),
        'updated_at' => $updatedAt,
        'release_url' => $releaseUrl !== '' ? $releaseUrl : null,
        'phase' => $phase !== '' ? $phase : null,
        'phase_label' => $phase !== '' ? $phaseLabels[$phase] : ucfirst(str_replace('_', ' ', $state)),
        'progress' => $progress,
        'action' => $action,
        'can_retry' => $stale || ($saved['can_retry'] ?? false) === true,
        'recovery' => $recovery !== '' ? $recovery : null,
        'rollback_state' => $rollbackState,
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
        'profiles' => array_fill_keys(['technician', 'client', 'auth', 'guest', 'print'], [
            'enabled' => false,
            'colors' => [],
            'appearance' => [],
        ]),
        'navigation' => [
            'desktop' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'href' => '/agent/dashboard.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'clients', 'label' => 'Clients', 'icon' => 'fas fa-users', 'href' => '/agent/clients.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'tickets', 'label' => 'Tickets', 'icon' => 'fas fa-ticket-alt', 'href' => '/agent/tickets.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'href' => '/agent/projects.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'invoices', 'label' => 'Invoices', 'icon' => 'fas fa-file-invoice', 'href' => '/agent/invoices.php', 'roles' => ['admin'], 'visible' => true],
                ['id' => 'reports', 'label' => 'Reports', 'icon' => 'fas fa-chart-line', 'href' => '/agent/reports.php', 'roles' => ['admin'], 'visible' => true],
            ],
            'mobile' => [
                ['id' => 'dashboard', 'label' => 'Home', 'icon' => 'fas fa-home', 'href' => '/agent/dashboard.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'tickets', 'label' => 'Tickets', 'icon' => 'fas fa-ticket-alt', 'href' => '/agent/tickets.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'clients', 'label' => 'Clients', 'icon' => 'fas fa-users', 'href' => '/agent/clients.php', 'roles' => ['admin', 'tech'], 'visible' => true],
                ['id' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'href' => '/agent/projects.php', 'roles' => ['admin', 'tech'], 'visible' => true],
            ],
        ],
        'dark_mode' => [
            'mode' => 'system',
            'user_selectable' => true,
            'schedule_start' => '19:00',
            'schedule_end' => '07:00',
            'colors' => ['primary' => '#69bff5', 'secondary' => '#7888ff', 'sidebar' => '#090817', 'header' => '#070611', 'header_text' => '#ffffff', 'auth_background' => '#070611', 'page' => '#0f1020', 'surface' => '#17182b', 'text' => '#f7f8ff'],
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
    $paths = [nexusThemeControlPath($root), nexusThemeSettingsPath($root), nexusThemeDraftPath($root), nexusThemePreviousSettingsPath($root), nexusThemeRevisionsPath($root), nexusThemeStateLockPath($root), nexusThemeSavedPresetsPath($root), nexusThemeSchedulePath($root)];
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

function nexusThemeWithStateLock(callable $operation, ?string $root = null): mixed
{
    $path = nexusThemeStateLockPath($root);
    if (is_link($path)) {
        throw new RuntimeException('The Nexus state lock cannot be a symbolic link.');
    }
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('The Nexus state lock could not be opened.');
    }
    @chmod($path, 0640);
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('The Nexus state lock could not be acquired.');
        }
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }
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
    foreach (['branding', 'content', 'colors', 'appearance', 'profiles', 'navigation', 'dark_mode'] as $section) {
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
        if ($result['branding'][$key] !== '' && preg_match('#^/uploads/nexus-theme/' . preg_quote($file, '#') . '(?:-[a-f0-9]{16})?\\.(?:' . $extensions . ')$#', $result['branding'][$key]) !== 1) {
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

    foreach (array_keys($defaults['profiles']) as $surface) {
        $profile = is_array($input['profiles'][$surface] ?? null) ? $input['profiles'][$surface] : [];
        $result['profiles'][$surface]['enabled'] = !empty($profile['enabled']);
        $result['profiles'][$surface]['colors'] = [];
        foreach ($defaults['colors'] as $key => $fallback) {
            if (isset($profile['colors'][$key]) && trim((string)$profile['colors'][$key]) !== '') {
                $result['profiles'][$surface]['colors'][$key] = nexusThemeCleanColor($profile['colors'][$key], $fallback);
            }
        }
        $result['profiles'][$surface]['appearance'] = [];
        foreach (['density', 'menu_density', 'header_style', 'navigation_style'] as $key) {
            if (isset($profile['appearance'][$key]) && trim((string)$profile['appearance'][$key]) !== '') {
                $allowed = $key === 'header_style' ? ['solid', 'gradient', 'glass'] : ($key === 'navigation_style' ? ['pill', 'rail', 'outline'] : ['compact', 'comfortable', 'spacious']);
                if (in_array($profile['appearance'][$key], $allowed, true)) {
                    $result['profiles'][$surface]['appearance'][$key] = $profile['appearance'][$key];
                }
            }
        }
    }

    $navigationDefaultsById = [];
    foreach (array_merge($defaults['navigation']['desktop'], $defaults['navigation']['mobile']) as $item) {
        if (!isset($navigationDefaultsById[$item['id']])) $navigationDefaultsById[$item['id']] = $item;
    }
    foreach (['desktop', 'mobile'] as $viewport) {
        $submittedItems = is_array($input['navigation'][$viewport] ?? null) ? $input['navigation'][$viewport] : [];
        $result['navigation'][$viewport] = [];
        $seen = [];
        foreach (array_slice($submittedItems, 0, 24) as $item) {
            if (!is_array($item)) continue;
            $id = strtolower(nexusThemeCleanText($item['id'] ?? '', 32));
            if (!isset($navigationDefaultsById[$id]) || isset($seen[$id])) continue;
            $base = $navigationDefaultsById[$id];
            $roles = array_values(array_intersect(['admin', 'tech'], is_array($item['roles'] ?? null) ? $item['roles'] : []));
            $result['navigation'][$viewport][] = [
                'id' => $id,
                'label' => nexusThemeCleanText($item['label'] ?? $base['label'], 32),
                'icon' => preg_match('/^(?:fas|far) fa-[a-z0-9-]{2,40}$/', (string)($item['icon'] ?? '')) === 1 ? (string)$item['icon'] : $base['icon'],
                'href' => $base['href'],
                'roles' => $roles === [] ? $base['roles'] : $roles,
                'visible' => !empty($item['visible']),
            ];
            $seen[$id] = true;
        }
        foreach ($defaults['navigation'][$viewport] as $item) {
            if (!isset($seen[$item['id']])) $result['navigation'][$viewport][] = $item;
        }
    }

    $dark = is_array($input['dark_mode'] ?? null) ? $input['dark_mode'] : [];
    $result['dark_mode']['mode'] = in_array($dark['mode'] ?? '', ['light', 'dark', 'system', 'scheduled'], true) ? $dark['mode'] : 'system';
    $result['dark_mode']['user_selectable'] = !empty($dark['user_selectable']);
    foreach (['schedule_start', 'schedule_end'] as $key) {
        $candidate = (string)($dark[$key] ?? $defaults['dark_mode'][$key]);
        $result['dark_mode'][$key] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $candidate) === 1 ? $candidate : $defaults['dark_mode'][$key];
    }
    foreach ($defaults['dark_mode']['colors'] as $key => $fallback) {
        $result['dark_mode']['colors'][$key] = nexusThemeCleanColor($dark['colors'][$key] ?? '', $fallback);
    }
    return $result;
}

function nexusThemeSettingsForSurface(array $settings, string $surface): array
{
    $settings = nexusThemeValidateSettings($settings);
    if (!isset($settings['profiles'][$surface]) || !$settings['profiles'][$surface]['enabled']) return $settings;
    foreach (['colors', 'appearance'] as $section) {
        foreach ($settings['profiles'][$surface][$section] as $key => $value) $settings[$section][$key] = $value;
    }
    return $settings;
}

function nexusThemeDarkModeState(array $settings, ?string $preference = null, ?int $timestamp = null): string
{
    $settings = nexusThemeValidateSettings($settings);
    if ($settings['dark_mode']['user_selectable'] && in_array($preference, ['light', 'dark', 'system'], true)) return $preference;
    $mode = $settings['dark_mode']['mode'];
    if ($mode !== 'scheduled') return $mode;
    $now = (int)date('Hi', $timestamp ?? time());
    $start = (int)str_replace(':', '', $settings['dark_mode']['schedule_start']);
    $end = (int)str_replace(':', '', $settings['dark_mode']['schedule_end']);
    $dark = $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
    return $dark ? 'dark' : 'light';
}

function nexusThemeDarkModeClasses(array $settings): string
{
    $cookie = isset($_COOKIE['nexus_color_mode']) ? (string)$_COOKIE['nexus_color_mode'] : null;
    $mode = nexusThemeDarkModeState($settings, $cookie);
    return 'nexus-color-mode-' . $mode . ($mode === 'dark' ? ' dark-mode' : '');
}

function nexusThemeNavigationItems(array $settings, string $viewport = 'desktop', string $role = 'tech'): array
{
    $settings = nexusThemeValidateSettings($settings);
    $viewport = $viewport === 'mobile' ? 'mobile' : 'desktop';
    $role = strtolower($role) === 'admin' ? 'admin' : 'tech';
    return array_values(array_filter($settings['navigation'][$viewport], static fn(array $item): bool => $item['visible'] && in_array($role, $item['roles'], true)));
}

function nexusThemeNavigationScript(array $settings, string $role = 'tech'): string
{
    $payload = json_encode([
        'desktop' => nexusThemeNavigationItems($settings, 'desktop', $role),
        'mobile' => nexusThemeNavigationItems($settings, 'mobile', $role),
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    return '(function(){"use strict";var c=' . $payload . ';function apply(items){var nav=document.querySelector(".nav-sidebar");if(!nav)return;var links=Array.prototype.slice.call(nav.querySelectorAll("a.nav-link[href]")),byId={};links.forEach(function(a){var h=a.getAttribute("href")||"",hb=h.split(/[?#]/)[0].split("/").pop();items.forEach(function(i){var ib=i.href.split("/").pop();if(h===i.href||hb===ib)byId[i.id]=a.closest("li.nav-item");});});var group=nav.querySelector("[data-nexus-nav-group]");if(!group){group=document.createElement("li");group.className="nav-header";group.dataset.nexusNavGroup="1";group.textContent="CUSTOM NAVIGATION";nav.appendChild(group);}items.forEach(function(i){var li=byId[i.id];if(!li)return;li.hidden=false;var p=li.querySelector("p");if(p&&p.childNodes.length)p.childNodes[0].nodeValue=i.label+" ";var icon=li.querySelector(".nav-icon");if(icon)icon.className="nav-icon "+i.icon;nav.appendChild(li);});Object.keys(byId).forEach(function(id){if(!items.some(function(i){return i.id===id;}))byId[id].hidden=true;});}function run(){apply(matchMedia("(max-width: 991px)").matches?c.mobile:c.desktop);}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",run):run();matchMedia("(max-width: 991px)").addEventListener&&matchMedia("(max-width: 991px)").addEventListener("change",run);})();';
}

function nexusThemeColorModeScript(array $settings): string
{
    $settings = nexusThemeValidateSettings($settings);
    $lightLogo = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', 'light'), $settings);
    $darkLogo = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', 'dark'), $settings);
    $config = json_encode(['mode' => $settings['dark_mode']['mode'], 'selectable' => $settings['dark_mode']['user_selectable'], 'light_logo' => $lightLogo, 'dark_logo' => $darkLogo], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    return '(function(){"use strict";var c=' . $config . ',k="nexus_color_mode",stored=null;try{stored=localStorage.getItem(k);}catch(e){}var v=c.selectable?stored:c.mode;if(!/^(light|dark|system)$/.test(v||""))v=c.mode;document.documentElement.dataset.nexusColorMode=v;try{if(c.selectable)document.cookie=k+"="+v+"; path=/; max-age=31536000; SameSite=Lax";}catch(e){}function applyLogo(){var systemDark=matchMedia("(prefers-color-scheme:dark)").matches,bodyDark=document.body&&document.body.classList.contains("nexus-color-mode-dark"),isDark=v==="dark"||(v==="system"&&systemDark)||(v==="scheduled"&&bodyDark),src=isDark?c.light_logo:c.dark_logo;if(src)document.querySelectorAll("img[data-nexus-color-logo]").forEach(function(img){if(img.getAttribute("src")!==src)img.setAttribute("src",src);});}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",applyLogo):applyLogo();var media=matchMedia("(prefers-color-scheme:dark)");if(media.addEventListener)media.addEventListener("change",applyLogo);if(c.selectable)window.nexusSetColorMode=function(n){if(!/^(light|dark|system)$/.test(n))return;try{localStorage.setItem(k,n);document.cookie=k+"="+n+"; path=/; max-age=31536000; SameSite=Lax";}catch(e){}location.reload();};})();';
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

function nexusThemeDraftSettings(?string $root = null): ?array
{
    $path = nexusThemeDraftPath($root);
    if (!is_file($path) || is_link($path) || filesize($path) > 65536) {
        return null;
    }
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? nexusThemeValidateSettings($decoded) : null;
    } catch (JsonException) {
        return null;
    }
}

function nexusThemeHasDraft(?string $root = null): bool
{
    $draft = nexusThemeDraftSettings($root);
    return $draft !== null && $draft !== nexusThemeSettings($root);
}

function nexusThemeDraftVersion(?string $root = null): string
{
    $path = nexusThemeDraftPath($root);
    $hash = is_file($path) && !is_link($path) ? hash_file('sha256', $path) : false;
    return $hash === false ? 'none' : substr($hash, 0, 16);
}

function nexusThemeAssertDraftVersion(?string $expectedVersion, ?string $root = null): void
{
    if ($expectedVersion !== null && !hash_equals(nexusThemeDraftVersion($root), $expectedVersion)) {
        throw new RuntimeException('The Nexus draft changed in another administrator session. Reload Theme Studio before continuing.');
    }
}

function nexusThemeSaveDraftSettingsUnlocked(array $settings, ?string $root = null, ?string $expectedVersion = null): array
{
    nexusThemeAssertDraftVersion($expectedVersion, $root);
    $validated = nexusThemeValidateSettings($settings);
    if ($validated === nexusThemeSettings($root)) {
        nexusThemeDiscardDraftUnlocked($root, $expectedVersion);
        return $validated;
    }
    nexusThemeAtomicWrite(
        nexusThemeDraftPath($root),
        json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
    nexusThemeCleanupOrphanedAssets($root);
    return $validated;
}

function nexusThemeSaveDraftSettings(array $settings, ?string $root = null, ?string $expectedVersion = null): array
{
    return nexusThemeWithStateLock(
        static fn(): array => nexusThemeSaveDraftSettingsUnlocked($settings, $root, $expectedVersion),
        $root
    );
}

function nexusThemeDiscardDraftUnlocked(?string $root = null, ?string $expectedVersion = null): void
{
    nexusThemeAssertDraftVersion($expectedVersion, $root);
    $path = nexusThemeDraftPath($root);
    if (is_link($path) || (is_file($path) && !@unlink($path))) {
        throw new RuntimeException('The Nexus draft could not be discarded.');
    }
    clearstatcache(true, $path);
    nexusThemeCleanupOrphanedAssets($root);
}

function nexusThemeDiscardDraft(?string $root = null, ?string $expectedVersion = null): void
{
    nexusThemeWithStateLock(
        static function () use ($root, $expectedVersion): void {
            nexusThemeDiscardDraftUnlocked($root, $expectedVersion);
        },
        $root
    );
}

function nexusThemeSettingsHash(array $settings): string
{
    return hash('sha256', json_encode(nexusThemeValidateSettings($settings), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function nexusThemeFlattenSettings(array $settings, string $prefix = ''): array
{
    $flat = [];
    foreach ($settings as $key => $value) {
        $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
        if (is_array($value)) {
            $flat += nexusThemeFlattenSettings($value, $path);
            continue;
        }
        if ($path === 'branding.asset_revision' || $path === 'schema') {
            continue;
        }
        $flat[$path] = is_bool($value) ? ($value ? 'Enabled' : 'Disabled') : (string)$value;
    }
    ksort($flat);
    return $flat;
}

function nexusThemeSettingsDiff(array $from, array $to): array
{
    $before = nexusThemeFlattenSettings(nexusThemeValidateSettings($from));
    $after = nexusThemeFlattenSettings(nexusThemeValidateSettings($to));
    $changes = [];
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $path) {
        $old = $before[$path] ?? '';
        $new = $after[$path] ?? '';
        if ($old === $new) {
            continue;
        }
        $changes[] = ['path' => $path, 'before' => $old, 'after' => $new];
    }
    return $changes;
}

function nexusThemeRevisionEntry(array $settings, string $actor, string $action, ?string $createdAt = null, bool $pinned = false): array
{
    $validated = nexusThemeValidateSettings($settings);
    return [
        'id' => bin2hex(random_bytes(8)),
        'created_at' => $createdAt ?? gmdate('c'),
        'actor' => nexusThemeCleanText($actor, 80),
        'action' => nexusThemeCleanText($action, 120),
        'pinned' => $pinned,
        'hash' => nexusThemeSettingsHash($validated),
        'settings' => $validated,
    ];
}

function nexusThemeRevisions(?string $root = null): array
{
    $path = nexusThemeRevisionsPath($root);
    if (!is_file($path) || is_link($path) || filesize($path) > 2097152) {
        return [];
    }
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    if (($decoded['schema'] ?? null) !== 1 || !is_array($decoded['revisions'] ?? null)) {
        return [];
    }
    $revisions = [];
    foreach (array_slice($decoded['revisions'], -NEXUS_THEME_MAX_REVISIONS) as $revision) {
        if (!is_array($revision) || preg_match('/^[a-f0-9]{16}$/', (string)($revision['id'] ?? '')) !== 1 || !is_array($revision['settings'] ?? null)) {
            continue;
        }
        $settings = nexusThemeValidateSettings($revision['settings']);
        $revisions[] = [
            'id' => (string)$revision['id'],
            'created_at' => nexusThemeCleanText($revision['created_at'] ?? '', 40),
            'actor' => nexusThemeCleanText($revision['actor'] ?? 'Administrator', 80),
            'action' => nexusThemeCleanText($revision['action'] ?? 'Published design', 120),
            'pinned' => (bool)($revision['pinned'] ?? false),
            'hash' => nexusThemeSettingsHash($settings),
            'settings' => $settings,
        ];
    }
    return $revisions;
}

function nexusThemeWriteRevisions(array $revisions, ?string $root = null): void
{
    if (count($revisions) > NEXUS_THEME_MAX_REVISIONS) {
        $pinned = array_values(array_filter($revisions, static fn(array $revision): bool => (bool)($revision['pinned'] ?? false)));
        $ordinary = array_values(array_filter($revisions, static fn(array $revision): bool => !(bool)($revision['pinned'] ?? false)));
        $pinned = array_slice($pinned, -NEXUS_THEME_MAX_REVISIONS);
        $ordinarySlots = max(0, NEXUS_THEME_MAX_REVISIONS - count($pinned));
        $revisions = array_merge($pinned, $ordinarySlots > 0 ? array_slice($ordinary, -$ordinarySlots) : []);
        usort($revisions, static fn(array $left, array $right): int => strcmp((string)$left['created_at'], (string)$right['created_at']));
    }
    nexusThemeAtomicWrite(
        nexusThemeRevisionsPath($root),
        json_encode(['schema' => 1, 'revisions' => array_values($revisions)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
}

function nexusThemeSnapshotActive(string $actor, string $action, ?string $root = null, bool $pinned = false): array
{
    return nexusThemeWithStateLock(static function () use ($actor, $action, $root, $pinned): array {
        $revision = nexusThemeRevisionEntry(nexusThemeSettings($root), $actor, $action, null, $pinned);
        $revisions = nexusThemeRevisions($root);
        $revisions[] = $revision;
        nexusThemeWriteRevisions($revisions, $root);
        return $revision;
    }, $root);
}

function nexusThemePinRevision(string $id, bool $pinned, ?string $root = null): array
{
    return nexusThemeWithStateLock(static function () use ($id, $pinned, $root): array {
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            throw new RuntimeException('Invalid Nexus revision identifier.');
        }
        $revisions = nexusThemeRevisions($root);
        foreach ($revisions as &$revision) {
            if ($revision['id'] !== $id) {
                continue;
            }
            $revision['pinned'] = $pinned;
            $updated = $revision;
            unset($revision);
            nexusThemeWriteRevisions($revisions, $root);
            return $updated;
        }
        unset($revision);
        throw new RuntimeException('The selected Nexus revision no longer exists.');
    }, $root);
}

function nexusThemePublishDraft(string $actor = 'Administrator', ?string $root = null, string $summary = 'Published draft', ?string $expectedVersion = null): array
{
    return nexusThemeWithStateLock(static function () use ($actor, $root, $summary, $expectedVersion): array {
        nexusThemeAssertDraftVersion($expectedVersion, $root);
        $draft = nexusThemeDraftSettings($root);
        if ($draft === null) {
            throw new RuntimeException('No unpublished Nexus draft is available.');
        }
        $active = nexusThemeSettings($root);
        if ($draft === $active) {
            nexusThemeDiscardDraftUnlocked($root, $expectedVersion);
            throw new RuntimeException('The draft already matches the published design.');
        }

        $draft['branding']['asset_revision'] = bin2hex(random_bytes(8));
        $draft = nexusThemeValidateSettings($draft);
        $revisions = nexusThemeRevisions($root);
        $revisionName = nexusThemeCleanText($summary, 120);
        $revisions[] = nexusThemeRevisionEntry($active, $actor, 'Automatic snapshot before publication');
        $revisions[] = nexusThemeRevisionEntry($draft, $actor, $revisionName !== '' ? $revisionName : 'Published draft');

        // History is prepared first; the live design changes with one atomic settings-file rename.
        nexusThemeWriteRevisions($revisions, $root);
        nexusThemeAtomicWrite(nexusThemePreviousSettingsPath($root), json_encode($active, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        nexusThemeAtomicWrite(nexusThemeSettingsPath($root), json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        nexusThemeDiscardDraftUnlocked($root, $expectedVersion);
        return $draft;
    }, $root);
}

function nexusThemeRestoreRevisionToDraft(string $id, ?string $root = null, ?string $expectedVersion = null): array
{
    return nexusThemeWithStateLock(static function () use ($id, $root, $expectedVersion): array {
        nexusThemeAssertDraftVersion($expectedVersion, $root);
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            throw new RuntimeException('Invalid Nexus revision identifier.');
        }
        foreach (nexusThemeRevisions($root) as $revision) {
            if ($revision['id'] === $id) {
                return nexusThemeSaveDraftSettingsUnlocked($revision['settings'], $root, $expectedVersion);
            }
        }
        throw new RuntimeException('The selected Nexus revision no longer exists.');
    }, $root);
}

function nexusThemeCleanupOrphanedAssets(?string $root = null): void
{
    $directory = nexusThemeAssetPath($root);
    if (!is_dir($directory) || is_link($directory)) {
        return;
    }
    $referenced = [];
    $designs = [nexusThemeSettings($root)];
    $draft = nexusThemeDraftSettings($root);
    if ($draft !== null) {
        $designs[] = $draft;
    }
    foreach (nexusThemeRevisions($root) as $revision) {
        $designs[] = $revision['settings'];
    }
    foreach ($designs as $design) {
        foreach (['logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path'] as $key) {
            $path = (string)($design['branding'][$key] ?? '');
            if (preg_match('#^/uploads/nexus-theme/([^/]+)$#', $path, $matches) === 1) {
                $referenced[$matches[1]] = true;
                $webpCompanion = preg_replace('/\.(?:png|jpe?g)$/i', '.webp', $matches[1]);
                if ($webpCompanion !== $matches[1]) $referenced[$webpCompanion] = true;
            }
        }
    }
    foreach (new DirectoryIterator($directory) as $item) {
        if (!$item->isFile() || $item->isLink()) {
            continue;
        }
        $name = $item->getFilename();
        if (preg_match('/^(?:logo-light|logo-dark|favicon|login-background)-[a-f0-9]{16}\.(?:png|jpe?g|webp|gif)$/', $name) !== 1 || isset($referenced[$name])) {
            continue;
        }
        @unlink($item->getPathname());
    }
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

function nexusThemeGifPlaybackReport(string $path): array
{
    $contents = is_file($path) && filesize($path) <= NEXUS_THEME_MAX_LOGO_BYTES ? file_get_contents($path) : false;
    if ($contents === false) return ['animated' => false, 'frames' => 0, 'fps' => null, 'valid_24fps' => false];
    preg_match_all('/\x21\xF9\x04[\x00-\xFF]\K[\x00-\xFF]{2}/s', $contents, $delays);
    $values = [];
    foreach ($delays[0] as $bytes) $values[] = max(1, unpack('v', $bytes)[1]);
    $average = $values === [] ? null : array_sum($values) / count($values);
    $fps = $average === null ? null : round(100 / $average, 1);
    return ['animated' => count($values) > 1, 'frames' => count($values), 'fps' => $fps, 'valid_24fps' => $fps !== null && $fps >= 23 && $fps <= 25];
}

function nexusThemeImageResource(string $path, int $type): mixed
{
    return match ($type) {
        IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function nexusThemeProcessImage(string $path, int $type, array $options): void
{
    $resizeWidth = max(0, min(4000, (int)($options['resize_width'] ?? 0)));
    $resizeHeight = max(0, min(4000, (int)($options['resize_height'] ?? 0)));
    $crop = is_array($options['crop'] ?? null) ? $options['crop'] : [];
    $wantsTransform = $resizeWidth > 0 || $resizeHeight > 0 || $crop !== [];
    if (!$wantsTransform) return;
    if ($type === IMAGETYPE_GIF) throw new RuntimeException('Animated GIFs preserve every frame and cannot be cropped. Upload a pre-cropped GIF or use a static logo.');
    if (!extension_loaded('gd')) throw new RuntimeException('Logo crop and resize require the PHP GD extension.');
    $source = nexusThemeImageResource($path, $type);
    if ($source === false) throw new RuntimeException('The uploaded image could not be opened for processing.');
    try {
        $sourceWidth = imagesx($source); $sourceHeight = imagesy($source);
        $x = max(0, min($sourceWidth - 1, (int)($crop['x'] ?? 0)));
        $y = max(0, min($sourceHeight - 1, (int)($crop['y'] ?? 0)));
        $cropWidth = max(1, min($sourceWidth - $x, (int)($crop['width'] ?? $sourceWidth)));
        $cropHeight = max(1, min($sourceHeight - $y, (int)($crop['height'] ?? $sourceHeight)));
        if ($resizeWidth < 1 && $resizeHeight < 1) { $resizeWidth = $cropWidth; $resizeHeight = $cropHeight; }
        elseif ($resizeWidth < 1) $resizeWidth = max(1, (int)round($resizeHeight * $cropWidth / $cropHeight));
        elseif ($resizeHeight < 1) $resizeHeight = max(1, (int)round($resizeWidth * $cropHeight / $cropWidth));
        $target = imagecreatetruecolor($resizeWidth, $resizeHeight);
        imagealphablending($target, false); imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, $x, $y, $resizeWidth, $resizeHeight, $cropWidth, $cropHeight);
        $written = match ($type) { IMAGETYPE_PNG => imagepng($target, $path, 6), IMAGETYPE_WEBP => imagewebp($target, $path, 88), default => imagejpeg($target, $path, 90) };
        imagedestroy($target);
        if (!$written) throw new RuntimeException('The processed image could not be saved.');
    } finally { imagedestroy($source); }
}

function nexusThemeCreateWebpCompanion(string $path, int $type): ?string
{
    if ($type === IMAGETYPE_WEBP || $type === IMAGETYPE_GIF || !function_exists('imagewebp')) return null;
    $source = nexusThemeImageResource($path, $type);
    if ($source === false) return null;
    $target = preg_replace('/\.[^.]+$/', '.webp', $path);
    $written = imagewebp($source, $target, 88); imagedestroy($source);
    if (!$written) return null;
    @chmod($target, 0644);
    return $target;
}

function nexusThemeStoreImage(array $upload, string $slot, ?string $root = null, array $options = []): string
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

    // Immutable filenames keep unpublished uploads isolated from the active design
    // and allow historical revisions to retain their exact visual assets.
    $assetId = bin2hex(random_bytes(8));
    $target = $assetDirectory . DIRECTORY_SEPARATOR . $slot . '-' . $assetId . '.' . $extension;
    if (is_link($target)) {
        throw new RuntimeException('The Nexus image target cannot be a symbolic link.');
    }
    $staged = tempnam($assetDirectory, '.nexus-image-');
    if ($staged === false || !@move_uploaded_file($temporary, $staged)) {
        throw new RuntimeException('The uploaded image could not be staged.');
    }
    try {
        @chmod($staged, 0644);
        nexusThemeProcessImage($staged, $image[2], $options);
        if (!@rename($staged, $target)) {
            throw new RuntimeException('The uploaded image could not be activated.');
        }
    } finally {
        if (is_file($staged)) {
            @unlink($staged);
        }
    }
    nexusThemeCreateWebpCompanion($target, $image[2]);
    return '/uploads/' . NEXUS_THEME_ASSET_DIRECTORY . '/' . $slot . '-' . $assetId . '.' . $extension;
}

function nexusThemeAssetMetadata(string $relative, ?string $root = null): array
{
    $empty = ['configured' => false, 'exists' => false, 'width' => null, 'height' => null, 'bytes' => null, 'mime' => null, 'animated' => false, 'frames' => 0, 'fps' => null, 'valid_24fps' => false, 'webp' => false, 'warnings' => []];
    if ($relative === '' || preg_match('#^/uploads/nexus-theme/[a-z-]+-[a-f0-9]{16}\.(?:png|jpe?g|webp|gif)$#', $relative) !== 1) return $empty;
    $path = nexusThemeDocumentRoot($root) . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $empty['configured'] = true;
    if (!is_file($path) || is_link($path)) { $empty['warnings'][] = 'Configured file is missing.'; return $empty; }
    $info = @getimagesize($path);
    if ($info === false) { $empty['warnings'][] = 'File is not a readable image.'; return $empty; }
    $empty['exists'] = true; $empty['width'] = $info[0]; $empty['height'] = $info[1]; $empty['bytes'] = filesize($path); $empty['mime'] = $info['mime'] ?? null;
    if ($info[2] === IMAGETYPE_GIF) $empty = array_merge($empty, nexusThemeGifPlaybackReport($path));
    $empty['webp'] = $info[2] === IMAGETYPE_WEBP || is_file((string)preg_replace('/\.[^.]+$/', '.webp', $path));
    if ($info[0] > 2400 || $info[1] > 1200) $empty['warnings'][] = 'Large dimensions may slow page rendering.';
    if ($empty['bytes'] > 2097152) $empty['warnings'][] = 'File size exceeds the recommended 2 MB target.';
    if ($empty['animated'] && !$empty['valid_24fps']) $empty['warnings'][] = 'GIF playback is not approximately 24fps.';
    return $empty;
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
    if ($url === '' || preg_match('#^/uploads/nexus-theme/(?:logo-light|logo-dark|favicon|login-background)(?:-[a-f0-9]{16})?\.(?:png|jpe?g|webp|gif)$#', $url) !== 1) {
        return $url;
    }
    if (preg_match('/\.(?:png|jpe?g)$/i', $url) === 1) {
        $webpUrl = (string)preg_replace('/\.(?:png|jpe?g)$/i', '.webp', $url);
        try {
            $webpPath = nexusThemeDocumentRoot() . str_replace('/', DIRECTORY_SEPARATOR, $webpUrl);
            if (is_file($webpPath) && !is_link($webpPath)) $url = $webpUrl;
        } catch (Throwable) {
            // Asset optimization must never interfere with rendering.
        }
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

function nexusThemeApplyPreset(string $id, ?string $root = null, ?string $expectedVersion = null): array
{
    foreach (nexusThemeSavedPresets($root) as $preset) {
        if ($preset['id'] !== $id) {
            continue;
        }
        $current = nexusThemeDraftSettings($root) ?? nexusThemeSettings($root);
        foreach (['logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path', 'asset_revision'] as $asset) {
            $preset['settings']['branding'][$asset] = $current['branding'][$asset];
        }
        return nexusThemeSaveDraftSettings($preset['settings'], $root, $expectedVersion);
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
        $darkAgentLogo = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', nexusThemeLogoVariantForColor($settings['dark_mode']['colors']['header'])), $settings);
        if ($darkAgentLogo !== '' && $darkAgentLogo !== $logo) {
            $css .= '.nexus-agent.nexus-color-mode-dark .brand-link[href="/agent/dashboard.php"] .brand-image{background-image:url("' . $darkAgentLogo . '")}' . "\n";
            if ($settings['dark_mode']['mode'] === 'system') $css .= '@media (prefers-color-scheme:dark){.nexus-agent.nexus-color-mode-system .brand-link[href="/agent/dashboard.php"] .brand-image{background-image:url("' . $darkAgentLogo . '")}}' . "\n";
        }
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
    $surfaceSelectors = ['technician' => '.nexus-theme.nexus-surface-technician', 'client' => '.nexus-theme.nexus-surface-client', 'auth' => '.nexus-theme.nexus-surface-auth', 'guest' => '.nexus-theme.nexus-surface-guest', 'print' => '.nexus-theme.nexus-surface-print'];
    foreach ($surfaceSelectors as $surface => $surfaceSelector) {
        if (!$settings['profiles'][$surface]['enabled']) continue;
        $surfaceSettings = nexusThemeSettingsForSurface($settings, $surface);
        $surfaceColors = $surfaceSettings['colors'];
        $surfaceVariables = [
            '--nexus-cyan' => $surfaceColors['primary'], '--nexus-cyan-strong' => nexusThemeMixColors($surfaceColors['primary'], '#000000', 88),
            '--nexus-violet' => $surfaceColors['secondary'], '--nexus-gradient' => 'linear-gradient(110deg,' . $surfaceColors['secondary'] . ' 0%,' . $surfaceColors['primary'] . ' 100%)',
            '--nexus-night' => $surfaceColors['sidebar'], '--nexus-header' => $surfaceColors['header'], '--nexus-header-text' => $surfaceColors['header_text'], '--nexus-ink' => $surfaceColors['auth_background'],
            '--nexus-page' => $surfaceColors['page'], '--nexus-surface' => $surfaceColors['surface'], '--nexus-surface-2' => $surfaceColors['page'], '--nexus-text' => $surfaceColors['text'],
            '--nexus-copy' => nexusThemeMixColors($surfaceColors['text'], $surfaceColors['surface'], 72), '--nexus-muted' => nexusThemeMixColors($surfaceColors['text'], $surfaceColors['surface'], 56), '--nexus-border' => nexusThemeMixColors($surfaceColors['text'], $surfaceColors['surface'], 18),
        ];
        $surfaceDeclarations = [];
        foreach ($surfaceVariables as $name => $value) $surfaceDeclarations[] = $name . ':' . $value;
        $css .= $surfaceSelector . '{' . implode(';', $surfaceDeclarations) . "}\n";
    }
    $darkColors = $settings['dark_mode']['colors'];
    $darkDeclarations = [
        '--nexus-cyan:' . $darkColors['primary'], '--nexus-violet:' . $darkColors['secondary'], '--nexus-night:' . $darkColors['sidebar'], '--nexus-header:' . $darkColors['header'], '--nexus-header-text:' . $darkColors['header_text'], '--nexus-ink:' . $darkColors['auth_background'], '--nexus-page:' . $darkColors['page'], '--nexus-surface:' . $darkColors['surface'], '--nexus-surface-2:' . nexusThemeMixColors($darkColors['surface'], '#ffffff', 94), '--nexus-text:' . $darkColors['text'], '--nexus-copy:' . nexusThemeMixColors($darkColors['text'], $darkColors['surface'], 72), '--nexus-muted:' . nexusThemeMixColors($darkColors['text'], $darkColors['surface'], 56), '--nexus-border:' . nexusThemeMixColors($darkColors['text'], $darkColors['surface'], 18),
    ];
    $css .= '.nexus-theme.nexus-color-mode-dark,.nexus-theme[data-nexus-color-mode="dark"]{' . implode(';', $darkDeclarations) . "}\n";
    if ($settings['dark_mode']['mode'] === 'system') $css .= '@media (prefers-color-scheme:dark){.nexus-theme.nexus-color-mode-system{' . implode(';', $darkDeclarations) . '}}' . "\n";
    $css .= '@media (min-width:992px){.nexus-agent:not(.sidebar-collapse) .main-sidebar{width:var(--nexus-sidebar-width)}.nexus-agent:not(.sidebar-collapse) .content-wrapper,.nexus-agent:not(.sidebar-collapse) .main-header,.nexus-agent:not(.sidebar-collapse) .main-footer{margin-left:var(--nexus-sidebar-width)}}' . "\n";
    return $css;
}

function nexusThemePresentationModel(array $settings, string $fallbackBrand = 'Nexus MSP', ?string $surface = null): array
{
    $settings = nexusThemeValidateSettings($settings);
    if ($surface !== null) $settings = nexusThemeSettingsForSurface($settings, $surface);
    $brand = nexusThemeBrandName($fallbackBrand, $settings);
    $authLogo = $settings['branding']['show_login_logo']
        ? nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', nexusThemeLogoVariantForColor($settings['colors']['auth_background'])), $settings)
        : '';
    $agentLogo = $settings['branding']['show_agent_logo']
        ? nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', nexusThemeLogoVariantForColor($settings['colors']['header'])), $settings)
        : '';
    $portalLogo = $settings['branding']['show_portal_logo']
        ? nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($settings, '', nexusThemeLogoVariantForColor($settings['colors']['sidebar'])), $settings)
        : '';
    return [
        'settings' => $settings,
        'brand' => $brand,
        'tagline' => $settings['branding']['tagline'],
        'logo_alt' => $settings['branding']['logo_alt'] !== '' ? $settings['branding']['logo_alt'] : $brand . ' logo',
        'auth_logo' => $authLogo,
        'agent_logo' => $agentLogo,
        'portal_logo' => $portalLogo,
        'body_classes' => nexusThemeBodyClasses($settings) . ($surface !== null ? ' nexus-surface-' . $surface : '') . ' ' . nexusThemeDarkModeClasses($settings),
        'custom_css' => nexusThemeCustomCss($settings),
    ];
}

function nexusThemeRelativeLuminance(string $hex): float
{
    $channels = [];
    for ($offset = 1; $offset <= 5; $offset += 2) {
        $channel = hexdec(substr($hex, $offset, 2)) / 255;
        $channels[] = $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function nexusThemeContrastRatio(string $first, string $second): float
{
    $one = nexusThemeRelativeLuminance(nexusThemeCleanColor($first, '#000000'));
    $two = nexusThemeRelativeLuminance(nexusThemeCleanColor($second, '#ffffff'));
    return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
}

function nexusThemeAccessibleTextColor(string $background): string
{
    return nexusThemeContrastRatio('#ffffff', $background) >= nexusThemeContrastRatio('#0b0a17', $background) ? '#ffffff' : '#0b0a17';
}

function nexusThemeAccessibleAccent(string $background): string
{
    return nexusThemeRelativeLuminance($background) > 0.45 ? '#005fcc' : '#7dd3fc';
}

function nexusThemeQualityReport(array $settings, int $width = 1440): array
{
    $settings = nexusThemeValidateSettings($settings);
    $width = max(320, min(1920, $width));
    $colors = $settings['colors'];
    $findings = [];
    $add = static function (string $id, string $severity, string $title, string $detail, string $section, string $control, string $surface, ?array $fix = null) use (&$findings): void {
        $findings[] = compact('id', 'severity', 'title', 'detail', 'section', 'control', 'surface', 'fix');
    };

    $bodyRatio = nexusThemeContrastRatio($colors['text'], $colors['surface']);
    if ($bodyRatio < 4.5) {
        $add('body-contrast', 'error', 'Body text contrast is too low', sprintf('Text and surface contrast is %.2f:1; normal text needs at least 4.5:1.', $bodyRatio), 'colors', 'nexus-color-text', 'all', ['path' => 'colors.text', 'value' => nexusThemeAccessibleTextColor($colors['surface'])]);
    }
    $headerRatio = nexusThemeContrastRatio($colors['header_text'], $colors['header']);
    if ($headerRatio < 4.5) {
        $add('header-contrast', 'error', 'Header text can disappear', sprintf('Header contrast is %.2f:1.', $headerRatio), 'colors', 'nexus-color-header_text', 'technician', ['path' => 'colors.header_text', 'value' => nexusThemeAccessibleTextColor($colors['header'])]);
    }
    $buttonInk = nexusThemeAccessibleTextColor($colors['primary']);
    $buttonRatio = nexusThemeContrastRatio($colors['primary'], $buttonInk);
    if ($buttonRatio < 4.5) {
        $add('active-contrast', 'error', 'Primary and active states need contrast', sprintf('Primary action contrast is %.2f:1.', $buttonRatio), 'colors', 'nexus-color-primary', 'all', ['path' => 'colors.primary', 'value' => nexusThemeAccessibleAccent($colors['surface'])]);
    }
    $focusRatio = nexusThemeContrastRatio($colors['primary'], $colors['surface']);
    if ($focusRatio < 3.0) {
        $add('focus-contrast', 'warning', 'Focus indicators blend into surfaces', sprintf('The focus color has %.2f:1 contrast; visible component boundaries need 3:1.', $focusRatio), 'colors', 'nexus-color-primary', 'all', ['path' => 'colors.primary', 'value' => nexusThemeAccessibleAccent($colors['surface'])]);
    }
    $hasLogo = $settings['branding']['logo_light_path'] !== '' || $settings['branding']['logo_dark_path'] !== '';
    if ($hasLogo && trim($settings['branding']['logo_alt']) === '') {
        $alt = trim($settings['branding']['brand_name']) !== '' ? $settings['branding']['brand_name'] : 'Nexus MSP';
        $add('logo-alt', 'error', 'Uploaded logo is missing alternative text', 'Screen readers need a concise accessible brand name.', 'brand', 'nexus-logo-alt', 'all', ['path' => 'branding.logo_alt', 'value' => $alt]);
    }
    if ($settings['appearance']['font_scale'] < 95) {
        $add('font-scale', 'warning', 'Interface text may be too small', 'The selected interface scale is below the recommended 95% floor.', 'layout', 'nexus-font-scale', 'all', ['path' => 'appearance.font_scale', 'value' => 100]);
    }
    if ($settings['appearance']['motion_style'] === 'snappy' && !$settings['appearance']['reduce_motion']) {
        $add('excess-motion', 'warning', 'High-energy motion has no local reduction', 'Operating-system preferences are respected, but a reduced-motion draft is safer for shared environments.', 'motion', 'nexus-reduce-motion', 'all', ['path' => 'appearance.reduce_motion', 'value' => true]);
    }
    if ($settings['appearance']['density'] === 'compact' && $settings['appearance']['font_scale'] < 100) {
        $add('touch-targets', 'warning', 'Compact controls may miss touch-target guidance', 'Compact density combined with reduced scale can create controls below the recommended touch size.', 'layout', 'nexus-density', 'mobile', ['path' => 'appearance.density', 'value' => 'comfortable']);
    }
    if ($width <= 768 && $settings['appearance']['sidebar_width'] > 280 && !$settings['appearance']['sidebar_compact']) {
        $add('sidebar-collision', 'warning', 'Sidebar can crowd the content area', 'This sidebar width leaves limited room at the selected viewport.', 'layout', 'nexus-sidebar-width', 'technician', ['path' => 'appearance.sidebar_width', 'value' => 250]);
    }
    if ($width <= 480 && $hasLogo && $settings['branding']['logo_size'] > 115) {
        $add('logo-overflow', 'warning', 'Logo may overflow narrow headers', 'The current logo scale is oversized for a phone-width navigation header.', 'brand', 'nexus-logo-size', 'mobile', ['path' => 'branding.logo_size', 'value' => 100]);
    }
    if ($width <= 480 && strlen(trim($settings['branding']['brand_name'])) > 24) {
        $add('brand-truncation', 'info', 'Brand name may truncate on phones', 'Use the mobile preview to confirm the full name remains recognizable.', 'brand', 'nexus-brand-name', 'mobile');
    }
    if ($width <= 480) {
        $add('table-behavior', 'info', 'Wide tables require horizontal access', 'Ticket and invoice tables retain their columns in an independently scrollable region.', 'quality', 'nexus-responsive-width', 'invoice');
    }

    $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
    foreach ($findings as $finding) {
        $counts[$finding['severity']]++;
    }
    return ['width' => $width, 'counts' => $counts, 'score' => max(0, 100 - $counts['error'] * 20 - $counts['warning'] * 8), 'findings' => $findings];
}

function nexusThemeApplyQualityFixes(array $settings, int $width = 390): array
{
    $settings = nexusThemeValidateSettings($settings);
    foreach (nexusThemeQualityReport($settings, $width)['findings'] as $finding) {
        if (!is_array($finding['fix'] ?? null) || !str_contains((string)$finding['fix']['path'], '.')) {
            continue;
        }
        [$group, $key] = explode('.', (string)$finding['fix']['path'], 2);
        if (array_key_exists($key, $settings[$group] ?? [])) {
            $settings[$group][$key] = $finding['fix']['value'];
        }
    }
    return nexusThemeValidateSettings($settings);
}

function nexusThemeHealthReport(?string $root = null): array
{
    $documentRoot = nexusThemeDocumentRoot($root);
    static $cache = [];
    if (isset($cache[$documentRoot])) return $cache[$documentRoot];
    $required = ['includes/nexus_theme.php', 'css/nexus-theme.css', 'css/nexus-theme-custom.php', 'admin/nexus.php', 'admin/post/nexus.php', 'login.php'];
    $checks = [];
    foreach ($required as $relative) {
        $path = $documentRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $checks[$relative] = is_file($path) && !is_link($path) && filesize($path) > 0;
    }
    $cssPath = $documentRoot . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'nexus-theme.css';
    $css = is_file($cssPath) ? (string)file_get_contents($cssPath) : '';
    $checks['css-balanced'] = $css !== '' && substr_count($css, '{') === substr_count($css, '}') && !str_contains($css, '</style>');
    $settingsPath = nexusThemeSettingsPath($root);
    $checks['settings-readable'] = !is_file($settingsPath);
    if (is_file($settingsPath) && !is_link($settingsPath) && filesize($settingsPath) <= 65536) {
        try { $decodedSettings = json_decode((string)file_get_contents($settingsPath), true, 32, JSON_THROW_ON_ERROR); $checks['settings-readable'] = is_array($decodedSettings); }
        catch (JsonException) { $checks['settings-readable'] = false; }
    }
    $failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
    return $cache[$documentRoot] = ['healthy' => $failed === [], 'checks' => $checks, 'failed' => $failed, 'checked_at' => gmdate('c')];
}

function nexusThemeRuntimeEnabled(?string $root = null): bool
{
    if (!nexusThemeIsEnabled($root)) return false;
    try { return nexusThemeHealthReport($root)['healthy']; } catch (Throwable) { return false; }
}

function nexusThemeEmergencyDisable(?string $root = null): array
{
    nexusThemeSetEnabled(false, $root);
    return nexusThemeHealthReport($root);
}

function nexusThemeRestoreKnownGood(string $actor = 'Administrator', ?string $root = null): array
{
    $revisions = array_reverse(nexusThemeRevisions($root));
    $selected = null;
    foreach ($revisions as $revision) {
        if ($revision['pinned']) { $selected = $revision; break; }
    }
    if ($selected === null && $revisions !== []) $selected = $revisions[0];
    if ($selected === null) throw new RuntimeException('No known-good Nexus revision is available.');
    nexusThemeSnapshotActive($actor, 'Automatic snapshot before emergency recovery', $root, false);
    nexusThemeSaveSettings($selected['settings'], $root);
    nexusThemeDiscardDraft($root);
    nexusThemeSetEnabled(true, $root);
    return $selected;
}

function nexusThemeDiagnostics(?string $root = null): array
{
    $active = nexusThemeSettings($root);
    $draft = nexusThemeDraftSettings($root);
    $assets = [];
    foreach (['logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path'] as $key) {
        $relative = (string)$active['branding'][$key];
        $file = $relative !== '' ? nexusThemeDocumentRoot($root) . str_replace('/', DIRECTORY_SEPARATOR, $relative) : '';
        $assets[$key] = nexusThemeAssetMetadata($relative, $root);
    }
    $revisions = nexusThemeRevisions($root);
    return [
        'schema' => 1,
        'kind' => 'nexus-theme-diagnostics',
        'generated_at' => gmdate('c'),
        'versions' => ['manager' => NEXUS_MANAGER_VERSION, 'theme' => NEXUS_THEME_VERSION, 'itflow_commit' => NEXUS_ITFLOW_COMMIT, 'php' => PHP_VERSION],
        'state' => ['enabled' => nexusThemeIsEnabled($root), 'has_draft' => $draft !== null, 'active_hash' => nexusThemeSettingsHash($active), 'draft_hash' => $draft !== null ? nexusThemeSettingsHash($draft) : null],
        'quality' => ['desktop' => nexusThemeQualityReport($draft ?? $active, 1440), 'mobile' => nexusThemeQualityReport($draft ?? $active, 390)],
        'assets' => $assets,
        'history' => ['revisions' => count($revisions), 'pinned' => count(array_filter($revisions, static fn(array $revision): bool => $revision['pinned']))],
        'health' => nexusThemeHealthReport($root),
        'updater' => nexusUpdaterStatus($root),
    ];
}

function nexusThemeTicketSummaryComponent(array $metrics, string $title = 'Ticket queue'): string
{
    $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $values = ['open' => (int)($metrics['open'] ?? 0), 'waiting' => (int)($metrics['waiting'] ?? 0), 'priority' => (int)($metrics['priority'] ?? 0), 'response' => $e($metrics['response'] ?? 'No data')];
    return '<section class="nexus-ticket-queue-summary" aria-labelledby="nexus-ticket-queue-title"><header class="nexus-ticket-queue-heading"><div><span class="nexus-manager-kicker">Service desk pulse</span><h2 id="nexus-ticket-queue-title">' . $e($title) . '</h2></div><span class="nexus-ticket-queue-live"><i class="fas fa-circle" aria-hidden="true"></i> Live queue</span></header><div class="nexus-ticket-queue-grid">'
        . '<article class="card nexus-ticket-metric nexus-ticket-metric-open"><div class="card-body"><div><strong>' . $values['open'] . '</strong><span>Open tickets</span></div><span class="nexus-ticket-metric-icon"><i class="fas fa-inbox" aria-hidden="true"></i></span></div></article>'
        . '<article class="card nexus-ticket-metric nexus-ticket-metric-waiting"><div class="card-body"><div><strong>' . $values['waiting'] . '</strong><span>Waiting on client</span></div><span class="nexus-ticket-metric-icon"><i class="fas fa-user-clock" aria-hidden="true"></i></span></div></article>'
        . '<article class="card nexus-ticket-metric nexus-ticket-metric-priority"><div class="card-body"><div><strong>' . $values['priority'] . '</strong><span>High priority</span></div><span class="nexus-ticket-metric-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></span></div></article>'
        . '<article class="card nexus-ticket-metric nexus-ticket-metric-response"><div class="card-body"><div><strong>' . $values['response'] . '</strong><span>Median response</span></div><span class="nexus-ticket-metric-icon"><i class="fas fa-stopwatch" aria-hidden="true"></i></span></div></article></div></section>';
}

function nexusThemePreviewDocument(array $settings, string $surface, string $fallbackBrand = 'Nexus MSP'): string
{
    if (!in_array($surface, ['auth', 'reset', 'dashboard', 'technician', 'client', 'mobile', 'invoice', 'print'], true)) {
        throw new RuntimeException('Unknown Nexus preview surface.');
    }
    $profileSurface = match ($surface) { 'auth', 'reset' => 'auth', 'dashboard', 'technician', 'mobile' => 'technician', 'client' => 'client', 'invoice' => 'guest', 'print' => 'print' };
    $model = nexusThemePresentationModel($settings, $fallbackBrand, $profileSurface);
    $settings = $model['settings'];
    $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $brand = $e($model['brand']);
    $tagline = $e($model['tagline']);
    $classes = $e($model['body_classes']);
    $logoAlt = $e($model['logo_alt']);
    $authLogo = $e($model['auth_logo']);
    $agentLogo = $e($model['agent_logo']);
    $portalLogo = $e($model['portal_logo']);
    $customCss = $model['custom_css'];
    $heading = $e($settings['content']['login_heading']);
    $eyebrow = $e($settings['content']['login_eyebrow']);
    $message = nl2br($e($settings['content']['login_message']));
    $portalHeading = $e($settings['content']['portal_heading']);
    $portalMessage = nl2br($e($settings['content']['portal_message']));

    $brandImage = static function (string $url, string $alt, string $class = 'img-fluid') use ($e): string {
        return $url === '' ? '' : '<img class="' . $e($class) . '" data-nexus-color-logo src="' . $url . '" alt="' . $alt . '">';
    };
    $navigationMarkup = static function (array $items, string $activeId = '') use ($e): string {
        $markup = '';
        foreach ($items as $item) $markup .= '<li class="nav-item"><a class="nav-link ' . ($item['id'] === $activeId ? 'active' : '') . '" href="' . $e($item['href']) . '"><i class="nav-icon ' . $e($item['icon']) . '"></i><p>' . $e($item['label']) . '</p></a></li>';
        return $markup;
    };

    if ($surface === 'reset') {
        $brandMarkup = $authLogo !== ''
            ? $brandImage($authLogo, $logoAlt)
            : '<span class="nexus-fallback-logo"><i class="fas fa-layer-group mr-2" aria-hidden="true"></i>' . $brand . '</span>';
        $content = '<body class="hold-transition login-page nexus-theme nexus-auth ' . $classes . '"><div class="login-box">'
            . '<div class="login-logo ' . ($authLogo !== '' ? 'nexus-auth-brand--logo' : 'nexus-auth-brand--text') . '">' . $brandMarkup . '</div>'
            . '<div class="card"><div class="card-body login-card-body"><span class="nexus-eyebrow">Secure account recovery</span><h1 class="nexus-auth-title">Reset your password</h1><p class="nexus-auth-copy">Enter the email address for your portal account and we will send a secure reset link.</p>'
            . '<label class="nexus-field-label">Email address</label><div class="input-group mb-3"><input class="form-control" value="alex@example.com" readonly><div class="input-group-append"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div></div>'
            . '<button class="btn btn-primary btn-block">Send reset link</button><p class="text-center mt-3 mb-0"><a>Return to sign in</a></p></div></div><p class="nexus-auth-tagline">' . $tagline . '</p></div></body>';
    } elseif ($surface === 'auth') {
        $brandMarkup = $authLogo !== ''
            ? $brandImage($authLogo, $logoAlt)
            : '<span class="nexus-fallback-logo"><i class="fas fa-layer-group mr-2" aria-hidden="true"></i>' . $brand . '</span>';
        $content = '<body class="hold-transition login-page nexus-theme nexus-auth ' . $classes . '"><div class="login-box">'
            . '<div class="login-logo ' . ($authLogo !== '' ? 'nexus-auth-brand--logo' : 'nexus-auth-brand--text') . '">' . $brandMarkup . '</div>'
            . '<div class="card"><div class="card-body login-card-body"><span class="nexus-eyebrow">' . $eyebrow . '</span><h1 class="nexus-auth-title">' . $heading . '</h1><p class="nexus-auth-copy">' . $message . '</p>'
            . '<label class="nexus-field-label">Email address</label><div class="input-group mb-3"><input class="form-control" value="alex@example.com" readonly><div class="input-group-append"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div></div>'
            . '<label class="nexus-field-label">Password</label><div class="input-group mb-3"><input class="form-control" value="••••••••" readonly><div class="input-group-append"><span class="input-group-text"><i class="fas fa-lock"></i></span></div></div>'
            . '<button class="btn btn-primary btn-block">Sign in</button></div></div><p class="nexus-auth-tagline">' . $tagline . '</p></div></body>';
    } elseif ($surface === 'dashboard' || $surface === 'technician') {
        $technicianTitle = $surface === 'dashboard' ? 'Operations dashboard' : 'Ticket queue';
        $brandMark = $agentLogo !== '' ? '<span class="brand-image"></span>' : '<span class="brand-image"><i class="fas fa-layer-group"></i></span>';
        $content = '<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed nexus-theme nexus-agent ' . $classes . '"><div class="wrapper text-sm">'
            . '<aside class="main-sidebar sidebar-dark-primary"><a class="brand-link" href="/agent/dashboard.php">' . $brandMark . '<span class="brand-text font-weight-light">' . $brand . '</span></a><div class="sidebar"><nav class="mt-2"><ul class="nav nav-pills nav-sidebar flex-column">'
            . '<li class="nav-header">CUSTOM NAVIGATION</li>' . $navigationMarkup(nexusThemeNavigationItems($settings, 'desktop', 'admin'), $surface === 'dashboard' ? 'dashboard' : 'tickets') . '</ul></nav></div></aside>'
            . '<nav class="main-header navbar navbar-expand navbar-dark"><a class="nav-link"><i class="fas fa-bars"></i></a><span class="navbar-text ml-auto">Alex Technician&nbsp; <i class="fas fa-user-circle"></i></span></nav>'
            . '<div class="content-wrapper"><section class="content-header"><div class="container-fluid"><h1>' . $technicianTitle . '</h1></div></section><section class="content"><div class="container-fluid">' . nexusThemeTicketSummaryComponent(['open' => 12, 'waiting' => 3, 'priority' => 2, 'response' => '18m'], $technicianTitle)
            . '<div class="card"><div class="card-header"><strong>Active tickets</strong></div><div class="card-body"><table class="table"><thead><tr><th>Subject</th><th>Client</th><th>Status</th></tr></thead><tbody><tr><td>New employee setup</td><td>Nexus MSP</td><td><span class="badge badge-info">Open</span></td></tr><tr><td>VPN access</td><td>Example Co.</td><td><span class="badge badge-warning">Waiting</span></td></tr></tbody></table></div></div></div></section></div></div></body>';
    } elseif ($surface === 'mobile') {
        $clientBrand = $portalLogo !== '' ? $brandImage($portalLogo, $logoAlt, 'nexus-client-nav-logo') : '<span>' . $brand . '</span>';
        $mobileLinks = '';
        foreach (nexusThemeNavigationItems($settings, 'mobile', 'admin') as $index => $item) $mobileLinks .= '<a class="' . ($index === 0 ? 'active' : '') . '"><i class="' . $e($item['icon']) . '"></i> ' . $e($item['label']) . '</a>';
        $content = '<body class="hold-transition nexus-theme nexus-agent nexus-client nexus-preview-mobile ' . $classes . '"><nav class="navbar navbar-dark nexus-client-nav"><div class="container"><a class="navbar-brand">' . $clientBrand . '</a><button class="navbar-toggler"><i class="fas fa-bars"></i></button></div></nav><aside class="nexus-preview-mobile-menu"><span class="nexus-manager-kicker">Mobile navigation</span>' . $mobileLinks . '<button class="btn btn-primary btn-block">Create support request</button></aside><main class="container py-4"><span class="nexus-eyebrow">Phone workspace</span><h1>Technician dashboard</h1><p>Independent mobile ordering, labels, icons, and access rules.</p></main></body>';
    } elseif ($surface === 'client') {
        $clientBrand = $portalLogo !== '' ? $brandImage($portalLogo, $logoAlt, 'nexus-client-nav-logo') : '<span>' . $brand . '</span>';
        $content = '<body class="hold-transition nexus-theme nexus-client ' . $classes . '"><nav class="navbar navbar-expand-lg navbar-dark nexus-client-nav"><div class="container"><a class="navbar-brand ' . ($portalLogo !== '' ? 'nexus-client-brand--logo' : 'nexus-client-brand--text') . '">' . $clientBrand . '</a><ul class="navbar-nav mr-auto"><li class="nav-item active"><a class="nav-link">Home</a></li><li class="nav-item"><a class="nav-link">Tickets</a></li><li class="nav-item"><a class="nav-link">Finance</a></li></ul><a class="btn nexus-portal-cta"><i class="fas fa-plus mr-2"></i>Create support request</a></div></nav>'
            . '<main class="container py-5"><span class="nexus-eyebrow">Client workspace</span><h1>' . $portalHeading . '</h1><p class="lead">' . $portalMessage . '</p><div class="row mt-4"><div class="col-md-4"><div class="card"><div class="card-body"><i class="fas fa-ticket-alt text-info fa-2x mb-3"></i><h2 class="h5">Open tickets</h2><strong class="h2">4</strong></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><i class="fas fa-file-invoice-dollar text-info fa-2x mb-3"></i><h2 class="h5">Invoices</h2><strong class="h2">2</strong></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><i class="fas fa-book text-info fa-2x mb-3"></i><h2 class="h5">Documents</h2><strong class="h2">18</strong></div></div></div></div></main></body>';
    } else {
        $printPreview = $surface === 'print';
        $invoiceBrand = $portalLogo !== '' ? $brandImage($portalLogo, $logoAlt, 'nexus-guest-logo-screen') : '<span class="nexus-preview-symbol"><i class="fas fa-layer-group"></i></span><strong>' . $brand . '</strong>';
        $content = '<body class="layout-top-nav nexus-theme nexus-guest nexus-guest-invoice ' . ($printPreview ? 'nexus-print-preview ' : '') . $classes . '"><div class="wrapper text-sm"><header class="nexus-guest-masthead"><div class="container nexus-guest-masthead-inner"><span class="nexus-guest-brand">' . $invoiceBrand . '</span><div class="nexus-guest-heading"><span>' . ($printPreview ? 'Printable billing document' : 'Secure billing portal') . '</span><strong>Invoice details</strong></div><p class="nexus-guest-tagline">' . $tagline . '</p></div></header><main class="container py-4"><div class="card"><div class="card-header d-flex justify-content-between"><strong>Account balance: $1,240.00</strong>' . ($printPreview ? '<span class="badge badge-light">Print/PDF preview</span>' : '<span><button class="btn btn-default btn-sm">Print</button> <button class="btn btn-default btn-sm">Download</button></span>') . '</div><div class="card-body"><div class="row"><div class="col-7"><span class="nexus-eyebrow">From</span><h1 class="h3">' . $brand . '</h1><p>Managed technology and support</p></div><div class="col-5 text-right"><span class="nexus-eyebrow">Billing document</span><h2>INVOICE</h2><span class="badge badge-success">Open</span></div></div><hr><table class="table mt-4"><thead><tr><th>Service</th><th>Quantity</th><th class="text-right">Amount</th></tr></thead><tbody><tr><td>Managed services<br><small>Monthly support coverage</small></td><td>1</td><td class="text-right">$1,240.00</td></tr></tbody></table><div class="text-right"><span class="nexus-eyebrow">Balance due</span><div class="h2">$1,240.00</div></div></div></div></main></div></body>';
    }

    $title = $e(ucfirst($surface) . ' draft preview');
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><title>' . $title . '</title>'
        . '<link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css"><link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css"><link rel="stylesheet" href="/css/nexus-theme.css?v=' . $e(NEXUS_THEME_VERSION) . '">'
        . '<style>' . $customCss . 'html,body{min-height:100%;overflow:auto}body{background:var(--nexus-page);color:var(--nexus-text);margin:0}.nexus-preview-note{display:none}.nexus-preview-mobile-menu{background:var(--nexus-surface);box-shadow:0 1rem 2.5rem rgba(11,10,23,.18);display:grid;gap:.35rem;margin:1rem;padding:1rem}.nexus-preview-mobile-menu a{border-radius:var(--nexus-radius);color:var(--nexus-text);display:flex;gap:.75rem;padding:.75rem}.nexus-preview-mobile-menu a.active{background:var(--nexus-gradient);color:var(--nexus-primary-contrast)}.nexus-print-preview{background:#fff!important}.nexus-print-preview .card{box-shadow:none}</style></head>' . $content . '<script>' . nexusThemeColorModeScript($settings) . '</script></html>';
}

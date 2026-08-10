<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$packageRoot = dirname(__DIR__);
$manager = $packageRoot . DIRECTORY_SEPARATOR . 'manager.php';
$manifest = json_decode((string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexus-theme-manager-tests-' . bin2hex(random_bytes(6));
$passes = 0;

function copyTree(string $source, string $destination): void
{
    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException("Could not create $destination");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
                throw new RuntimeException("Could not create $target");
            }
        } elseif (!copy($item->getPathname(), $target)) {
            throw new RuntimeException("Could not copy $target");
        }
    }
}

function removeTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function makeFixture(string $packageRoot, string $destination): void
{
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'baseline', $destination);
    if (!is_dir($destination . DIRECTORY_SEPARATOR . 'css')) {
        mkdir($destination . DIRECTORY_SEPARATOR . 'css', 0777, true);
    }
    if (!is_dir($destination . DIRECTORY_SEPARATOR . 'uploads')) {
        mkdir($destination . DIRECTORY_SEPARATOR . 'uploads', 0777, true);
    }
    file_put_contents($destination . DIRECTORY_SEPARATOR . 'config.php', "<?php\n// Test fixture only.\n");
}

function runManager(string $manager, array $arguments, int $expectedExit): array
{
    $command = array_merge([PHP_BINARY, $manager], $arguments);
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start manager process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== $expectedExit) {
        throw new RuntimeException(
            'Unexpected exit code. Expected ' . $expectedExit . ', got ' . $exit .
            "\nCommand: " . implode(' ', $command) . "\nSTDOUT:\n$stdout\nSTDERR:\n$stderr"
        );
    }
    return [$stdout, $stderr];
}

function expect(bool $condition, string $message): void
{
    global $passes;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passes++;
    fwrite(STDOUT, "PASS: $message\n");
}

function sha(string $path): string
{
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException("Could not hash $path");
    }
    return $hash;
}

try {
    mkdir($testRoot, 0777, true);

    $adminPageSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'nexus.php');
    $adminPostSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'post' . DIRECTORY_SEPARATOR . 'nexus.php');
    $adminNavSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'side_nav.php');
    $themeCssSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'nexus-theme.css');
    $agentHeaderSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php');
    $clientHeaderSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php');
    $guestHeaderSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'guest_header.php');
    $loginSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'login.php');
    $resetSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'login_reset.php');
    $mfaSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'agent' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'mfa_enforcement.php');
    expect(str_contains($adminPageSource, "require_once 'includes/inc_all_admin.php'"), 'administration page uses ITFlow administrator permission enforcement');
    expect(str_contains($adminPostSource, 'validateCSRFToken()'), 'administration action validates the ITFlow CSRF token');
    expect(!preg_match('/\\b(?:exec|shell_exec|system|passthru|proc_open)\\s*\\(/', $adminPostSource), 'administration action cannot launch lifecycle shell commands');
    expect(str_contains($adminNavSource, '/admin/nexus.php'), 'administration navigation exposes the Nexus Theme Manager');
    expect(str_contains($adminNavSource, 'brand-link nexus-admin-back') && str_contains($themeCssSource, '.nexus-agent .main-sidebar .nexus-admin-back'), 'administration return navigation uses the compact Nexus treatment');
    expect(str_contains($adminNavSource, 'NEXUS_MANAGER_VERSION'), 'administration navigation reports the installed manager version');
    foreach ([$loginSource, $resetSource, $mfaSource] as $authSource) {
        expect(str_contains($authSource, "'nexus-auth-brand--logo' : 'nexus-auth-brand--text'"), 'authentication template marks logo and text branding as mutually exclusive');
        expect(str_contains($authSource, "['branding']['tagline']"), 'authentication template renders the configured brand tagline');
    }
    expect(str_contains($themeCssSource, '.nexus-auth .login-logo.nexus-auth-brand--logo > :not(img)') && str_contains($themeCssSource, 'font-size: 0;'), 'logo branding suppresses duplicate title content while preserving the image');
    expect(str_contains($themeCssSource, '.nexus-auth .nexus-auth-title') && str_contains($themeCssSource, 'color: var(--nexus-white);'), 'authentication heading remains readable on the dark login card');
    expect(str_contains($themeCssSource, '.nexus-theme .modal.fade .modal-dialog') && str_contains($themeCssSource, '@keyframes nexus-notice-in'), 'theme includes modal and notification motion treatments');
    expect(str_contains($themeCssSource, '@media (prefers-reduced-motion: reduce)') && str_contains($themeCssSource, '.nexus-theme.nexus-motion-reduced'), 'motion treatments preserve user and operating-system reduced-motion preferences');
    expect(str_contains($themeCssSource, 'linear-gradient(120deg, var(--nexus-night)') && str_contains($adminPageSource, 'function updateStudioPalette()'), 'Theme Studio hero follows saved and previewed palette colors');
    expect(str_contains($adminPageSource, 'data-preview-panel="navigation"') && str_contains($themeCssSource, '.nexus-preview-shell'), 'Theme Studio includes a live sidebar and header preview');
    expect(str_contains($adminPageSource, 'id="nexus-density"') && str_contains($adminPageSource, 'shell.dataset.contentDensity') && str_contains($themeCssSource, '[data-content-density="compact"]'), 'content density updates the navigation preview and rendered interface');
    expect(str_contains($adminPageSource, 'shell.dataset.menuDensity') && str_contains($themeCssSource, '[data-menu-density="spacious"]'), 'menu density updates the navigation preview');
    expect(str_contains($adminPageSource, "showPreviewMode('navigation')") && str_contains($adminPageSource, 'data-preview-header-label'), 'layout controls reveal the affected preview surface and identify the header treatment');
    expect(str_contains($themeCssSource, '.nexus-agent.nexus-header-gradient .main-header') && str_contains($themeCssSource, '.nexus-agent.nexus-header-glass .main-header'), 'header treatments have distinct rendered styles');
    expect(str_contains($themeCssSource, '.nexus-agent.nexus-navigation-pill .nav-sidebar .nav-link.active') && str_contains($themeCssSource, '.nexus-agent.nexus-navigation-rail .nav-sidebar .nav-link.active') && str_contains($themeCssSource, '.nexus-agent.nexus-navigation-outline .nav-sidebar .nav-link.active'), 'active navigation treatments cover top-level and nested links');
    expect(str_contains($themeCssSource, '.nexus-agent.nexus-sidebar-compact .nav-sidebar .nav-icon') && str_contains($themeCssSource, '.nexus-preview-shell.is-compact .nexus-preview-sidebar'), 'compact sidebar changes labels, icons, spacing, and preview width');
    expect(str_contains($adminPageSource, 'nexus_logo_light') && str_contains($adminPageSource, 'nexus_logo_dark') && str_contains($adminPageSource, 'nexus_login_background'), 'Theme Studio exposes responsive logos and custom login imagery');
    expect(str_contains($adminPostSource, "['branding']['asset_revision'] = bin2hex(random_bytes(8))"), 'asset uploads and removals rotate the browser cache revision');
    foreach ([$adminPageSource, $loginSource, $resetSource, $mfaSource, $agentHeaderSource, $clientHeaderSource] as $assetSurfaceSource) {
        expect(str_contains($assetSurfaceSource, 'nexusThemeVersionedAssetUrl'), 'rendered Nexus surfaces use versioned custom asset URLs');
    }
    expect(str_contains($guestHeaderSource, 'nexus-guest-invoice') && str_contains($guestHeaderSource, 'Secure billing portal') && str_contains($guestHeaderSource, "['branding']['tagline']"), 'guest invoices use the branded Nexus masthead and tagline');
    expect(str_contains($themeCssSource, '.nexus-guest-invoice') && str_contains($themeCssSource, '.nexus-guest-masthead'), 'guest invoice layout uses the responsive Nexus billing shell');
    expect(str_contains($adminPostSource, "nexus_preset_action") && str_contains($adminPostSource, "nexus_schedule_command") && str_contains($adminPostSource, "nexus_theme_rollback"), 'administration actions support presets, scheduling, and rollback');

    $fixture = $testRoot . DIRECTORY_SEPARATOR . 'fixture';
    $stateRoot = $testRoot . DIRECTORY_SEPARATOR . 'state';
    makeFixture($packageRoot, $fixture);
    $common = ['--root', $fixture, '--state-root', $stateRoot];

    runManager($manager, array_merge(['doctor'], $common), 0);
    expect(!file_exists($fixture . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'nexus-theme.css'), 'doctor is non-mutating');

    runManager($manager, array_merge(['install'], $common, ['--yes']), 0);
    foreach ($manifest['files'] as $entry) {
        $target = $fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        expect(is_file($target) && sha($target) === $entry['payload_sha256'], 'install activates ' . $entry['path']);
    }

    require_once $fixture . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'nexus_theme.php';
    expect(nexusThemeIsEnabled($fixture), 'web control defaults to an active Nexus theme');
    expect(nexusThemeControlIsWritable($fixture), 'web control detects a writable ITFlow uploads directory');
    nexusThemeSetEnabled(false, $fixture);
    expect(!nexusThemeIsEnabled($fixture), 'web control pauses the Nexus visual layer');
    nexusThemeSetEnabled(true, $fixture);
    expect(nexusThemeIsEnabled($fixture), 'web control reactivates the Nexus visual layer');

    $themeDefaults = nexusThemeSettings($fixture);
    expect($themeDefaults['preset'] === 'aurora', 'theme customization starts with the Aurora preset');
    expect($themeDefaults['branding']['logo_path'] === '', 'theme customization starts without a logo override');
    expect($themeDefaults['branding']['logo_light_path'] === '' && $themeDefaults['branding']['logo_dark_path'] === '', 'theme customization supports separate light and dark logos');
    expect($themeDefaults['branding']['asset_revision'] === '0000000000000000', 'theme customization starts with a valid asset cache revision');
    expect(isset(nexusThemeAllowedImageTypes('logo-light')[IMAGETYPE_GIF]) && !isset(nexusThemeAllowedImageTypes('favicon')[IMAGETYPE_GIF]), 'animated GIFs are allow-listed only for logo assets');
    expect($themeDefaults['branding']['show_agent_logo'] === true, 'theme customization shows a custom logo in technician navigation by default');
    expect($themeDefaults['appearance']['motion_style'] === 'fluid', 'theme customization starts with the fluid popup motion profile');
    $legacyTheme = $themeDefaults;
    unset($legacyTheme['branding']['show_agent_logo']);
    expect(nexusThemeValidateSettings($legacyTheme)['branding']['show_agent_logo'] === true, 'existing settings inherit technician navigation logo placement when upgraded');

    $customTheme = $themeDefaults;
    $customTheme['preset'] = 'custom';
    $customTheme['branding']['brand_name'] = '<b>Nexus Support</b>';
    $customTheme['branding']['tagline'] = "Always ready\nfor you";
    $customTheme['branding']['logo_path'] = '/outside/unsafe.svg';
    $customTheme['branding']['asset_revision'] = 'bad?cache=off';
    $customTheme['content']['login_heading'] = 'Your support hub';
    $customTheme['colors']['primary'] = '#12ABEF';
    $customTheme['colors']['secondary'] = 'not-a-color';
    $customTheme['appearance']['radius'] = 'rounded';
    $customTheme['appearance']['density'] = 'compact';
    $customTheme['appearance']['menu_density'] = 'spacious';
    $customTheme['appearance']['sidebar_width'] = 900;
    $customTheme['appearance']['header_style'] = 'glass';
    $customTheme['appearance']['navigation_style'] = 'rail';
    $customTheme['appearance']['font_scale'] = 500;
    $customTheme['appearance']['motion_style'] = 'snappy';
    $customTheme['appearance']['reduce_motion'] = true;
    $savedTheme = nexusThemeSaveSettings($customTheme, $fixture);
    expect($savedTheme['branding']['brand_name'] === 'Nexus Support', 'theme customization strips markup from brand text');
    expect($savedTheme['branding']['tagline'] === 'Always ready for you', 'theme customization normalizes single-line brand text');
    expect($savedTheme['branding']['logo_path'] === '', 'theme customization rejects untrusted logo paths');
    expect($savedTheme['branding']['asset_revision'] === '0000000000000000', 'theme customization rejects malformed asset cache revisions');
    expect($savedTheme['colors']['primary'] === '#12abef', 'theme customization normalizes valid colors');
    expect($savedTheme['colors']['secondary'] === $themeDefaults['colors']['secondary'], 'theme customization rejects invalid colors');
    expect($savedTheme['appearance']['font_scale'] === 110, 'theme customization clamps interface scale');
    expect($savedTheme['appearance']['motion_style'] === 'snappy', 'theme customization accepts an allow-listed popup motion profile');
    expect($savedTheme['appearance']['sidebar_width'] === 340, 'theme customization clamps sidebar width');
    expect(nexusThemeSettings($fixture)['content']['login_heading'] === 'Your support hub', 'theme customization persists atomically');
    $firstSettingsVersion = nexusThemeSettingsVersion($fixture);
    $savedTheme['content']['login_heading'] = 'Support, your way';
    nexusThemeSaveSettings($savedTheme, $fixture);
    expect(nexusThemeSettings($fixture)['content']['login_heading'] === 'Support, your way', 'theme customization safely replaces existing settings');
    expect(nexusThemeSettingsVersion($fixture) !== $firstSettingsVersion, 'theme customization changes its generated stylesheet cache key');
    file_put_contents(nexusThemeSettingsPath($fixture), '{"preset":"custom","colors":{"primary":"red;}body{display:none"}}');
    $tamperedThemeSettings = nexusThemeSettings($fixture);
    expect($tamperedThemeSettings['colors']['primary'] === $themeDefaults['colors']['primary'], 'runtime revalidates manually tampered settings');
    expect(!str_contains(nexusThemeCustomCss($tamperedThemeSettings), 'display:none'), 'generated stylesheet excludes injected declarations');
    nexusThemeSaveSettings($savedTheme, $fixture);
    expect(str_contains(nexusThemeCustomCss($savedTheme), '--nexus-cyan:#12abef'), 'theme customization renders validated CSS properties');
    expect(nexusThemeBodyClasses($savedTheme) === 'nexus-density-compact nexus-menu-spacious nexus-header-glass nexus-navigation-rail nexus-motion-snappy nexus-motion-reduced', 'theme customization renders safe behavior classes');
    expect(nexusThemeBrandName('ITFlow', $savedTheme) === 'Nexus Support', 'custom brand name overrides the ITFlow fallback');
    $agentLogoTheme = $savedTheme;
    $agentLogoTheme['branding']['logo_light_path'] = '/uploads/nexus-theme/logo-light.png';
    $agentLogoTheme['branding']['logo_path'] = '/uploads/nexus-theme/logo-light.png';
    $agentLogoTheme['branding']['asset_revision'] = '0123456789abcdef';
    $agentLogoCss = nexusThemeCustomCss(nexusThemeValidateSettings($agentLogoTheme));
    expect(str_contains($agentLogoCss, 'background-image:url("/uploads/nexus-theme/logo-light.png?v=0123456789abcdef")') && str_contains($agentLogoCss, 'clip-path:inset(50%)'), 'custom logo uses a cache-busting URL in technician navigation while preserving its accessible name');
    expect(nexusThemeVersionedAssetUrl('/uploads/nexus-theme/logo-light.png', $agentLogoTheme) === '/uploads/nexus-theme/logo-light.png?v=0123456789abcdef', 'managed asset URLs include the current upload revision');
    expect(nexusThemeVersionedAssetUrl('/uploads/settings/company.png', $agentLogoTheme) === '/uploads/settings/company.png', 'native ITFlow assets are not rewritten by Nexus cache busting');
    $agentLogoTheme['branding']['show_agent_logo'] = false;
    expect(!str_contains(nexusThemeCustomCss(nexusThemeValidateSettings($agentLogoTheme)), 'background-image:url("/uploads/nexus-theme/logo-light.png?v=0123456789abcdef")'), 'technician navigation logo placement can be disabled independently');
    expect(nexusThemeContrastColor('#ffffff') === '#0b0a17' && nexusThemeContrastColor('#000000') === '#ffffff', 'theme customization derives readable accent contrast');
    expect(nexusThemeMixColors('#ffffff', '#000000', 50) === '#808080', 'theme customization derives supporting palette colors');
    expect(nexusThemeValidateSettings(['preset' => 'ocean'])['colors']['primary'] === nexusThemePresets()['ocean']['primary'], 'curated presets resolve to their protected palette');
    expect(nexusThemeValidateSettings(['appearance' => ['motion_style' => 'spin-forever']])['appearance']['motion_style'] === 'fluid', 'theme customization rejects unknown popup motion profiles');
    $gifLogoTheme = $themeDefaults;
    $gifLogoTheme['branding']['logo_light_path'] = '/uploads/nexus-theme/logo-light.gif';
    expect(nexusThemeValidateSettings($gifLogoTheme)['branding']['logo_light_path'] === '/uploads/nexus-theme/logo-light.gif', 'theme customization preserves validated GIF logo paths');
    $rollbackTheme = $savedTheme;
    $rollbackTheme['content']['login_heading'] = 'Rollback target';
    nexusThemeSaveSettings($rollbackTheme, $fixture);
    expect(nexusThemeCanRollback($fixture), 'theme customization snapshots the previous design');
    $preRollbackAssetRevision = nexusThemeSettings($fixture)['branding']['asset_revision'];
    nexusThemeRollback($fixture);
    expect(nexusThemeSettings($fixture)['content']['login_heading'] === 'Support, your way', 'one-click rollback restores the prior design');
    expect(nexusThemeSettings($fixture)['branding']['asset_revision'] !== $preRollbackAssetRevision, 'one-click rollback rotates the asset cache revision');
    $presetId = nexusThemeSavePreset('Operations', nexusThemeSettings($fixture), $fixture);
    expect(count(nexusThemeSavedPresets($fixture)) === 1, 'saved presets persist a validated design');
    nexusThemeApplyPreset($presetId, $fixture);
    nexusThemeDeletePreset($presetId, $fixture);
    expect(nexusThemeSavedPresets($fixture) === [], 'saved presets can be applied and deleted');
    $scheduled = nexusThemeSetSchedule('disable', gmdate('Y-m-d\TH:i:s\Z', time() + 60), $fixture);
    expect($scheduled['action'] === 'disable' && nexusThemeSchedule($fixture) !== null, 'scheduled activation validates and persists a future action');
    nexusThemeCancelSchedule($fixture);
    expect(nexusThemeSchedule($fixture) === null, 'scheduled activation can be cancelled');

    expect(!nexusUpdaterReady($fixture) && nexusUpdaterStatus($fixture)['state'] === 'not_configured', 'GUI updater reports its one-time setup requirement');
    try {
        nexusUpdaterQueue('check', $fixture);
        throw new RuntimeException('queue unexpectedly accepted without updater service');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), 'not installed'), 'GUI updater refuses requests before protected service setup');
    }
    nexusThemeAtomicWrite(nexusUpdaterControlPath(NEXUS_UPDATER_READY_FILE, $fixture), json_encode([
        'schema' => 1,
        'instance_id' => str_repeat('a', 16),
    ], JSON_THROW_ON_ERROR));
    expect(nexusUpdaterReady($fixture), 'GUI updater recognizes a valid protected-service marker');
    $updateRequestId = nexusUpdaterQueue('check', $fixture);
    $queuedUpdate = json_decode((string)file_get_contents(nexusUpdaterControlPath(NEXUS_UPDATER_REQUEST_FILE, $fixture)), true, 8, JSON_THROW_ON_ERROR);
    expect($queuedUpdate['action'] === 'check' && $queuedUpdate['request_id'] === $updateRequestId, 'GUI updater queues only the selected allow-listed action');
    try {
        nexusUpdaterQueue('update', $fixture);
        throw new RuntimeException('duplicate queue unexpectedly accepted');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), 'already queued'), 'GUI updater refuses duplicate queued operations');
    }
    unlink(nexusUpdaterControlPath(NEXUS_UPDATER_REQUEST_FILE, $fixture));
    nexusThemeAtomicWrite(nexusUpdaterControlPath(NEXUS_UPDATER_STATUS_FILE, $fixture), json_encode([
        'schema' => 1,
        'state' => 'running',
        'message' => '<b>Installing</b>',
        'current_version' => '2.5.1',
        'latest_version' => '3.0.0',
        'release_url' => 'https://evil.invalid/release',
        'updated_at' => gmdate('c'),
    ], JSON_THROW_ON_ERROR));
    $runningUpdate = nexusUpdaterStatus($fixture);
    expect($runningUpdate['message'] === 'Installing' && $runningUpdate['release_url'] === null, 'GUI updater sanitizes privileged-service status before display');
    try {
        nexusUpdaterQueue('update', $fixture);
        throw new RuntimeException('busy queue unexpectedly accepted');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), 'already running'), 'GUI updater refuses concurrent operations while the service is busy');
    }
    nexusThemeAtomicWrite(nexusUpdaterControlPath(NEXUS_UPDATER_STATUS_FILE, $fixture), json_encode([
        'schema' => 1,
        'state' => 'running',
        'message' => 'Installing',
        'updated_at' => gmdate('c', time() - 1800),
    ], JSON_THROW_ON_ERROR));
    expect(nexusUpdaterStatus($fixture)['state'] === 'failed', 'GUI updater turns stale progress into a recoverable failure state');

    runManager($manager, array_merge(['verify'], $common), 0);
    [$statusJson] = runManager($manager, array_merge(['status'], $common, ['--json']), 0);
    $status = json_decode($statusJson, true, 512, JSON_THROW_ON_ERROR);
    expect($status['status'] === 'healthy' && $status['mode'] === 'enabled', 'status reports a healthy enabled install');

    runManager($manager, array_merge(['install'], $common, ['--yes']), 3);
    expect(true, 'a second install is refused');
    runManager($manager, array_merge(['enable'], $common, ['--yes']), 3);
    expect(true, 'enable is refused while already enabled');

    nexusThemeSetEnabled(false, $fixture);
    runManager($manager, array_merge(['disable'], $common, ['--yes']), 0);
    expect(nexusThemeIsEnabled($fixture), 'CLI disable clears the web theme-control marker');
    expect(is_file(nexusThemeSettingsPath($fixture)), 'CLI disable preserves administrator customization');
    foreach ($manifest['files'] as $entry) {
        $target = $fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        if ($entry['baseline_sha256'] === null) {
            expect(!file_exists($target), 'disable removes theme-owned ' . $entry['path']);
        } else {
            expect(is_file($target) && sha($target) === $entry['baseline_sha256'], 'disable restores ' . $entry['path']);
        }
    }
    runManager($manager, array_merge(['verify'], $common), 0);
    runManager($manager, array_merge(['disable'], $common, ['--yes']), 3);
    expect(true, 'disable is refused while already disabled');

    runManager($manager, array_merge(['enable'], $common, ['--yes']), 0);
    runManager($manager, array_merge(['verify'], $common), 0);
    expect(true, 'enable reapplies and verifies the payload');

    $login = $fixture . DIRECTORY_SEPARATOR . 'login.php';
    file_put_contents($login, "\n// post-install drift\n", FILE_APPEND);
    runManager($manager, array_merge(['uninstall'], $common, ['--yes']), 4);
    expect(str_contains((string)file_get_contents($login), 'post-install drift'), 'uninstall refuses to overwrite post-install drift');
    copy($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'login.php', $login);

    runManager($manager, array_merge(['uninstall'], $common, ['--yes']), 0);
    foreach ($manifest['files'] as $entry) {
        $target = $fixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        if ($entry['baseline_sha256'] === null) {
            expect(!file_exists($target), 'uninstall removes theme-owned ' . $entry['path']);
        } else {
            expect(is_file($target) && sha($target) === $entry['baseline_sha256'], 'uninstall restores ' . $entry['path']);
        }
    }
    [$notInstalledJson] = runManager($manager, array_merge(['status'], $common, ['--json']), 0);
    $notInstalled = json_decode($notInstalledJson, true, 512, JSON_THROW_ON_ERROR);
    expect($notInstalled['status'] === 'not-installed', 'status reports not installed after uninstall');
    $archives = glob($stateRoot . DIRECTORY_SEPARATOR . 'archives' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    expect(is_array($archives) && count($archives) === 1, 'safe uninstall archives recovery state outside the fixture');

    $incompatible = $testRoot . DIRECTORY_SEPARATOR . 'incompatible';
    makeFixture($packageRoot, $incompatible);
    file_put_contents($incompatible . DIRECTORY_SEPARATOR . 'login.php', "\n// incompatible local change\n", FILE_APPEND);
    runManager($manager, ['doctor', '--root', $incompatible, '--state-root', $testRoot . DIRECTORY_SEPARATOR . 'incompatible-state'], 3);
    expect(!file_exists($incompatible . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'nexus-theme.css'), 'incompatible checkout is refused without mutation');

    $adoptFixture = $testRoot . DIRECTORY_SEPARATOR . 'adopt-fixture';
    $adoptState = $testRoot . DIRECTORY_SEPARATOR . 'adopt-state';
    makeFixture($packageRoot, $adoptFixture);
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'payload', $adoptFixture);
    nexusThemeSetEnabled(false, $adoptFixture);
    $adoptCommon = ['--root', $adoptFixture, '--state-root', $adoptState];
    runManager($manager, array_merge(['adopt'], $adoptCommon, ['--yes']), 0);
    expect(!nexusThemeIsEnabled($adoptFixture), 'adopt preserves the current web presentation state');
    [$adoptStatusJson] = runManager($manager, array_merge(['status'], $adoptCommon, ['--json']), 0);
    $adoptStatus = json_decode($adoptStatusJson, true, 512, JSON_THROW_ON_ERROR);
    expect($adoptStatus['status'] === 'healthy' && $adoptStatus['mode'] === 'enabled', 'adopt manages an existing exact theme installation');
    runManager($manager, array_merge(['verify'], $adoptCommon), 0);
    expect(true, 'adopted installation verifies');
    runManager($manager, array_merge(['uninstall'], $adoptCommon, ['--yes', '--purge']), 0);
    expect(nexusThemeIsEnabled($adoptFixture), 'adopted uninstall clears the web presentation-state marker');
    $adoptRestored = true;
    foreach ($manifest['files'] as $entry) {
        $target = $adoptFixture . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
        $adoptRestored = $adoptRestored && ($entry['baseline_sha256'] === null
            ? !file_exists($target)
            : is_file($target) && sha($target) === $entry['baseline_sha256']);
    }
    expect($adoptRestored, 'uninstall restores the supported baseline after adoption');

    $badAdoptFixture = $testRoot . DIRECTORY_SEPARATOR . 'bad-adopt-fixture';
    makeFixture($packageRoot, $badAdoptFixture);
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'payload', $badAdoptFixture);
    file_put_contents($badAdoptFixture . DIRECTORY_SEPARATOR . 'login.php', "\n// unknown theme drift\n", FILE_APPEND);
    runManager(
        $manager,
        ['adopt', '--root', $badAdoptFixture, '--state-root', $testRoot . DIRECTORY_SEPARATOR . 'bad-adopt-state', '--yes'],
        3
    );
    expect(true, 'adopt refuses a non-exact manual installation');

    $purgeFixture = $testRoot . DIRECTORY_SEPARATOR . 'purge-fixture';
    $purgeState = $testRoot . DIRECTORY_SEPARATOR . 'purge-state';
    makeFixture($packageRoot, $purgeFixture);
    $purgeCommon = ['--root', $purgeFixture, '--state-root', $purgeState];
    runManager($manager, array_merge(['install'], $purgeCommon, ['--yes']), 0);
    runManager($manager, array_merge(['uninstall'], $purgeCommon, ['--yes', '--purge']), 0);
    [$purgeStatusJson] = runManager($manager, array_merge(['status'], $purgeCommon, ['--json']), 0);
    $purgeStatus = json_decode($purgeStatusJson, true, 512, JSON_THROW_ON_ERROR);
    expect($purgeStatus['status'] === 'not-installed', 'purge uninstall removes active state');

    $tamperedPackage = $testRoot . DIRECTORY_SEPARATOR . 'tampered-package';
    mkdir($tamperedPackage, 0777, true);
    copy($manager, $tamperedPackage . DIRECTORY_SEPARATOR . 'manager.php');
    copy($packageRoot . DIRECTORY_SEPARATOR . 'manifest.json', $tamperedPackage . DIRECTORY_SEPARATOR . 'manifest.json');
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'baseline', $tamperedPackage . DIRECTORY_SEPARATOR . 'baseline');
    copyTree($packageRoot . DIRECTORY_SEPARATOR . 'payload', $tamperedPackage . DIRECTORY_SEPARATOR . 'payload');
    file_put_contents(
        $tamperedPackage . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'nexus-theme.css',
        "\n/* tampered */\n",
        FILE_APPEND
    );
    $tamperFixture = $testRoot . DIRECTORY_SEPARATOR . 'tamper-fixture';
    makeFixture($packageRoot, $tamperFixture);
    runManager(
        $tamperedPackage . DIRECTORY_SEPARATOR . 'manager.php',
        ['doctor', '--root', $tamperFixture, '--state-root', $testRoot . DIRECTORY_SEPARATOR . 'tamper-state'],
        4
    );
    expect(true, 'package checksum tampering is detected');

    fwrite(STDOUT, "\nLifecycle test result: $passes assertions passed, 0 failed.\n");
    removeTree($testRoot);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: {$error->getMessage()}\nTest workspace retained at: $testRoot\n");
    exit(1);
}

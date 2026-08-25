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
    $agentTicketsSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'agent' . DIRECTORY_SEPARATOR . 'tickets.php');
    $agentHeaderSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php');
    $clientHeaderSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php');
    $guestHeaderSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'guest_header.php');
    $guestInvoiceSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'guest_view_invoice.php');
    $invoicePdfEndpointSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'nexus_invoice_pdf.php');
    $invoicePdfHelperSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'nexus_invoice_pdf.php');
    $loginSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'login.php');
    $resetSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'login_reset.php');
    $mfaSource = (string)file_get_contents($packageRoot . DIRECTORY_SEPARATOR . 'payload' . DIRECTORY_SEPARATOR . 'agent' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'mfa_enforcement.php');
    $managerSource = (string)file_get_contents($manager);
    expect(count($manifest['files']) === 23 && str_contains($managerSource, 'const NEXUS_MANAGED_FILE_COUNT = 23;'), 'manager accepts the complete bounded 23-file manifest');
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
    expect(str_contains($clientHeaderSource, 'nexus-client-brand--logo') && str_contains($clientHeaderSource, 'nexus-client-nav-logo') && str_contains($clientHeaderSource, '$nexus_portal_has_logo'), 'client portal renders the configured logo inside the navigation brand');
    expect(str_contains($themeCssSource, '.navbar-brand.nexus-client-brand--logo::before') && str_contains($themeCssSource, '.nexus-client .nexus-client-nav-logo'), 'client portal replaces the decorative brand marker with the configured responsive logo');
    expect(str_contains($themeCssSource, '.nexus-auth .nexus-auth-title') && str_contains($themeCssSource, 'color: var(--nexus-white);'), 'authentication heading remains readable on the dark login card');
    expect(str_contains($themeCssSource, '.nexus-theme .modal.fade .modal-dialog') && str_contains($themeCssSource, '@keyframes nexus-notice-in'), 'theme includes modal and notification motion treatments');
    expect(str_contains($themeCssSource, '@media (prefers-reduced-motion: reduce)') && str_contains($themeCssSource, '.nexus-theme.nexus-motion-reduced'), 'motion treatments preserve user and operating-system reduced-motion preferences');
    expect(str_contains($themeCssSource, 'linear-gradient(120deg, var(--nexus-night)') && str_contains($adminPageSource, 'function updateStudioPalette()'), 'Theme Studio hero follows the selected workspace palette');
    expect(substr_count($adminPageSource, 'nexus-preview-mode') >= 9 && str_contains($adminPageSource, 'data-mode="auth"') && str_contains($adminPageSource, 'data-mode="reset"') && str_contains($adminPageSource, 'data-mode="dashboard"') && str_contains($adminPageSource, 'data-mode="technician"') && str_contains($adminPageSource, 'data-mode="client"') && str_contains($adminPageSource, 'data-mode="mobile"') && str_contains($adminPageSource, 'data-mode="invoice"') && str_contains($adminPageSource, 'data-mode="print"'), 'Theme Studio exposes exact previews for eight live surface families');
    expect(str_contains($adminPageSource, 'nexusThemePreviewDocument(') && str_contains($adminPageSource, 'sandbox=""') && str_contains($adminPageSource, 'srcdoc='), 'Theme Studio renders isolated server-generated runtime previews');
    expect(str_contains($adminPageSource, 'id="nexus-density"') && str_contains($adminPageSource, "showPreviewMode('technician')") && str_contains($themeCssSource, '.nexus-theme.nexus-density-compact'), 'layout controls map to the technician runtime preview and rendered interface');
    expect(str_contains($adminPageSource, 'same validated settings model') && str_contains($adminPageSource, 'same') && str_contains($adminPageSource, 'Nexus stylesheet'), 'Theme Studio explains the runtime-parity preview contract');
    expect(substr_count($adminPageSource, 'data-workspace-section=') === 8 && str_contains($adminPageSource, 'data-workspace-section="quality"') && str_contains($adminPageSource, 'data-workspace-section="operations"') && str_contains($adminPageSource, 'data-workspace-section="system"'), 'Theme Studio exposes eight focused design and management sections');
    expect(str_contains($adminPageSource, 'function showWorkspaceSection(') && str_contains($adminPageSource, 'window.history.replaceState') && str_contains($adminPageSource, 'window.location.hash'), 'Theme Studio section navigation is refresh-stable and deep-linkable');
    expect(str_contains($adminPageSource, 'id="nexus-motion"') && str_contains($adminPageSource, 'data-workspace-section="motion"'), 'motion controls live in their own focused Theme Studio section');
    expect(str_contains($themeCssSource, '.nexus-studio-workspace[data-active-section="operations"]') && str_contains($themeCssSource, '.nexus-workspace-panel { display: none; }'), 'management panels stay out of the design workspace until selected');
    expect(str_contains($themeCssSource, 'grid-template-columns: 17rem minmax(0, 1fr)') && str_contains($themeCssSource, '@media (max-width: 1199.98px)'), 'Theme Studio navigation uses a desktop rail and responsive compact treatment');
    expect(str_contains($themeCssSource, '.nexus-agent.nexus-header-gradient .main-header') && str_contains($themeCssSource, '.nexus-agent.nexus-header-glass .main-header'), 'header treatments have distinct rendered styles');
    expect(str_contains($themeCssSource, '.nexus-agent.nexus-navigation-pill .nav-sidebar .nav-link.active') && str_contains($themeCssSource, '.nexus-agent.nexus-navigation-rail .nav-sidebar .nav-link.active') && str_contains($themeCssSource, '.nexus-agent.nexus-navigation-outline .nav-sidebar .nav-link.active'), 'active navigation treatments cover top-level and nested links');
    expect(str_contains($themeCssSource, '.nexus-agent.nexus-sidebar-compact .nav-sidebar .nav-icon') && str_contains($themeCssSource, '.nexus-runtime-preview-frame'), 'compact sidebar changes live labels and is rendered through the runtime preview');
    expect(str_contains($adminPageSource, 'id="nexus-accessibility-summary"') && str_contains($adminPageSource, 'updateAccessibilityReport()') && str_contains($themeCssSource, '.nexus-accessibility-inspector'), 'Theme Studio exposes a live accessibility inspector');
    expect(str_contains($adminPageSource, 'data-responsive-width="1600"') && str_contains($adminPageSource, 'data-responsive-width="1366"') && str_contains($adminPageSource, 'data-responsive-width="768"') && str_contains($adminPageSource, 'data-responsive-width="390"') && str_contains($adminPageSource, 'id="nexus-responsive-width"'), 'responsive tester provides widescreen, laptop, tablet, phone, and custom widths');
    expect(str_contains($adminPageSource, 'availableWidth / width') && str_contains($adminPageSource, 'window.addEventListener(\'resize\', updateResponsivePreview)') && str_contains($themeCssSource, '--nexus-preview-width') && str_contains($themeCssSource, '--nexus-preview-scale'), 'responsive tester fits the shared runtime preview to its available viewport');
    expect(str_contains($agentTicketsSource, 'nexus-ticket-queue-summary') && str_contains($agentTicketsSource, 'Ticket queue') && str_contains($themeCssSource, '.nexus-agent .nexus-ticket-queue-grid'), 'technician tickets render the responsive Nexus queue summary shown in the theme preview');
    expect(str_contains($agentTicketsSource, "ticket_reply_type IN ('Public', 'Client')") && str_contains($agentTicketsSource, "= 'Public' THEN 1 ELSE 0"), 'waiting-on-client counts use the latest client-visible reply direction');
    expect(str_contains($agentTicketsSource, "ticket_priority IN ('High', 'Urgent')") && str_contains($agentTicketsSource, 'TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at)'), 'queue metrics use live priority and first-response data');
    expect(str_contains($agentTicketsSource, 'if (!empty($nexus_theme_enabled))') && str_contains($agentTicketsSource, '$nexus_ticket_metrics = null;'), 'pausing Nexus removes the queue summary and skips its aggregate queries');
    expect(str_contains($adminPageSource, 'nexus_logo_light') && str_contains($adminPageSource, 'nexus_logo_dark') && str_contains($adminPageSource, 'nexus_login_background'), 'Theme Studio exposes responsive logos and custom login imagery');
    expect(str_contains($adminPageSource, '$nexusBrandPlaceholder = \'Nexus MSP\';') && str_contains($adminPageSource, 'Nexus MSP is preview text only'), 'empty Theme Studio brand fields use the neutral Nexus MSP placeholder');
    expect(!str_contains($adminPageSource, 'placeholder="<?= escapeHtml($session_company_name) ?>"') && !str_contains($adminPageSource, 'json_encode($session_company_name'), 'Theme Studio does not expose the configured company name through empty-field placeholders');
    expect(str_contains($themeCssSource, '.nexus-manager-page { overflow-x: hidden; overflow-x: clip; }') && str_contains($themeCssSource, 'overscroll-behavior-inline: contain') && str_contains($themeCssSource, 'scroll-snap-type: inline proximity'), 'mobile Theme Studio navigation stays within the viewport while preserving horizontal access');
    expect(str_contains($themeCssSource, '.nexus-studio-editor > .card-footer') && str_contains($themeCssSource, 'position: static;') && str_contains($themeCssSource, '.nexus-preview-card .btn-group'), 'mobile editor actions and preview controls no longer overlap or overflow content');
    expect(str_contains($themeCssSource, '.nexus-update-versions { grid-template-columns: 1fr; }') && str_contains($themeCssSource, '.nexus-workspace-panel .btn-group.w-100'), 'mobile management and updater controls stack into touch-friendly rows');
    expect(str_contains($adminPostSource, "['branding']['asset_revision'] = bin2hex(random_bytes(8))"), 'asset uploads and removals rotate the browser cache revision');
    foreach ([$adminPageSource, $loginSource, $resetSource, $mfaSource, $agentHeaderSource, $clientHeaderSource] as $assetSurfaceSource) {
        expect(str_contains($assetSurfaceSource, 'nexusThemeVersionedAssetUrl'), 'rendered Nexus surfaces use versioned custom asset URLs');
    }
    foreach ([$loginSource, $resetSource, $mfaSource, $agentHeaderSource, $clientHeaderSource, $guestHeaderSource] as $styledSurfaceSource) {
        expect(str_contains($styledSurfaceSource, 'nexus-theme.css?v=') && str_contains($styledSurfaceSource, 'NEXUS_THEME_VERSION'), 'rendered Nexus surfaces invalidate the static stylesheet cache after an update');
    }
    expect(str_contains($guestHeaderSource, 'nexus-guest-invoice') && str_contains($guestHeaderSource, 'Secure billing portal') && str_contains($guestHeaderSource, "['branding']['tagline']"), 'guest invoices use the branded Nexus masthead and tagline');
    expect(str_contains($themeCssSource, '.nexus-guest-invoice') && str_contains($themeCssSource, '.nexus-guest-masthead'), 'guest invoice layout uses the responsive Nexus billing shell');
    expect(str_contains($themeCssSource, '.nexus-guest-masthead.d-print-none') && str_contains($themeCssSource, 'display: block !important') && str_contains($guestHeaderSource, 'nexus-guest-logo-print'), 'browser printing preserves a compact print-safe Nexus invoice masthead');
    expect(str_contains($guestInvoiceSource, 'nexus_invoice_pdf.php?invoice_id=') && !str_contains($guestInvoiceSource, 'guest_post.php?export_invoice_pdf='), 'guest invoice download uses the lifecycle-managed Nexus PDF endpoint');
    expect(str_contains($invoicePdfEndpointSource, '!nexusThemeIsEnabled()') && str_contains($invoicePdfEndpointSource, "'export_invoice_pdf' => \$invoice_id") && str_contains($invoicePdfEndpointSource, 'nexusThemeInvoicePdfHtml'), 'paused themes redirect PDF downloads to the original ITFlow renderer');
    expect(str_contains($invoicePdfHelperSource, 'SECURE BILLING PORTAL') && str_contains($invoicePdfHelperSource, 'BILLING DOCUMENT') && str_contains($invoicePdfHelperSource, 'BALANCE DUE'), 'downloadable invoices use the Nexus document hierarchy');
    expect(str_contains($adminPostSource, 'nexusThemeSaveDraftSettings(') && str_contains($adminPostSource, 'nexusThemePublishDraft(') && str_contains($adminPostSource, 'nexusThemeDiscardDraft('), 'administration actions keep draft saving separate from atomic publishing and discard');
    expect(str_contains($adminPageSource, 'nexus_draft_version') && str_contains($adminPostSource, "['nexus_draft_version']"), 'draft mutations carry optimistic concurrency protection across administrator sessions');
    expect(str_contains($adminPostSource, "nexus_preset_action") && str_contains($adminPostSource, "nexus_schedule_command") && str_contains($adminPostSource, 'nexusThemeRestoreRevisionToDraft('), 'administration actions support presets, scheduling, and revision restoration');
    expect(str_contains($adminPageSource, 'Draft comparison') && str_contains($adminPageSource, 'Revision history') && str_contains($adminPageSource, 'Load this published revision into the private draft workspace?'), 'Theme Studio exposes comparison and durable revision history controls');
    expect(str_contains($adminPageSource, 'Apply fixes to draft') && str_contains($adminPageSource, 'data-target-control') && str_contains($adminPostSource, 'nexusThemeApplyQualityFixes('), 'design quality findings link directly to controls and can apply validated draft corrections');
    expect(str_contains($adminPageSource, 'Diagnostics bundle') && str_contains($adminPostSource, 'nexusThemeDiagnostics(') && str_contains($adminPostSource, 'Content-Disposition: attachment'), 'administrators can download a sanitized diagnostic bundle');
    expect(str_contains($adminPostSource, 'nexusThemeSnapshotActive(') && str_contains($adminPostSource, 'Automatic snapshot before Nexus update') && str_contains($adminPostSource, 'Automatic snapshot before configuration import'), 'high-risk operations automatically preserve the active design');
    expect(str_contains($adminPostSource, 'nexusThemePinRevision(') && str_contains($adminPageSource, 'Known good') && str_contains($adminPageSource, 'Unpin'), 'revision history supports protected known-good designs');
    foreach ([$loginSource, $agentHeaderSource, $clientHeaderSource, $guestHeaderSource] as $runtimeSurfaceSource) {
        expect(str_contains($runtimeSurfaceSource, 'nexusThemePresentationModel('), 'live Nexus surfaces share the preview presentation model');
    }

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
    require_once $fixture . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'nexus_invoice_pdf.php';
    expect(nexusThemeIsEnabled($fixture), 'web control defaults to an active Nexus theme');
    expect(nexusThemeControlIsWritable($fixture), 'web control detects a writable ITFlow uploads directory');
    nexusThemeSetEnabled(false, $fixture);
    expect(!nexusThemeIsEnabled($fixture), 'web control pauses the Nexus visual layer');
    nexusThemeSetEnabled(true, $fixture);
    expect(nexusThemeIsEnabled($fixture), 'web control reactivates the Nexus visual layer');

    $invoicePdfHtml = nexusThemeInvoicePdfHtml([
        'brand_name' => 'Nexus Support',
        'tagline' => 'Secure support',
        'company_name' => 'Nexus Support LLC',
        'client_name' => '<script>Client</script>',
        'invoice_label' => 'INV-26',
        'status' => 'Paid',
        'date' => '2026-08-03',
        'due' => '2026-08-10',
        'balance' => '$0.00',
        'items' => [[
            'name' => 'Managed Services',
            'description' => 'Monthly coverage',
            'quantity' => '1',
            'unit_price' => '$100.00',
            'tax' => '$0.00',
            'amount' => '$100.00',
        ]],
        'totals' => [['label' => 'Balance', 'value' => '$0.00', 'important' => true]],
    ], nexusThemeDefaults());
    expect(str_contains($invoicePdfHtml, 'Invoice details') && str_contains($invoicePdfHtml, 'Managed Services'), 'Nexus PDF builder renders branded headings and invoice content');
    expect(str_contains($invoicePdfHtml, '&lt;script&gt;Client&lt;/script&gt;') && !str_contains($invoicePdfHtml, '<script>'), 'Nexus PDF builder escapes database-backed document content');

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
    $immutableLogoTheme = $themeDefaults;
    $immutableLogoTheme['branding']['logo_light_path'] = '/uploads/nexus-theme/logo-light-0123456789abcdef.gif';
    expect(nexusThemeValidateSettings($immutableLogoTheme)['branding']['logo_light_path'] === '/uploads/nexus-theme/logo-light-0123456789abcdef.gif', 'draft-safe immutable logo asset paths remain valid');

    $activeBeforeDraft = nexusThemeSettings($fixture);
    $draftTheme = $activeBeforeDraft;
    $draftTheme['content']['login_heading'] = 'Private draft heading';
    $draftTheme['colors']['header'] = '#123456';
    nexusThemeSaveDraftSettings($draftTheme, $fixture);
    expect(nexusThemeHasDraft($fixture) && nexusThemeSettings($fixture) === $activeBeforeDraft, 'saving a draft never mutates the published design');
    expect(is_file(nexusThemeStateLockPath($fixture)), 'draft mutations serialize version checks and writes through a filesystem lock');
    expect(nexusThemeDraftSettings($fixture)['content']['login_heading'] === 'Private draft heading', 'draft settings persist independently');
    $draftChanges = nexusThemeSettingsDiff($activeBeforeDraft, nexusThemeDraftSettings($fixture));
    expect(count($draftChanges) === 2 && in_array('content.login_heading', array_column($draftChanges, 'path'), true), 'draft comparison reports exact changed fields');
    $staleDraftVersion = nexusThemeDraftVersion($fixture);
    $concurrentDraft = nexusThemeDraftSettings($fixture);
    $concurrentDraft['content']['portal_heading'] = 'Concurrent draft update';
    nexusThemeSaveDraftSettings($concurrentDraft, $fixture);
    try {
        nexusThemeSaveDraftSettings($draftTheme, $fixture, $staleDraftVersion);
        throw new RuntimeException('stale draft write unexpectedly succeeded');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), 'another administrator session'), 'stale administrator drafts cannot overwrite newer work');
    }
    $assetDirectory = nexusThemeAssetPath($fixture);
    if (!is_dir($assetDirectory)) {
        mkdir($assetDirectory, 0777, true);
    }
    $draftAssetName = 'logo-light-aaaaaaaaaaaaaaaa.png';
    $orphanAssetName = 'logo-light-bbbbbbbbbbbbbbbb.png';
    file_put_contents($assetDirectory . DIRECTORY_SEPARATOR . $draftAssetName, 'draft asset');
    file_put_contents($assetDirectory . DIRECTORY_SEPARATOR . $orphanAssetName, 'orphan asset');
    $concurrentDraft['branding']['logo_light_path'] = '/uploads/nexus-theme/' . $draftAssetName;
    $concurrentDraft['branding']['logo_path'] = $concurrentDraft['branding']['logo_light_path'];
    nexusThemeSaveDraftSettings($concurrentDraft, $fixture);
    expect(is_file($assetDirectory . DIRECTORY_SEPARATOR . $draftAssetName) && !is_file($assetDirectory . DIRECTORY_SEPARATOR . $orphanAssetName), 'immutable asset cleanup preserves referenced draft media and removes abandoned uploads');
    foreach (['auth', 'reset', 'dashboard', 'technician', 'client', 'mobile', 'invoice', 'print'] as $previewSurface) {
        $previewDocument = nexusThemePreviewDocument(nexusThemeDraftSettings($fixture), $previewSurface);
        expect(str_contains($previewDocument, '/css/nexus-theme.css?v=') && str_contains($previewDocument, '--nexus-header:#123456') && str_contains($previewDocument, 'Private draft heading') === ($previewSurface === 'auth'), $previewSurface . ' preview is generated from shared runtime CSS and draft presentation data');
    }
    $publishedDraft = nexusThemePublishDraft('Lifecycle tester', $fixture);
    expect($publishedDraft['content']['login_heading'] === 'Private draft heading' && nexusThemeSettings($fixture)['colors']['header'] === '#123456', 'publishing atomically promotes the validated draft');
    expect(!nexusThemeHasDraft($fixture) && !is_file(nexusThemeDraftPath($fixture)), 'publishing clears the private draft');
    $publishedRevisions = nexusThemeRevisions($fixture);
    expect(count($publishedRevisions) === 2 && end($publishedRevisions)['hash'] === nexusThemeSettingsHash(nexusThemeSettings($fixture)), 'first publication records both the original and newly published revisions');
    $revisionToRestore = $publishedRevisions[0];
    $pinnedRevision = nexusThemePinRevision($revisionToRestore['id'], true, $fixture);
    expect($pinnedRevision['pinned'] === true && nexusThemeRevisions($fixture)[0]['pinned'] === true, 'published revisions can be protected as known-good designs');
    nexusThemeRestoreRevisionToDraft($revisionToRestore['id'], $fixture);
    expect(nexusThemeHasDraft($fixture) && nexusThemeDraftSettings($fixture)['content']['login_heading'] === $revisionToRestore['settings']['content']['login_heading'], 'any published revision can be restored safely into the draft workspace');
    nexusThemeDiscardDraft($fixture);
    expect(!nexusThemeHasDraft($fixture) && nexusThemeSettings($fixture)['content']['login_heading'] === 'Private draft heading', 'discard removes unpublished restoration without changing live settings');

    $qualityTheme = nexusThemeSettings($fixture);
    $qualityTheme['colors']['text'] = '#ffffff';
    $qualityTheme['colors']['surface'] = '#ffffff';
    $qualityTheme['colors']['header_text'] = '#111111';
    $qualityTheme['colors']['header'] = '#111111';
    $qualityTheme['branding']['logo_light_path'] = '/uploads/nexus-theme/logo-light.png';
    $qualityTheme['branding']['logo_alt'] = '';
    $qualityTheme['branding']['logo_size'] = 180;
    $qualityTheme['appearance']['font_scale'] = 90;
    $qualityTheme['appearance']['density'] = 'compact';
    $qualityTheme['appearance']['sidebar_width'] = 340;
    $qualityTheme['appearance']['sidebar_compact'] = false;
    $qualityTheme['appearance']['motion_style'] = 'snappy';
    $qualityTheme['appearance']['reduce_motion'] = false;
    $qualityReport = nexusThemeQualityReport($qualityTheme, 390);
    $qualityIds = array_column($qualityReport['findings'], 'id');
    expect(in_array('body-contrast', $qualityIds, true) && in_array('header-contrast', $qualityIds, true) && in_array('logo-alt', $qualityIds, true) && in_array('sidebar-collision', $qualityIds, true) && in_array('logo-overflow', $qualityIds, true), 'design quality audit detects accessibility and responsive risks at the selected width');
    $fixedQualityTheme = nexusThemeApplyQualityFixes($qualityTheme, 390);
    $fixedQualityReport = nexusThemeQualityReport($fixedQualityTheme, 390);
    expect($fixedQualityReport['counts']['error'] === 0 && $fixedQualityTheme['branding']['logo_alt'] === 'Nexus Support' && $fixedQualityTheme['appearance']['reduce_motion'] === true && $fixedQualityTheme['appearance']['sidebar_width'] === 250, 'safe quality corrections repair contrast, branding, motion, and narrow-layout risks');
    $snapshot = nexusThemeSnapshotActive('Lifecycle tester', 'Automatic test snapshot', $fixture, true);
    $snapshotRevisions = nexusThemeRevisions($fixture);
    expect($snapshot['pinned'] === true && end($snapshotRevisions)['action'] === 'Automatic test snapshot', 'automatic snapshots can be protected from normal revision trimming');
    for ($revisionIndex = 0; $revisionIndex < 55; $revisionIndex++) {
        $snapshotRevisions[] = nexusThemeRevisionEntry(nexusThemeSettings($fixture), 'Lifecycle tester', 'Ordinary revision ' . $revisionIndex, gmdate('c', time() + $revisionIndex));
    }
    nexusThemeWriteRevisions($snapshotRevisions, $fixture);
    $trimmedRevisions = nexusThemeRevisions($fixture);
    expect(count($trimmedRevisions) === NEXUS_THEME_MAX_REVISIONS && in_array($snapshot['id'], array_column($trimmedRevisions, 'id'), true), 'revision trimming retains protected known-good snapshots while limiting ordinary history');
    $diagnostics = nexusThemeDiagnostics($fixture);
    expect($diagnostics['kind'] === 'nexus-theme-diagnostics' && $diagnostics['versions']['manager'] === NEXUS_MANAGER_VERSION && isset($diagnostics['quality']['desktop'], $diagnostics['quality']['mobile']) && !str_contains(json_encode($diagnostics, JSON_THROW_ON_ERROR), $fixture), 'diagnostics expose sanitized health data without filesystem paths');

    $rollbackTheme = $savedTheme;
    $rollbackTheme['content']['login_heading'] = 'Rollback target';
    nexusThemeSaveSettings($rollbackTheme, $fixture);
    expect(nexusThemeCanRollback($fixture), 'theme customization snapshots the previous design');
    $preRollbackAssetRevision = nexusThemeSettings($fixture)['branding']['asset_revision'];
    nexusThemeRollback($fixture);
    expect(nexusThemeSettings($fixture)['content']['login_heading'] === 'Private draft heading', 'legacy one-click rollback restores the prior published design');
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

<?php

require_once 'includes/inc_all_admin.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

$nexusDocumentRoot = nexusThemeDocumentRoot();
$nexusThemeEnabled = nexusThemeIsEnabled();
$nexusControlWritable = nexusThemeControlIsWritable();
$nexusSettings = nexusThemeSettings();
$nexusDefaults = nexusThemeDefaults();
$nexusPresets = nexusThemePresets();
$nexusLogoUrl = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($nexusSettings, '', 'light'), $nexusSettings);
$nexusDarkLogoUrl = nexusThemeVersionedAssetUrl($nexusSettings['branding']['logo_dark_path'], $nexusSettings);
$nexusNavigationLogoUrl = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($nexusSettings, '', nexusThemeLogoVariantForColor($nexusSettings['colors']['header'])), $nexusSettings);
$nexusLoginBackgroundUrl = nexusThemeVersionedAssetUrl($nexusSettings['branding']['login_background_path'], $nexusSettings);
$nexusSavedPresets = nexusThemeSavedPresets();
$nexusSavedPresetsJson = nexusThemeExportPresets();
$nexusSchedule = nexusThemeSchedule();
$nexusCanRollback = nexusThemeCanRollback();
$nexusBrandPlaceholder = 'Nexus MSP';
$nexusStudioBrandName = $nexusSettings['branding']['brand_name'] !== ''
    ? $nexusSettings['branding']['brand_name']
    : $nexusBrandPlaceholder;
$nexusManagedAssets = [
    'Theme stylesheet' => '/css/nexus-theme.css',
    'Customization stylesheet' => '/css/nexus-theme-custom.php',
    'Theme runtime' => '/includes/nexus_theme.php',
    'Administration editor' => '/admin/nexus.php',
    'Secure action handler' => '/admin/post/nexus.php',
    'Administration navigation' => '/admin/includes/side_nav.php',
];
$nexusPresentAssets = 0;
foreach ($nexusManagedAssets as $relativePath) {
    if (is_file($nexusDocumentRoot . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        $nexusPresentAssets++;
    }
}
$nexusAssetsHealthy = $nexusPresentAssets === count($nexusManagedAssets);
$nexusStatusLabel = $nexusThemeEnabled ? 'Active' : 'Paused';
$nexusStatusClass = $nexusThemeEnabled ? 'success' : 'secondary';
$nexusExportJson = json_encode($nexusSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$nexusUpdaterIsReady = nexusUpdaterReady();
$nexusUpdateStatus = nexusUpdaterStatus();
$nexusUpdateBusy = in_array($nexusUpdateStatus['state'], ['checking', 'running'], true);
$nexusUpdateAvailable = $nexusUpdateStatus['state'] === 'update_available'
    && $nexusUpdateStatus['latest_version'] !== null
    && version_compare($nexusUpdateStatus['latest_version'], NEXUS_MANAGER_VERSION, '>');
$nexusUpdatePresentation = [
    'not_configured' => ['secondary', 'plug', 'Setup required'],
    'ready' => ['info', 'shield-alt', 'Ready'],
    'checking' => ['info', 'sync-alt fa-spin', 'Checking'],
    'update_available' => ['warning', 'arrow-circle-up', 'Update available'],
    'up_to_date' => ['success', 'check-circle', 'Up to date'],
    'running' => ['primary', 'sync-alt fa-spin', 'Updating'],
    'completed' => ['success', 'check-circle', 'Updated'],
    'failed' => ['danger', 'exclamation-circle', 'Attention needed'],
][$nexusUpdateStatus['state']];
$nexusUpdaterSetupCommand = 'sudo php /opt/Nexus-Theme-Manager-for-ITFlow-' . NEXUS_MANAGER_VERSION
    . '/updater.php install-service --root ' . $nexusDocumentRoot;

?>

<div class="nexus-manager-page nexus-studio" id="nexus-theme-studio">
    <section class="card border-0 nexus-manager-hero overflow-hidden">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center mb-3">
                        <span class="nexus-manager-mark mr-3"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                        <div>
                            <span class="nexus-manager-kicker">Nexus design system</span>
                            <h1 class="h2 mb-0">Theme Studio</h1>
                        </div>
                    </div>
                    <p class="lead mb-4">Shape every Nexus surface from one workspace—brand, palette, content, spacing, and behavior—with an instant preview.</p>
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="badge badge-<?= $nexusStatusClass ?> nexus-manager-status mr-2 mb-2"><i class="fas fa-circle mr-1" aria-hidden="true"></i><?= $nexusStatusLabel ?></span>
                        <span class="nexus-manager-meta mb-2">Manager <?= NEXUS_MANAGER_VERSION ?> &middot; Theme <?= NEXUS_THEME_VERSION ?></span>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0 text-lg-right">
                    <form action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <?php if ($nexusThemeEnabled) { ?>
                            <button type="submit" name="nexus_theme_state" value="disable" class="btn btn-light btn-lg" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-pause mr-2" aria-hidden="true"></i>Pause theme</button>
                        <?php } else { ?>
                            <button type="submit" name="nexus_theme_state" value="enable" class="btn btn-primary btn-lg" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-play mr-2" aria-hidden="true"></i>Activate theme</button>
                        <?php } ?>
                    </form>
                    <small class="d-block mt-2 nexus-manager-meta">Changes apply across login, technician, admin, and client surfaces.</small>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$nexusControlWritable) { ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i>Theme Studio is read-only because the web service cannot write to the ITFlow <code>uploads</code> directory.</div>
    <?php } ?>

    <div class="row nexus-manager-metrics">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-<?= $nexusStatusClass ?>"><i class="fas fa-toggle-on"></i></span><span class="text-muted text-uppercase small font-weight-bold">Theme</span><div class="h4 mb-0 mt-2"><?= $nexusStatusLabel ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-info"><i class="fas fa-swatchbook"></i></span><span class="text-muted text-uppercase small font-weight-bold">Palette</span><div class="h4 mb-0 mt-2"><?= ucfirst(escapeHtml($nexusSettings['preset'])) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-indigo"><i class="fas fa-image"></i></span><span class="text-muted text-uppercase small font-weight-bold">Branding</span><div class="h4 mb-0 mt-2"><?= $nexusLogoUrl !== '' ? 'Custom logo' : 'ITFlow default' ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-<?= $nexusAssetsHealthy ? 'success' : 'danger' ?>"><i class="fas fa-heartbeat"></i></span><span class="text-muted text-uppercase small font-weight-bold">Core assets</span><div class="h4 mb-0 mt-2"><?= $nexusPresentAssets ?>/<?= count($nexusManagedAssets) ?> healthy</div></div></div></div>
    </div>

    <form action="post.php" method="post" enctype="multipart/form-data" id="nexus-customizer-form">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="nexus_theme_save" value="1">
        <input type="hidden" name="nexus[preset]" id="nexus-preset" value="<?= escapeHtml($nexusSettings['preset']) ?>">

        <div class="nexus-studio-workspace mt-4" data-active-section="brand">
            <aside class="card nexus-workspace-navigation">
                <div class="card-body p-3">
                    <div class="nexus-workspace-navigation-title"><span class="nexus-manager-kicker">Theme settings</span><strong>Studio navigation</strong></div>
                    <nav class="nav nexus-studio-tabs" aria-label="Theme Studio sections">
                        <span class="nexus-workspace-nav-label">Design</span>
                        <a class="nav-link active" href="#nexus-brand" data-workspace-section="brand" data-workspace-title="Branding" data-workspace-description="Logos, identity, browser details, and login artwork."><i class="fas fa-fingerprint"></i><span><strong>Branding</strong><small>Logos &amp; identity</small></span></a>
                        <a class="nav-link" href="#nexus-colors" data-workspace-section="colors" data-workspace-title="Colors" data-workspace-description="Palettes, surfaces, accents, and accessibility contrast."><i class="fas fa-palette"></i><span><strong>Colors</strong><small>Palette &amp; contrast</small></span></a>
                        <a class="nav-link" href="#nexus-layout" data-workspace-section="layout" data-workspace-title="Layout" data-workspace-description="Sidebar, header, navigation, density, and interface scale."><i class="fas fa-sliders-h"></i><span><strong>Layout</strong><small>Navigation &amp; spacing</small></span></a>
                        <a class="nav-link" href="#nexus-motion" data-workspace-section="motion" data-workspace-title="Motion" data-workspace-description="Animation profiles and reduced-motion behavior."><i class="fas fa-magic"></i><span><strong>Motion</strong><small>Popups &amp; modals</small></span></a>
                        <a class="nav-link" href="#nexus-content" data-workspace-section="content" data-workspace-title="Content" data-workspace-description="Login and client portal headings, messages, and supporting copy."><i class="fas fa-pen-nib"></i><span><strong>Content</strong><small>Headings &amp; messages</small></span></a>
                        <span class="nexus-workspace-nav-label">Manage</span>
                        <a class="nav-link" href="#nexus-operations" data-workspace-section="operations" data-workspace-title="Presets &amp; scheduling" data-workspace-description="Save designs, schedule activation, and recover the previous look."><i class="fas fa-layer-group"></i><span><strong>Presets &amp; schedule</strong><small>Reuse &amp; automate</small></span></a>
                        <a class="nav-link" href="#nexus-system" data-workspace-section="system" data-workspace-title="Updates &amp; system" data-workspace-description="Protected updates, configuration transfer, and factory reset."><i class="fas fa-shield-alt"></i><span><strong>Updates &amp; system</strong><small>Lifecycle &amp; tools</small></span></a>
                    </nav>
                    <div class="nexus-workspace-navigation-note"><i class="fas fa-check-circle"></i><span>Settings remain unsaved until you choose <strong>Save and apply</strong>.</span></div>
                </div>
            </aside>

            <div class="nexus-workspace-content">
                <header class="card nexus-workspace-heading">
                    <div class="card-body"><div><span class="nexus-manager-kicker">Current section</span><h2 class="h4 mb-1" id="nexus-workspace-title">Branding</h2><p class="text-muted mb-0" id="nexus-workspace-description">Logos, identity, browser details, and login artwork.</p></div><span class="nexus-workspace-step" id="nexus-workspace-step">1 of 7</span></div>
                </header>

                <div class="row nexus-workspace-row">
            <div class="col-xl-7 nexus-workspace-editor-column">
                <section class="card nexus-studio-editor">
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="nexus-brand" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-signature"></i></span><div><h2 class="h5 mb-1">Brand identity</h2><p class="text-muted mb-0">Override ITFlow branding wherever Nexus is active.</p></div></div>
                                <div class="form-row mt-4">
                                    <div class="form-group col-md-7"><label for="nexus-brand-name">Display name</label><input class="form-control nexus-preview-input" id="nexus-brand-name" name="nexus[branding][brand_name]" maxlength="80" value="<?= escapeHtml($nexusSettings['branding']['brand_name']) ?>" placeholder="<?= escapeHtml($nexusBrandPlaceholder) ?>" data-preview="brand"><small class="form-text">Leave blank to preserve ITFlow's native company name on live pages; Nexus MSP is preview text only.</small></div>
                                    <div class="form-group col-md-5"><label for="nexus-logo-alt">Logo alt text</label><input class="form-control" id="nexus-logo-alt" name="nexus[branding][logo_alt]" maxlength="120" value="<?= escapeHtml($nexusSettings['branding']['logo_alt']) ?>" placeholder="<?= escapeHtml($nexusBrandPlaceholder) ?> logo"></div>
                                </div>
                                <div class="form-row"><div class="form-group col-md-7"><label for="nexus-tagline">Brand tagline</label><input class="form-control nexus-preview-input" id="nexus-tagline" name="nexus[branding][tagline]" maxlength="140" value="<?= escapeHtml($nexusSettings['branding']['tagline']) ?>" data-preview="tagline"></div><div class="form-group col-md-5"><label for="nexus-browser-title">Browser title</label><input class="form-control" id="nexus-browser-title" name="nexus[branding][browser_title]" maxlength="80" value="<?= escapeHtml($nexusSettings['branding']['browser_title']) ?>" placeholder="<?= escapeHtml($nexusBrandPlaceholder) ?>"></div></div>
                                <div class="nexus-logo-dropzone">
                                    <div class="nexus-logo-current nexus-logo-current-dark" id="nexus-logo-preview">
                                        <?php if ($nexusLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusLogoUrl) ?>" alt="Current custom logo"><?php } else { ?><span><i class="fas fa-layer-group"></i></span><?php } ?>
                                    </div>
                                    <div class="flex-grow-1"><label for="nexus-logo" class="mb-1">Light logo <small class="text-muted">for dark surfaces</small></label><input type="file" class="form-control-file nexus-image-input" id="nexus-logo" name="nexus_logo_light" accept="image/png,image/jpeg,image/webp,image/gif" data-preview-target-id="nexus-logo-preview" data-preview-logo="light" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">PNG, JPEG, WebP, or animated GIF · up to 8 MB · including native 24fps timing.</small></div>
                                    <?php if ($nexusSettings['branding']['logo_light_path'] !== '') { ?><button type="submit" form="nexus-remove-light-logo-form" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt mr-1"></i>Remove</button><?php } ?>
                                </div>
                                <div class="nexus-logo-dropzone mt-3"><div class="nexus-logo-current" id="nexus-dark-logo-preview"><?php if ($nexusDarkLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusDarkLogoUrl) ?>" alt="Current dark logo"><?php } else { ?><span><i class="fas fa-sun"></i></span><?php } ?></div><div class="flex-grow-1"><label for="nexus-dark-logo" class="mb-1">Dark logo <small class="text-muted">for light surfaces</small></label><input type="file" class="form-control-file nexus-image-input" id="nexus-dark-logo" name="nexus_logo_dark" accept="image/png,image/jpeg,image/webp,image/gif" data-preview-target-id="nexus-dark-logo-preview" data-preview-logo="dark" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">Static images and animated GIFs up to 8 MB; embedded timing is preserved.</small></div><?php if ($nexusDarkLogoUrl !== '') { ?><button type="submit" form="nexus-remove-dark-logo-form" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt mr-1"></i>Remove</button><?php } ?></div>
                                <div class="form-row mt-3"><div class="form-group col-md-7"><div class="d-flex justify-content-between"><label for="nexus-logo-size">Logo size</label><output id="nexus-logo-size-output"><?= (int)$nexusSettings['branding']['logo_size'] ?>%</output></div><input type="range" class="custom-range" id="nexus-logo-size" name="nexus[branding][logo_size]" min="50" max="180" value="<?= (int)$nexusSettings['branding']['logo_size'] ?>"></div><div class="form-group col-md-5"><label for="nexus-logo-alignment">Logo alignment</label><select class="custom-select" id="nexus-logo-alignment" name="nexus[branding][logo_alignment]"><?php foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['branding']['logo_alignment'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div></div>
                                <div class="form-row"><div class="form-group col-md-6"><label for="nexus-favicon">Custom favicon</label><input type="file" class="form-control-file" id="nexus-favicon" name="nexus_favicon" accept="image/png,image/jpeg,image/webp" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">Square images work best. <?php if ($nexusSettings['branding']['favicon_path'] !== '') { ?><button type="submit" form="nexus-remove-favicon-form" class="btn btn-link btn-sm p-0 text-danger">Remove current</button><?php } ?></small></div><div class="form-group col-md-6"><label for="nexus-login-background">Login background</label><input type="file" class="form-control-file" id="nexus-login-background" name="nexus_login_background" accept="image/png,image/jpeg,image/webp" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">PNG, JPEG, or WebP · up to 8 MB. <?php if ($nexusSettings['branding']['login_background_path'] !== '') { ?><button type="submit" form="nexus-remove-background-form" class="btn btn-link btn-sm p-0 text-danger">Remove current</button><?php } ?></small></div></div>
                                <div class="form-row"><div class="form-group col-md-6"><label for="nexus-background-position">Background focal point</label><select class="custom-select" id="nexus-background-position" name="nexus[branding][login_background_position]"><?php foreach (['top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['branding']['login_background_position'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div><div class="form-group col-md-6"><div class="d-flex justify-content-between"><label for="nexus-background-overlay">Background overlay</label><output id="nexus-background-overlay-output"><?= (int)$nexusSettings['branding']['login_background_overlay'] ?>%</output></div><input type="range" class="custom-range" id="nexus-background-overlay" name="nexus[branding][login_background_overlay]" min="0" max="90" value="<?= (int)$nexusSettings['branding']['login_background_overlay'] ?>"></div></div>
                                <div class="row mt-3">
                                    <div class="col-md-4"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-show-login-logo" name="nexus[branding][show_login_logo]" value="1" <?= $nexusSettings['branding']['show_login_logo'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-show-login-logo">Authentication pages</label></div></div>
                                    <div class="col-md-4"><div class="custom-control custom-switch"><input type="hidden" name="nexus[branding][show_agent_logo]" value="0"><input type="checkbox" class="custom-control-input" id="nexus-show-agent-logo" name="nexus[branding][show_agent_logo]" value="1" <?= $nexusSettings['branding']['show_agent_logo'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-show-agent-logo">Technician navigation</label></div></div>
                                    <div class="col-md-4"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-show-portal-logo" name="nexus[branding][show_portal_logo]" value="1" <?= $nexusSettings['branding']['show_portal_logo'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-show-portal-logo">Client portal</label></div></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="nexus-colors" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-swatchbook"></i></span><div><h2 class="h5 mb-1">Color system</h2><p class="text-muted mb-0">Start with a curated preset, then tune every surface.</p></div></div>
                                <div class="nexus-preset-grid mt-4">
                                    <?php foreach ($nexusPresets as $presetName => $presetColors) { ?>
                                        <button type="button" class="nexus-preset <?= $nexusSettings['preset'] === $presetName ? 'active' : '' ?>" data-preset="<?= escapeHtml($presetName) ?>" data-colors='<?= escapeHtml(json_encode($presetColors)) ?>'>
                                            <span class="nexus-preset-swatches"><i style="background:<?= $presetColors['sidebar'] ?>"></i><i style="background:<?= $presetColors['secondary'] ?>"></i><i style="background:<?= $presetColors['primary'] ?>"></i></span><strong><?= ucfirst(escapeHtml($presetName)) ?></strong>
                                        </button>
                                    <?php } ?>
                                </div>
                                <div class="row mt-4">
                                    <?php $nexusColorLabels = ['primary' => 'Primary accent', 'secondary' => 'Gradient accent', 'sidebar' => 'Sidebar', 'header' => 'Header', 'header_text' => 'Header text', 'auth_background' => 'Login background', 'page' => 'Page background', 'surface' => 'Cards & surfaces', 'text' => 'Primary text']; ?>
                                    <?php foreach ($nexusColorLabels as $colorKey => $colorLabel) { ?>
                                        <div class="col-md-6"><div class="form-group nexus-color-field"><label for="nexus-color-<?= $colorKey ?>"><?= $colorLabel ?></label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text"><input type="color" class="nexus-color-picker" value="<?= $nexusSettings['colors'][$colorKey] ?>" tabindex="-1"></span></div><input type="text" class="form-control nexus-color-value" id="nexus-color-<?= $colorKey ?>" name="nexus[colors][<?= $colorKey ?>]" value="<?= $nexusSettings['colors'][$colorKey] ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color="<?= $colorKey ?>"></div></div></div>
                                    <?php } ?>
                                </div>
                                <div class="nexus-contrast-report" id="nexus-contrast-report" role="status" aria-live="polite">
                                    <span class="nexus-contrast-icon"><i class="fas fa-universal-access"></i></span>
                                    <div><strong>Accessibility contrast</strong><p class="mb-0 text-muted" id="nexus-contrast-copy">Checking the selected palette…</p></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="nexus-layout" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-ruler-combined"></i></span><div><h2 class="h5 mb-1">Interface feel</h2><p class="text-muted mb-0">Control visual rhythm without sacrificing responsiveness.</p></div></div>
                                <div class="form-group mt-4"><label>Corner style</label><div class="nexus-choice-grid"><?php foreach (['sharp' => 'Sharp', 'balanced' => 'Balanced', 'rounded' => 'Rounded'] as $value => $label) { ?><label class="nexus-choice"><input type="radio" name="nexus[appearance][radius]" value="<?= $value ?>" <?= $nexusSettings['appearance']['radius'] === $value ? 'checked' : '' ?>><span class="nexus-choice-demo nexus-radius-<?= $value ?>"></span><strong><?= $label ?></strong></label><?php } ?></div></div>
                                <div class="form-group"><label for="nexus-density">Content density</label><select class="custom-select nexus-navigation-control" id="nexus-density" name="nexus[appearance][density]"><?php foreach (['compact' => 'Compact — more information on screen', 'comfortable' => 'Comfortable — balanced spacing', 'spacious' => 'Spacious — relaxed and touch friendly'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['density'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div>
                                <div class="form-row"><div class="form-group col-md-6"><label for="nexus-menu-density">Menu density</label><select class="custom-select nexus-navigation-control" id="nexus-menu-density" name="nexus[appearance][menu_density]"><?php foreach (['compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['menu_density'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div><div class="form-group col-md-6"><div class="d-flex justify-content-between"><label for="nexus-sidebar-width">Sidebar width</label><output id="nexus-sidebar-width-output"><?= (int)$nexusSettings['appearance']['sidebar_width'] ?>px</output></div><input type="range" class="custom-range nexus-navigation-control" id="nexus-sidebar-width" name="nexus[appearance][sidebar_width]" min="220" max="340" step="5" value="<?= (int)$nexusSettings['appearance']['sidebar_width'] ?>"></div></div>
                                <div class="form-row"><div class="form-group col-md-6"><label for="nexus-header-style">Header treatment</label><select class="custom-select nexus-navigation-control" id="nexus-header-style" name="nexus[appearance][header_style]"><?php foreach (['solid' => 'Solid', 'gradient' => 'Gradient', 'glass' => 'Glass'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['header_style'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div><div class="form-group col-md-6"><label for="nexus-navigation-style">Active navigation</label><select class="custom-select nexus-navigation-control" id="nexus-navigation-style" name="nexus[appearance][navigation_style]"><?php foreach (['pill' => 'Gradient pill', 'rail' => 'Accent rail', 'outline' => 'Outline'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['navigation_style'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div></div>
                                <div class="custom-control custom-switch mb-3"><input type="checkbox" class="custom-control-input nexus-navigation-control" id="nexus-sidebar-compact" name="nexus[appearance][sidebar_compact]" value="1" <?= $nexusSettings['appearance']['sidebar_compact'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-sidebar-compact">Compact sidebar labels and section spacing</label></div>
                                <div class="form-group"><div class="d-flex justify-content-between"><label for="nexus-font-scale">Interface scale</label><output id="nexus-font-scale-output"><?= (int)$nexusSettings['appearance']['font_scale'] ?>%</output></div><input type="range" class="custom-range" id="nexus-font-scale" name="nexus[appearance][font_scale]" min="90" max="110" step="1" value="<?= (int)$nexusSettings['appearance']['font_scale'] ?>"></div>
                            </div>

                            <div class="tab-pane fade" id="nexus-motion" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-magic"></i></span><div><h2 class="h5 mb-1">Motion &amp; interaction</h2><p class="text-muted mb-0">Give overlays polish without getting in the user's way.</p></div></div>
                                <div class="nexus-motion-settings mt-4">
                                    <div class="form-group"><label for="nexus-motion-style">Popup and modal motion</label><div class="input-group"><select class="custom-select" id="nexus-motion-style" name="nexus[appearance][motion_style]"><?php foreach (['subtle' => 'Subtle - short, low movement', 'fluid' => 'Fluid - smooth and polished', 'snappy' => 'Snappy - quick with extra energy'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['motion_style'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select><div class="input-group-append"><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#nexus-motion-preview-modal"><i class="fas fa-play mr-1"></i>Preview</button></div></div><small class="form-text">Controls modals, dropdowns, tooltips, alerts, toasts, and floating panels.</small></div>
                                    <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-reduce-motion" name="nexus[appearance][reduce_motion]" value="1" <?= $nexusSettings['appearance']['reduce_motion'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-reduce-motion">Reduce animations and hover motion</label></div>
                                    <div class="nexus-motion-callout"><i class="fas fa-universal-access"></i><div><strong>Accessibility stays in control</strong><p class="mb-0">The visitor's operating-system preference still takes priority over the selected animation profile.</p></div></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="nexus-content" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-comment-alt"></i></span><div><h2 class="h5 mb-1">Portal copy</h2><p class="text-muted mb-0">Make entry points sound like your organization.</p></div></div>
                                <div class="form-row mt-4"><div class="form-group col-md-5"><label for="nexus-login-eyebrow">Login eyebrow</label><input class="form-control nexus-preview-input" id="nexus-login-eyebrow" name="nexus[content][login_eyebrow]" maxlength="60" value="<?= escapeHtml($nexusSettings['content']['login_eyebrow']) ?>" data-preview="eyebrow"></div><div class="form-group col-md-7"><label for="nexus-login-heading">Login heading</label><input class="form-control nexus-preview-input" id="nexus-login-heading" name="nexus[content][login_heading]" maxlength="80" value="<?= escapeHtml($nexusSettings['content']['login_heading']) ?>" data-preview="heading"></div></div>
                                <div class="form-group"><label for="nexus-login-message">Login message</label><textarea class="form-control nexus-preview-input" id="nexus-login-message" name="nexus[content][login_message]" rows="3" maxlength="240" data-preview="message"><?= escapeHtml($nexusSettings['content']['login_message']) ?></textarea></div>
                                <hr><div class="form-group"><label for="nexus-portal-heading">Portal heading</label><input class="form-control" id="nexus-portal-heading" name="nexus[content][portal_heading]" maxlength="80" value="<?= escapeHtml($nexusSettings['content']['portal_heading']) ?>"></div>
                                <div class="form-group mb-0"><label for="nexus-portal-message">Portal message</label><textarea class="form-control" id="nexus-portal-message" name="nexus[content][portal_message]" rows="3" maxlength="180"><?= escapeHtml($nexusSettings['content']['portal_message']) ?></textarea></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center">
                        <span class="text-muted small"><i class="fas fa-shield-alt mr-1 text-success"></i>Validated and written atomically</span>
                        <button class="btn btn-primary" type="submit" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-check mr-2"></i>Save and apply</button>
                    </div>
                </section>
            </div>

            <div class="col-xl-5 mt-4 mt-xl-0 nexus-workspace-side-column">
                <div class="nexus-preview-sticky">
                    <section class="card nexus-preview-card nexus-workspace-preview">
                        <div class="card-header border-0 d-flex align-items-center justify-content-between"><div><span class="nexus-manager-kicker">Live preview</span><h2 class="h5 mb-0" id="nexus-preview-title">Authentication</h2></div><div class="btn-group btn-group-sm" role="group" aria-label="Preview surface"><button type="button" class="btn btn-info nexus-preview-mode active" data-mode="auth">Login</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="navigation">Navigation</button></div></div>
                        <div class="card-body p-0">
                            <div class="nexus-live-preview" id="nexus-live-preview" style="--preview-primary:<?= $nexusSettings['colors']['primary'] ?>;--preview-secondary:<?= $nexusSettings['colors']['secondary'] ?>;--preview-sidebar:<?= $nexusSettings['colors']['sidebar'] ?>;--preview-header:<?= $nexusSettings['colors']['header'] ?>;--preview-header-text:<?= $nexusSettings['colors']['header_text'] ?>;--preview-auth:<?= $nexusSettings['colors']['auth_background'] ?>;--preview-page:<?= $nexusSettings['colors']['page'] ?>;--preview-surface:<?= $nexusSettings['colors']['surface'] ?>;--preview-text:<?= $nexusSettings['colors']['text'] ?>;--preview-overlay:<?= $nexusSettings['branding']['login_background_overlay'] / 100 ?>;--preview-bg-position:<?= $nexusSettings['branding']['login_background_position'] ?> center;<?php if ($nexusLoginBackgroundUrl !== '') { ?>--preview-bg-image:url('<?= escapeHtml($nexusLoginBackgroundUrl) ?>');<?php } ?>">
                                <div class="nexus-preview-windowbar"><i></i><i></i><i></i></div>
                                <div class="nexus-preview-canvas nexus-preview-panel" data-preview-panel="auth">
                                    <div class="nexus-preview-brand">
                                        <?php if ($nexusLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusLogoUrl) ?>" alt="Preview logo"><?php } else { ?><span class="nexus-preview-symbol"><i class="fas fa-layer-group"></i></span><?php } ?>
                                        <strong data-preview-target="brand"><?= escapeHtml($nexusStudioBrandName) ?></strong>
                                    </div>
                                    <div class="nexus-preview-login-card"><span class="nexus-preview-eyebrow" data-preview-target="eyebrow"><?= escapeHtml($nexusSettings['content']['login_eyebrow']) ?></span><h3 data-preview-target="heading"><?= escapeHtml($nexusSettings['content']['login_heading']) ?></h3><p data-preview-target="message"><?= escapeHtml($nexusSettings['content']['login_message']) ?></p><span class="nexus-preview-label">Email address</span><div class="nexus-preview-field"></div><span class="nexus-preview-label">Password</span><div class="nexus-preview-field"></div><button type="button" tabindex="-1">Sign in</button></div>
                                    <small data-preview-target="tagline"><?= escapeHtml($nexusSettings['branding']['tagline']) ?></small>
                                </div>
                                <div class="nexus-preview-shell nexus-preview-panel d-none" data-preview-panel="navigation" data-width="<?= (int)$nexusSettings['appearance']['sidebar_width'] ?>" data-menu-density="<?= escapeHtml($nexusSettings['appearance']['menu_density']) ?>" data-content-density="<?= escapeHtml($nexusSettings['appearance']['density']) ?>" data-header-style="<?= escapeHtml($nexusSettings['appearance']['header_style']) ?>" data-navigation-style="<?= escapeHtml($nexusSettings['appearance']['navigation_style']) ?>">
                                    <aside class="nexus-preview-sidebar"><div class="nexus-preview-shell-brand"><?php if ($nexusNavigationLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusNavigationLogoUrl) ?>" alt="Navigation logo preview"><?php } else { ?><i class="fas fa-layer-group"></i><strong data-preview-target="brand"><?= escapeHtml($nexusStudioBrandName) ?></strong><?php } ?></div><small>Workspace</small><a class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a><a><i class="fas fa-users"></i><span>Clients</span></a><a><i class="fas fa-ticket-alt"></i><span>Tickets</span></a><small>Finance</small><a><i class="fas fa-file-invoice-dollar"></i><span>Invoices</span></a><a><i class="fas fa-chart-line"></i><span>Reports</span></a></aside>
                                    <div class="nexus-preview-main"><header><i class="fas fa-bars"></i><span>Workspace</span><strong data-preview-header-label>Solid</strong><i class="fas fa-bell"></i><i class="fas fa-user-circle"></i></header><main><div class="nexus-preview-page-heading"></div><div class="nexus-preview-stat-grid"><i></i><i></i><i></i></div><div class="nexus-preview-content-block"></div></main></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card nexus-design-operations nexus-workspace-panel" data-workspace-panel="operations" id="nexus-operations">
                        <div class="card-header border-0"><div><span class="nexus-manager-kicker">Design operations</span><h2 class="h5 mb-0">Presets, schedule &amp; recovery</h2></div></div>
                        <div class="card-body pt-0">
                            <label for="nexus-preset-name">Save this design as a preset</label><div class="input-group mb-3"><input class="form-control" id="nexus-preset-name" name="nexus_preset_name" form="nexus-save-preset-form" maxlength="50" placeholder="e.g. Holiday campaign"><div class="input-group-append"><button class="btn btn-outline-info" type="submit" form="nexus-save-preset-form" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-bookmark mr-1"></i>Save</button></div></div>
                            <?php if ($nexusSavedPresets !== []) { ?><div class="nexus-saved-preset-list mb-3"><?php foreach ($nexusSavedPresets as $savedPreset) { ?><div><span><i class="fas fa-swatchbook mr-2 text-info"></i><?= escapeHtml($savedPreset['name']) ?></span><span><button type="submit" form="nexus-apply-preset-<?= $savedPreset['id'] ?>" class="btn btn-sm btn-link">Apply</button><button type="submit" form="nexus-delete-preset-<?= $savedPreset['id'] ?>" class="btn btn-sm btn-link text-danger" aria-label="Delete <?= escapeHtml($savedPreset['name']) ?>"><i class="fas fa-trash"></i></button></span></div><?php } ?></div><?php } else { ?><p class="small text-muted">No saved presets yet.</p><?php } ?>
                            <div class="btn-group w-100 mb-3"><button type="button" class="btn btn-outline-info" id="nexus-export-presets" <?= $nexusSavedPresets === [] ? 'disabled' : '' ?>><i class="fas fa-download mr-1"></i>Export presets</button><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#nexus-import-presets-modal"><i class="fas fa-upload mr-1"></i>Import presets</button></div><textarea class="d-none" id="nexus-export-presets-json"><?= escapeHtml($nexusSavedPresetsJson) ?></textarea>
                            <hr><label for="nexus-schedule-local">Scheduled activation</label><div class="form-row"><div class="col-7"><input type="datetime-local" class="form-control" id="nexus-schedule-local" form="nexus-schedule-form" required></div><div class="col-5"><select class="custom-select" name="nexus_schedule_action" form="nexus-schedule-form"><option value="enable">Activate</option><option value="disable">Pause</option></select></div></div><input type="hidden" name="nexus_schedule_at" id="nexus-schedule-at" form="nexus-schedule-form"><div class="d-flex align-items-center justify-content-between mt-2"><small class="text-muted"><?php if ($nexusSchedule !== null) { ?>Next: <?= ucfirst($nexusSchedule['action']) ?> at <?= escapeHtml($nexusSchedule['activate_at']) ?><?php } else { ?>Applies on the first request at or after this time.<?php } ?></small><span><button type="submit" form="nexus-schedule-form" class="btn btn-sm btn-outline-info">Schedule</button><?php if ($nexusSchedule !== null) { ?><button type="submit" form="nexus-cancel-schedule-form" class="btn btn-sm btn-link text-danger">Cancel</button><?php } ?></span></div>
                            <hr><button type="submit" form="nexus-rollback-form" class="btn btn-outline-warning btn-block" <?= $nexusCanRollback ? '' : 'disabled' ?> onclick="return confirm('Restore the previous Nexus design? You can click rollback again to switch back.')"><i class="fas fa-history mr-2"></i>Rollback to previous design</button>
                        </div>
                    </section>

                    <section class="card nexus-update-card nexus-workspace-panel" data-workspace-panel="system" id="nexus-system">
                        <div class="card-header border-0 d-flex align-items-center justify-content-between">
                            <div><span class="nexus-manager-kicker">Protected lifecycle</span><h2 class="h5 mb-0">Nexus updates</h2></div>
                            <span class="badge badge-<?= $nexusUpdatePresentation[0] ?> px-3 py-2"><i class="fas fa-<?= $nexusUpdatePresentation[1] ?> mr-1"></i><?= $nexusUpdatePresentation[2] ?></span>
                        </div>
                        <div class="card-body pt-0">
                            <p class="mb-3"><?= escapeHtml($nexusUpdateStatus['message']) ?></p>
                            <div class="nexus-update-versions">
                                <div><span>Installed</span><strong><?= escapeHtml($nexusUpdateStatus['current_version']) ?></strong></div>
                                <i class="fas fa-long-arrow-alt-right text-muted"></i>
                                <div><span>Latest</span><strong><?= escapeHtml($nexusUpdateStatus['latest_version'] ?? 'Check now') ?></strong></div>
                            </div>
                            <?php if (!$nexusUpdaterIsReady) { ?>
                                <div class="nexus-manager-command mt-3"><span>One-time updater setup</span><code><?= escapeHtml($nexusUpdaterSetupCommand) ?></code></div>
                                <small class="form-text">Run once as root. Theme Studio never receives general sudo or shell access.</small>
                            <?php } else { ?>
                                <?php if ($nexusUpdateStatus['phase'] !== null && $nexusUpdateBusy) { ?>
                                    <div class="progress mt-3" style="height:0.45rem"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:<?= $nexusUpdateStatus['phase'] === 'download' ? '25' : ($nexusUpdateStatus['phase'] === 'backup' ? '55' : '80') ?>%"></div></div>
                                <?php } ?>
                                <div class="d-flex flex-wrap mt-3">
                                    <button type="submit" form="nexus-update-check-form" class="btn btn-outline-info mr-2 mb-2" <?= $nexusUpdateBusy ? 'disabled' : '' ?>><i class="fas fa-sync-alt mr-2"></i>Check for updates</button>
                                    <?php if ($nexusUpdateAvailable) { ?>
                                        <button type="submit" form="nexus-update-install-form" class="btn btn-primary mb-2" onclick="return confirm('Install Nexus <?= escapeHtml($nexusUpdateStatus['latest_version']) ?> now? The updater will verify the release and roll back automatically if installation fails.')"><i class="fas fa-cloud-download-alt mr-2"></i>Install <?= escapeHtml($nexusUpdateStatus['latest_version']) ?></button>
                                    <?php } ?>
                                </div>
                                <?php if ($nexusUpdateStatus['updated_at'] !== null && $nexusUpdateStatus['updated_at'] !== '') { ?><small class="text-muted">Last updater activity: <?= escapeHtml($nexusUpdateStatus['updated_at']) ?> UTC</small><?php } ?>
                            <?php } ?>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0"><span class="small text-muted"><i class="fas fa-lock mr-1 text-success"></i>Fixed repository · SHA-256 verification · rollback protection</span></div>
                    </section>

                    <section class="card nexus-workspace-panel" data-workspace-panel="system">
                        <div class="card-header border-0"><h2 class="card-title font-weight-bold"><i class="fas fa-toolbox mr-2 text-info"></i>Configuration tools</h2></div>
                        <div class="card-body pt-0"><p class="text-muted">Move designs between Nexus installations or return to the factory palette.</p><div class="btn-group w-100"><button type="button" class="btn btn-outline-info" id="nexus-export"><i class="fas fa-download mr-1"></i>Export</button><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#nexus-import-modal"><i class="fas fa-upload mr-1"></i>Import</button><button type="submit" form="nexus-reset-form" class="btn btn-outline-danger" <?= $nexusControlWritable ? '' : 'disabled' ?> onclick="return confirm('Restore every Nexus customization to its default value?')"><i class="fas fa-undo mr-1"></i>Reset</button></div><textarea class="d-none" id="nexus-export-json"><?= escapeHtml((string)$nexusExportJson) ?></textarea></div>
                    </section>
                </div>
            </div>
                </div>
            </div>
        </div>
    </form>

    <form action="post.php" method="post" id="nexus-remove-light-logo-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="logo-light"></form>
    <form action="post.php" method="post" id="nexus-remove-dark-logo-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="logo-dark"></form>
    <form action="post.php" method="post" id="nexus-remove-favicon-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="favicon"></form>
    <form action="post.php" method="post" id="nexus-remove-background-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="login-background"></form>
    <form action="post.php" method="post" id="nexus-reset-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_reset" value="1"></form>
    <form action="post.php" method="post" id="nexus-save-preset-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="save"></form>
    <?php foreach ($nexusSavedPresets as $savedPreset) { ?><form action="post.php" method="post" id="nexus-apply-preset-<?= $savedPreset['id'] ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="apply"><input type="hidden" name="nexus_preset_id" value="<?= $savedPreset['id'] ?>"></form><form action="post.php" method="post" id="nexus-delete-preset-<?= $savedPreset['id'] ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="delete"><input type="hidden" name="nexus_preset_id" value="<?= $savedPreset['id'] ?>"></form><?php } ?>
    <form action="post.php" method="post" id="nexus-schedule-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_schedule_command" value="set"></form>
    <form action="post.php" method="post" id="nexus-cancel-schedule-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_schedule_command" value="cancel"></form>
    <form action="post.php" method="post" id="nexus-rollback-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_rollback" value="1"></form>
    <form action="post.php" method="post" id="nexus-update-check-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_update_action" value="check"></form>
    <form action="post.php" method="post" id="nexus-update-install-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_update_action" value="update"></form>
</div>

<div class="modal fade" id="nexus-import-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_import" value="1"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-title">Import Nexus configuration</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a configuration exported from Theme Studio. Your current uploaded logo is kept.</p><textarea class="form-control nexus-json-editor" name="nexus_import_json" rows="16" required spellcheck="false" placeholder='{"schema": 1, ...}'></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload mr-2"></i>Import and apply</button></div></form></div></div>

<div class="modal fade" id="nexus-import-presets-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-presets-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="import"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-presets-title">Import saved presets</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a Nexus saved-presets export. Imported names are retained and new secure identifiers are generated.</p><textarea class="form-control nexus-json-editor" name="nexus_presets_json" rows="14" required spellcheck="false"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload mr-2"></i>Import presets</button></div></form></div></div>

<div class="modal fade" id="nexus-motion-preview-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-motion-preview-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><div><span class="nexus-manager-kicker">Motion preview</span><h2 class="modal-title h5 mb-0" id="nexus-motion-preview-title">A smoother ITFlow experience</h2></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="nexus-motion-preview-icon"><i class="fas fa-magic" aria-hidden="true"></i></div><p class="lead mb-2">This is how windows will arrive.</p><p class="text-muted mb-0">The selected profile also shapes dropdowns, notifications, tooltips, popovers, and floating panels throughout Nexus.</p><div class="alert alert-info mt-4 mb-0"><i class="fas fa-universal-access mr-2" aria-hidden="true"></i>Operating-system and Theme Studio reduced-motion preferences always take priority.</div></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal">Looks good</button></div></div></div></div>

<script>
(function () {
    'use strict';
    var studio = document.getElementById('nexus-theme-studio');
    if (!studio) return;
    var preview = document.getElementById('nexus-live-preview');
    var presetField = document.getElementById('nexus-preset');
    var workspace = studio.querySelector('.nexus-studio-workspace');
    var workspaceLinks = Array.prototype.slice.call(studio.querySelectorAll('[data-workspace-section]'));
    var designSections = ['brand', 'colors', 'layout', 'motion', 'content'];
    var colorVariables = {primary:'--preview-primary', secondary:'--preview-secondary', sidebar:'--preview-sidebar', header:'--preview-header', header_text:'--preview-header-text', auth_background:'--preview-auth', page:'--preview-page', surface:'--preview-surface', text:'--preview-text'};

    function showWorkspaceSection(section, updateLocation) {
        var selected = workspaceLinks.find(function (link) { return link.dataset.workspaceSection === section; });
        if (!workspace || !selected) return;
        workspace.dataset.activeSection = section;
        workspaceLinks.forEach(function (link) {
            var active = link === selected;
            link.classList.toggle('active', active);
            link.setAttribute('aria-current', active ? 'page' : 'false');
        });
        studio.querySelectorAll('.nexus-studio-editor .tab-pane').forEach(function (panel) {
            var active = panel.id === 'nexus-' + section && designSections.indexOf(section) !== -1;
            panel.classList.toggle('active', active);
            panel.classList.toggle('show', active);
        });
        document.getElementById('nexus-workspace-title').textContent = selected.dataset.workspaceTitle;
        document.getElementById('nexus-workspace-description').textContent = selected.dataset.workspaceDescription;
        document.getElementById('nexus-workspace-step').textContent = (workspaceLinks.indexOf(selected) + 1) + ' of ' + workspaceLinks.length;
        if (section === 'layout') showPreviewMode('navigation');
        if (section === 'brand' || section === 'content') showPreviewMode('auth');
        if (updateLocation && window.history && window.history.replaceState) window.history.replaceState(null, '', selected.getAttribute('href'));
    }

    workspaceLinks.forEach(function (link) { link.addEventListener('click', function (event) { event.preventDefault(); showWorkspaceSection(link.dataset.workspaceSection, true); }); });
    var requestedSection = window.location.hash.indexOf('#nexus-') === 0 ? window.location.hash.slice(7) : 'brand';
    if (!workspaceLinks.some(function (link) { return link.dataset.workspaceSection === requestedSection; })) requestedSection = 'brand';

    function updateStudioPalette() {
        var primary = studio.querySelector('[data-color="primary"]').value;
        var secondary = studio.querySelector('[data-color="secondary"]').value;
        var sidebar = studio.querySelector('[data-color="sidebar"]').value;
        if (![primary, secondary, sidebar].every(function (value) { return /^#[0-9a-f]{6}$/i.test(value); })) return;
        studio.style.setProperty('--nexus-cyan', primary);
        studio.style.setProperty('--nexus-violet', secondary);
        studio.style.setProperty('--nexus-night', sidebar);
        studio.style.setProperty('--nexus-gradient', 'linear-gradient(110deg, ' + secondary + ' 0%, ' + primary + ' 100%)');
    }

    function setPreviewText(key, value) {
        studio.querySelectorAll('[data-preview-target="' + key + '"]').forEach(function (target) { target.textContent = value || (key === 'brand' ? <?= json_encode($nexusBrandPlaceholder, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> : ''); });
    }
    function rgb(hex) { return [1, 3, 5].map(function (offset) { var value = parseInt(hex.slice(offset, offset + 2), 16) / 255; return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4); }); }
    function contrast(first, second) { var a = rgb(first), b = rgb(second); var one = 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2]; var two = 0.2126 * b[0] + 0.7152 * b[1] + 0.0722 * b[2]; return (Math.max(one, two) + 0.05) / (Math.min(one, two) + 0.05); }
    function contrastInk(hex) { var channels = [1, 3, 5].map(function (offset) { return parseInt(hex.slice(offset, offset + 2), 16); }); return ((channels[0] * 299 + channels[1] * 587 + channels[2] * 114) / 1000) >= 145 ? '#0b0a17' : '#ffffff'; }
    function updateContrast() { var primary = studio.querySelector('[data-color="primary"]').value; var text = studio.querySelector('[data-color="text"]').value; var surface = studio.querySelector('[data-color="surface"]').value; if (![primary, text, surface].every(function (value) { return /^#[0-9a-f]{6}$/i.test(value); })) return; var bodyRatio = contrast(text, surface); var buttonRatio = contrast(primary, contrastInk(primary)); var pass = bodyRatio >= 4.5 && buttonRatio >= 4.5; var report = document.getElementById('nexus-contrast-report'); report.classList.toggle('nexus-contrast-pass', pass); report.classList.toggle('nexus-contrast-warning', !pass); document.getElementById('nexus-contrast-copy').textContent = (pass ? 'Pass' : 'Needs attention') + ' — text ' + bodyRatio.toFixed(2) + ':1 · primary button ' + buttonRatio.toFixed(2) + ':1'; }
    studio.querySelectorAll('.nexus-preview-input').forEach(function (input) { input.addEventListener('input', function () { setPreviewText(input.dataset.preview, input.value); }); });
    studio.querySelectorAll('.nexus-color-field').forEach(function (field) {
        var picker = field.querySelector('.nexus-color-picker');
        var text = field.querySelector('.nexus-color-value');
        function apply(value) { if (/^#[0-9a-f]{6}$/i.test(value)) { picker.value = value; preview.style.setProperty(colorVariables[text.dataset.color], value); presetField.value = 'custom'; studio.querySelectorAll('.nexus-preset').forEach(function (item) { item.classList.remove('active'); }); updateStudioPalette(); updateContrast(); } }
        picker.addEventListener('input', function () { text.value = picker.value; apply(picker.value); });
        text.addEventListener('input', function () { apply(text.value); });
    });
    studio.querySelectorAll('.nexus-preset').forEach(function (button) { button.addEventListener('click', function () { var colors = JSON.parse(button.dataset.colors); Object.keys(colors).forEach(function (key) { var input = studio.querySelector('[data-color="' + key + '"]'); if (input) { input.value = colors[key]; input.closest('.nexus-color-field').querySelector('.nexus-color-picker').value = colors[key]; preview.style.setProperty(colorVariables[key], colors[key]); } }); presetField.value = button.dataset.preset; studio.querySelectorAll('.nexus-preset').forEach(function (item) { item.classList.toggle('active', item === button); }); updateStudioPalette(); updateContrast(); }); });
    studio.querySelectorAll('.nexus-image-input').forEach(function (logoInput) { logoInput.addEventListener('change', function () { var file = logoInput.files && logoInput.files[0]; if (!file || !/^image\/(png|jpeg|webp|gif)$/.test(file.type)) return; var reader = new FileReader(); reader.onload = function (event) { document.getElementById(logoInput.dataset.previewTargetId).innerHTML = '<img src="' + event.target.result + '" alt="Selected logo preview">'; var selector = logoInput.dataset.previewLogo === 'dark' ? '.nexus-preview-shell-brand' : '.nexus-preview-brand,.nexus-preview-shell-brand'; studio.querySelectorAll(selector).forEach(function (brand) { var old = brand.querySelector('img,.nexus-preview-symbol'); if (old) old.remove(); var image = document.createElement('img'); image.src = event.target.result; image.alt = 'Selected logo preview'; brand.insertBefore(image, brand.firstChild); }); }; reader.readAsDataURL(file); }); });
    function showPreviewMode(mode) { studio.querySelectorAll('.nexus-preview-mode').forEach(function (item) { var selected = item.dataset.mode === mode; item.classList.toggle('active', selected); item.classList.toggle('btn-info', selected); item.classList.toggle('btn-outline-info', !selected); item.setAttribute('aria-pressed', selected ? 'true' : 'false'); }); studio.querySelectorAll('.nexus-preview-panel').forEach(function (panel) { panel.classList.toggle('d-none', panel.dataset.previewPanel !== mode); }); document.getElementById('nexus-preview-title').textContent = mode === 'navigation' ? 'Sidebar & header' : 'Authentication'; }
    studio.querySelectorAll('.nexus-preview-mode').forEach(function (button) { button.addEventListener('click', function () { showPreviewMode(button.dataset.mode); }); });
    function updateNavigationPreview() { var shell = studio.querySelector('.nexus-preview-shell'); if (!shell) return; var width = document.getElementById('nexus-sidebar-width').value; var headerStyle = document.getElementById('nexus-header-style').value; shell.dataset.width = width; shell.style.setProperty('--preview-sidebar-width', Math.max(30, Math.min(48, width / 7)) + '%'); shell.dataset.menuDensity = document.getElementById('nexus-menu-density').value; shell.dataset.contentDensity = document.getElementById('nexus-density').value; shell.dataset.headerStyle = headerStyle; shell.dataset.navigationStyle = document.getElementById('nexus-navigation-style').value; shell.classList.toggle('is-compact', document.getElementById('nexus-sidebar-compact').checked); shell.querySelector('[data-preview-header-label]').textContent = headerStyle.charAt(0).toUpperCase() + headerStyle.slice(1); document.getElementById('nexus-sidebar-width-output').textContent = width + 'px'; }
    studio.querySelectorAll('.nexus-navigation-control').forEach(function (control) { function refreshLayoutPreview() { updateNavigationPreview(); showPreviewMode('navigation'); } control.addEventListener('input', refreshLayoutPreview); control.addEventListener('change', refreshLayoutPreview); });
    var logoSize = document.getElementById('nexus-logo-size'); var logoAlignment = document.getElementById('nexus-logo-alignment'); function updateLogoPreview() { document.getElementById('nexus-logo-size-output').textContent = logoSize.value + '%'; studio.querySelectorAll('.nexus-preview-brand img,.nexus-preview-shell-brand img').forEach(function (image) { image.style.width = logoSize.value + '%'; }); studio.querySelectorAll('.nexus-preview-brand,.nexus-preview-shell-brand').forEach(function (brand) { brand.style.justifyContent = logoAlignment.value === 'center' ? 'center' : (logoAlignment.value === 'right' ? 'flex-end' : 'flex-start'); }); } logoSize.addEventListener('input', updateLogoPreview); logoAlignment.addEventListener('change', updateLogoPreview);
    var overlay = document.getElementById('nexus-background-overlay'); var backgroundPosition = document.getElementById('nexus-background-position'); function updateBackgroundPreview() { document.getElementById('nexus-background-overlay-output').textContent = overlay.value + '%'; preview.style.setProperty('--preview-overlay', String(Number(overlay.value) / 100)); preview.style.setProperty('--preview-bg-position', backgroundPosition.value + ' center'); } overlay.addEventListener('input', updateBackgroundPreview); backgroundPosition.addEventListener('change', updateBackgroundPreview);
    var backgroundInput = document.getElementById('nexus-login-background'); backgroundInput.addEventListener('change', function () { var file = backgroundInput.files && backgroundInput.files[0]; if (!file || !/^image\/(png|jpeg|webp)$/.test(file.type)) return; var reader = new FileReader(); reader.onload = function (event) { preview.style.setProperty('--preview-bg-image', 'url("' + event.target.result + '")'); }; reader.readAsDataURL(file); });
    var scale = document.getElementById('nexus-font-scale');
    scale.addEventListener('input', function () { document.getElementById('nexus-font-scale-output').textContent = scale.value + '%'; preview.style.fontSize = scale.value + '%'; });
    var motionStyle = document.getElementById('nexus-motion-style');
    var reduceMotion = document.getElementById('nexus-reduce-motion');
    function updateMotionPreview() { ['subtle', 'fluid', 'snappy'].forEach(function (profile) { document.body.classList.toggle('nexus-motion-' + profile, motionStyle.value === profile); }); document.body.classList.toggle('nexus-motion-reduced', reduceMotion.checked); }
    motionStyle.addEventListener('change', updateMotionPreview);
    reduceMotion.addEventListener('change', updateMotionPreview);
    document.getElementById('nexus-export').addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-configuration.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    var exportPresets = document.getElementById('nexus-export-presets'); if (exportPresets) exportPresets.addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-presets-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-presets.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    var scheduleForm = document.getElementById('nexus-schedule-form'); scheduleForm.addEventListener('submit', function (event) { var local = document.getElementById('nexus-schedule-local').value; if (!local) return; var date = new Date(local); if (isNaN(date.getTime())) { event.preventDefault(); return; } document.getElementById('nexus-schedule-at').value = date.toISOString(); });
    updateNavigationPreview(); updateLogoPreview(); updateBackgroundPreview();
    updateContrast();
    showWorkspaceSection(requestedSection, false);
    document.getElementById('nexus-customizer-form').addEventListener('input', function () { workspace.classList.add('is-dirty'); });
    document.getElementById('nexus-customizer-form').addEventListener('change', function () { workspace.classList.add('is-dirty'); });
    <?php if ($nexusUpdateBusy) { ?>setTimeout(function () { window.location.reload(); }, 3500);<?php } ?>
})();
</script>

<?php require_once '../includes/footer.php'; ?>

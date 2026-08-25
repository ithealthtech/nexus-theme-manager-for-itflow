<?php

require_once 'includes/inc_all_admin.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

$nexusDocumentRoot = nexusThemeDocumentRoot();
$nexusThemeEnabled = nexusThemeIsEnabled();
$nexusControlWritable = nexusThemeControlIsWritable();
$nexusActiveSettings = nexusThemeSettings();
$nexusDraftSettings = nexusThemeDraftSettings();
$nexusHasDraft = $nexusDraftSettings !== null && $nexusDraftSettings !== $nexusActiveSettings;
$nexusSettings = $nexusHasDraft ? $nexusDraftSettings : $nexusActiveSettings;
$nexusDraftVersion = nexusThemeDraftVersion();
$nexusDefaults = nexusThemeDefaults();
$nexusPresets = nexusThemePresets();
$nexusLogoUrl = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($nexusSettings, '', 'light'), $nexusSettings);
$nexusDarkLogoUrl = nexusThemeVersionedAssetUrl($nexusSettings['branding']['logo_dark_path'], $nexusSettings);
$nexusSavedPresets = nexusThemeSavedPresets();
$nexusSavedPresetsJson = nexusThemeExportPresets();
$nexusSchedule = nexusThemeSchedule();
$nexusRevisions = array_reverse(nexusThemeRevisions());
$nexusActiveHash = nexusThemeSettingsHash($nexusActiveSettings);
$nexusDraftChanges = $nexusHasDraft ? nexusThemeSettingsDiff($nexusActiveSettings, $nexusSettings) : [];
$nexusBrandPlaceholder = 'Nexus MSP';
$nexusPreviewDocuments = [];
foreach (['auth', 'reset', 'dashboard', 'technician', 'client', 'mobile', 'invoice', 'print'] as $nexusPreviewSurface) {
    $nexusPreviewDocuments[$nexusPreviewSurface] = nexusThemePreviewDocument($nexusSettings, $nexusPreviewSurface, $nexusBrandPlaceholder);
}
$nexusQualityReport = nexusThemeQualityReport($nexusSettings, 390);
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
                    <p class="lead mb-4">Design safely in a private draft, inspect the exact runtime surfaces, then publish every change atomically.</p>
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="badge badge-<?= $nexusStatusClass ?> nexus-manager-status mr-2 mb-2"><i class="fas fa-circle mr-1" aria-hidden="true"></i><?= $nexusStatusLabel ?></span>
                        <?php if ($nexusHasDraft) { ?><span class="badge badge-warning nexus-manager-status mr-2 mb-2"><i class="fas fa-pencil-alt mr-1" aria-hidden="true"></i>Unpublished draft</span><?php } else { ?><span class="badge badge-light nexus-manager-status mr-2 mb-2"><i class="fas fa-check mr-1" aria-hidden="true"></i>Published</span><?php } ?>
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
                    <small class="d-block mt-2 nexus-manager-meta">Pausing affects presentation only. Draft changes remain private until Publish.</small>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$nexusControlWritable) { ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i>Theme Studio is read-only because the web service cannot write to the ITFlow <code>uploads</code> directory.</div>
    <?php } ?>

    <section class="card nexus-draft-bar <?= $nexusHasDraft ? 'nexus-draft-bar-pending' : 'nexus-draft-bar-clean' ?>" aria-live="polite">
        <div class="card-body">
            <div class="nexus-draft-state">
                <span class="nexus-draft-state-icon"><i class="fas fa-<?= $nexusHasDraft ? 'pencil-ruler' : 'check' ?>"></i></span>
                <div><span class="nexus-manager-kicker"><?= $nexusHasDraft ? 'Private workspace' : 'Live design' ?></span><strong><?= $nexusHasDraft ? count($nexusDraftChanges) . ' unpublished change' . (count($nexusDraftChanges) === 1 ? '' : 's') : 'Published design is current' ?></strong><small><?= $nexusHasDraft ? 'Exact previews below are rendered from this saved draft. Visitors still see the published revision.' : 'Start editing and choose Save draft; nothing goes live until you publish.' ?></small></div>
            </div>
            <?php if ($nexusHasDraft) { ?><div class="nexus-draft-actions"><label class="sr-only" for="nexus-revision-name">Revision name</label><input class="form-control" id="nexus-revision-name" name="nexus_revision_name" form="nexus-publish-draft-form" maxlength="120" placeholder="Revision name (optional)"><button type="submit" form="nexus-discard-draft-form" class="btn btn-outline-secondary" onclick="return confirm('Discard every unpublished Nexus change?')">Discard draft</button><button type="submit" form="nexus-publish-draft-form" class="btn btn-success" onclick="return confirm('Publish this draft across every Nexus surface now?')"><i class="fas fa-cloud-upload-alt mr-2"></i>Publish draft</button></div><?php } ?>
        </div>
    </section>

    <div class="row nexus-manager-metrics">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-<?= $nexusStatusClass ?>"><i class="fas fa-toggle-on"></i></span><span class="text-muted text-uppercase small font-weight-bold">Theme</span><div class="h4 mb-0 mt-2"><?= $nexusStatusLabel ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-info"><i class="fas fa-swatchbook"></i></span><span class="text-muted text-uppercase small font-weight-bold">Palette</span><div class="h4 mb-0 mt-2"><?= ucfirst(escapeHtml($nexusSettings['preset'])) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-indigo"><i class="fas fa-image"></i></span><span class="text-muted text-uppercase small font-weight-bold">Branding</span><div class="h4 mb-0 mt-2"><?= $nexusLogoUrl !== '' ? 'Custom logo' : 'ITFlow default' ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-<?= $nexusAssetsHealthy ? 'success' : 'danger' ?>"><i class="fas fa-heartbeat"></i></span><span class="text-muted text-uppercase small font-weight-bold">Core assets</span><div class="h4 mb-0 mt-2"><?= $nexusPresentAssets ?>/<?= count($nexusManagedAssets) ?> healthy</div></div></div></div>
    </div>

    <form action="post.php" method="post" enctype="multipart/form-data" id="nexus-customizer-form">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="nexus_theme_save" value="1">
        <input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>">
        <input type="hidden" name="nexus[preset]" id="nexus-preset" value="<?= escapeHtml($nexusSettings['preset']) ?>">

        <div class="nexus-studio-workspace mt-4" data-active-section="brand">
            <aside class="card nexus-workspace-navigation">
                <div class="card-body p-3">
                    <div class="nexus-workspace-navigation-title"><div><span class="nexus-manager-kicker">Theme settings</span><strong>Studio navigation</strong></div><label class="sr-only" for="nexus-section-select">Choose a Theme Studio section</label><select class="custom-select nexus-section-select" id="nexus-section-select" aria-label="Choose a Theme Studio section"><option value="brand">Branding</option><option value="colors">Colors</option><option value="layout">Layout</option><option value="quality">Design quality</option><option value="motion">Motion</option><option value="content">Content</option><option value="operations">Presets &amp; schedule</option><option value="system">Updates &amp; system</option></select></div>
                    <nav class="nav nexus-studio-tabs" aria-label="Theme Studio sections">
                        <span class="nexus-workspace-nav-label">Design</span>
                        <a class="nav-link active" href="#nexus-brand" data-workspace-section="brand" data-workspace-title="Branding" data-workspace-description="Logos, identity, browser details, and login artwork."><i class="fas fa-fingerprint"></i><span><strong>Branding</strong><small>Logos &amp; identity</small></span></a>
                        <a class="nav-link" href="#nexus-colors" data-workspace-section="colors" data-workspace-title="Colors" data-workspace-description="Palettes, surfaces, accents, and accessibility contrast."><i class="fas fa-palette"></i><span><strong>Colors</strong><small>Palette &amp; contrast</small></span></a>
                        <a class="nav-link" href="#nexus-layout" data-workspace-section="layout" data-workspace-title="Layout" data-workspace-description="Sidebar, header, navigation, density, and interface scale."><i class="fas fa-sliders-h"></i><span><strong>Layout</strong><small>Navigation &amp; spacing</small></span></a>
                        <a class="nav-link" href="#nexus-quality" data-workspace-section="quality" data-workspace-title="Design quality" data-workspace-description="Accessibility findings, responsive risks, direct control links, and safe corrections."><i class="fas fa-universal-access"></i><span><strong>Design quality</strong><small>Audit &amp; repair</small></span></a>
                        <a class="nav-link" href="#nexus-motion" data-workspace-section="motion" data-workspace-title="Motion" data-workspace-description="Animation profiles and reduced-motion behavior."><i class="fas fa-magic"></i><span><strong>Motion</strong><small>Popups &amp; modals</small></span></a>
                        <a class="nav-link" href="#nexus-content" data-workspace-section="content" data-workspace-title="Content" data-workspace-description="Login and client portal headings, messages, and supporting copy."><i class="fas fa-pen-nib"></i><span><strong>Content</strong><small>Headings &amp; messages</small></span></a>
                        <span class="nexus-workspace-nav-label">Manage</span>
                        <a class="nav-link" href="#nexus-operations" data-workspace-section="operations" data-workspace-title="Presets &amp; scheduling" data-workspace-description="Save designs, schedule activation, and recover the previous look."><i class="fas fa-layer-group"></i><span><strong>Presets &amp; schedule</strong><small>Reuse &amp; automate</small></span></a>
                        <a class="nav-link" href="#nexus-system" data-workspace-section="system" data-workspace-title="Updates &amp; system" data-workspace-description="Protected updates, configuration transfer, and factory reset."><i class="fas fa-shield-alt"></i><span><strong>Updates &amp; system</strong><small>Lifecycle &amp; tools</small></span></a>
                    </nav>
                    <div class="nexus-workspace-navigation-note"><i class="fas fa-pencil-ruler"></i><span>Save freely to the private draft. Live visitors see changes only after <strong>Publish</strong>.</span></div>
                </div>
            </aside>

            <div class="nexus-workspace-content">
                <header class="card nexus-workspace-heading">
                    <div class="card-body"><div><span class="nexus-manager-kicker">Current section</span><h2 class="h4 mb-1" id="nexus-workspace-title">Branding</h2><p class="text-muted mb-0" id="nexus-workspace-description">Logos, identity, browser details, and login artwork.</p></div><div class="nexus-workspace-heading-actions"><button type="button" class="btn btn-sm btn-outline-info nexus-preview-toggle" id="nexus-preview-toggle" aria-expanded="true"><i class="fas fa-eye mr-1"></i><span>Hide preview</span></button><button type="button" class="btn btn-sm btn-outline-secondary" id="nexus-section-previous"><i class="fas fa-chevron-left mr-1"></i>Previous</button><span class="nexus-workspace-step" id="nexus-workspace-step">1 of 8</span><button type="button" class="btn btn-sm btn-outline-secondary" id="nexus-section-next">Next<i class="fas fa-chevron-right ml-1"></i></button></div></div>
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
                                <div class="nexus-accessibility-inspector mt-4" id="nexus-accessibility-inspector" aria-live="polite">
                                    <div class="d-flex align-items-start justify-content-between flex-wrap mb-3">
                                        <div>
                                            <span class="nexus-manager-kicker">Accessibility inspector</span>
                                            <h3 class="h6 mb-1">Current draft checks</h3>
                                            <p class="text-muted mb-0">Tracks contrast, reduced-motion, brand text, and logo accessibility before you publish.</p>
                                        </div>
                                        <span class="badge badge-light" id="nexus-accessibility-summary">Checking</span>
                                    </div>
                                    <div class="nexus-accessibility-grid">
                                        <div><span>Body contrast</span><strong id="nexus-accessibility-body-contrast">--</strong><small id="nexus-accessibility-body-status">Waiting</small></div>
                                        <div><span>Primary button</span><strong id="nexus-accessibility-button-contrast">--</strong><small id="nexus-accessibility-button-status">Waiting</small></div>
                                        <div><span>Reduced motion</span><strong id="nexus-accessibility-motion">--</strong><small>Respects OS preference</small></div>
                                        <div><span>Branding</span><strong id="nexus-accessibility-brand">--</strong><small id="nexus-accessibility-brand-status">Waiting</small></div>
                                    </div>
                                    <div class="nexus-accessibility-checklist mt-3">
                                        <div><i class="fas fa-check-circle"></i><span id="nexus-accessibility-logo-check">Logo alt text present</span></div>
                                        <div><i class="fas fa-check-circle"></i><span id="nexus-accessibility-heading-check">Login heading and tagline supplied</span></div>
                                        <div><i class="fas fa-check-circle"></i><span id="nexus-accessibility-nav-check">Navigation labels remain readable at compact widths</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="nexus-quality" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-universal-access"></i></span><div><h2 class="h5 mb-1">Accessibility &amp; responsive inspector</h2><p class="text-muted mb-0">Find design risks, jump to the responsible setting, and save conservative corrections into the private draft.</p></div></div>
                                <div class="nexus-quality-score mt-4"><div><span class="nexus-manager-kicker">Draft quality score</span><strong><?= (int)$nexusQualityReport['score'] ?></strong><small>Phone audit at <?= (int)$nexusQualityReport['width'] ?>px</small></div><div class="nexus-quality-counts"><span class="badge badge-danger"><?= (int)$nexusQualityReport['counts']['error'] ?> errors</span><span class="badge badge-warning"><?= (int)$nexusQualityReport['counts']['warning'] ?> warnings</span><span class="badge badge-info"><?= (int)$nexusQualityReport['counts']['info'] ?> notes</span></div></div>
                                <div class="nexus-quality-findings mt-3" id="nexus-quality-findings" aria-live="polite">
                                    <?php if ($nexusQualityReport['findings'] === []) { ?><div class="nexus-empty-state"><i class="fas fa-check-circle"></i><span>No accessibility or responsive risks were detected at this viewport.</span></div><?php } ?>
                                    <?php foreach ($nexusQualityReport['findings'] as $nexusFinding) { ?><article class="nexus-quality-finding nexus-quality-finding-<?= escapeHtml($nexusFinding['severity']) ?>"><span class="nexus-quality-finding-icon"><i class="fas fa-<?= $nexusFinding['severity'] === 'error' ? 'times-circle' : ($nexusFinding['severity'] === 'warning' ? 'exclamation-triangle' : 'info-circle') ?>"></i></span><div><strong><?= escapeHtml($nexusFinding['title']) ?></strong><p><?= escapeHtml($nexusFinding['detail']) ?></p><small><?= ucfirst(escapeHtml($nexusFinding['surface'])) ?> surface</small></div><button type="button" class="btn btn-sm btn-outline-info nexus-finding-target" data-target-section="<?= escapeHtml($nexusFinding['section']) ?>" data-target-control="<?= escapeHtml($nexusFinding['control']) ?>">Open setting</button></article><?php } ?>
                                </div>
                                <div class="nexus-quality-actions mt-3"><div><strong>Safe automatic corrections</strong><small>Only validated colors, scale, spacing, logo text, and reduced-motion settings are changed. The result stays unpublished.</small></div><button type="submit" form="nexus-quality-fix-form" class="btn btn-primary" <?= $nexusControlWritable && $nexusQualityReport['findings'] !== [] ? '' : 'disabled' ?>><i class="fas fa-magic mr-2"></i>Apply fixes to draft</button></div>
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
                        <span class="text-muted small" id="nexus-draft-save-state"><i class="fas fa-lock mr-1 text-success"></i>Saved drafts are private and validated</span>
                        <button class="btn btn-primary" type="submit" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-save mr-2"></i>Save draft</button>
                    </div>
                </section>
            </div>

            <div class="col-xl-5 mt-4 mt-xl-0 nexus-workspace-side-column">
                <div class="nexus-preview-sticky">
                    <section class="card nexus-preview-card nexus-workspace-preview">
                        <div class="card-header border-0"><div class="d-flex align-items-start justify-content-between flex-wrap"><div><span class="nexus-manager-kicker">Exact runtime preview</span><h2 class="h5 mb-1" id="nexus-preview-title">Authentication</h2><small class="text-muted">Uses the same validated settings model, generated CSS, AdminLTE markup, and Nexus stylesheet as live pages.</small></div><span class="badge badge-<?= $nexusHasDraft ? 'warning' : 'success' ?> mt-1"><?= $nexusHasDraft ? 'Saved draft' : 'Published' ?></span></div><label class="sr-only" for="nexus-preview-surface-select">Choose a preview surface</label><select class="custom-select nexus-preview-surface-select mt-3" id="nexus-preview-surface-select" aria-label="Choose a preview surface"><option value="auth">Login</option><option value="reset">Password reset</option><option value="dashboard">Technician dashboard</option><option value="technician">Ticket queue</option><option value="client">Client portal</option><option value="mobile">Mobile navigation</option><option value="invoice">Guest invoice</option><option value="print">Print / PDF invoice</option></select><div class="nexus-preview-surface-tabs nexus-preview-surface-tabs-expanded mt-3" role="group" aria-label="Preview surface"><button type="button" class="btn btn-info nexus-preview-mode active" data-mode="auth">Login</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="reset">Reset</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="dashboard">Dashboard</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="technician">Tickets</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="client">Client</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="mobile">Mobile nav</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="invoice">Invoice</button><button type="button" class="btn btn-outline-info nexus-preview-mode" data-mode="print">Print/PDF</button></div><div class="nexus-responsive-tester mt-3"><div class="d-flex align-items-center justify-content-between flex-wrap mb-2"><div><span class="nexus-manager-kicker">Responsive tester</span><strong>Preview size</strong></div><small class="text-muted" id="nexus-responsive-copy">Laptop 1366px</small></div><div class="btn-group btn-group-sm w-100 nexus-responsive-presets" role="group" aria-label="Responsive preview presets"><button type="button" class="btn btn-outline-info" data-responsive-mode="widescreen" data-responsive-width="1600">Wide</button><button type="button" class="btn btn-outline-info active" data-responsive-mode="laptop" data-responsive-width="1366">Laptop</button><button type="button" class="btn btn-outline-info" data-responsive-mode="tablet" data-responsive-width="768">Tablet</button><button type="button" class="btn btn-outline-info" data-responsive-mode="phone" data-responsive-width="390">Phone</button></div><div class="form-group mt-3 mb-0"><label for="nexus-responsive-width">Custom width</label><input type="range" class="custom-range" id="nexus-responsive-width" min="320" max="1920" step="10" value="1366"><small class="form-text text-muted">Drag to inspect narrow breakpoints without leaving Theme Studio.</small></div><div class="nexus-responsive-alerts mt-2" id="nexus-responsive-alerts" aria-live="polite"><span class="badge badge-success"><i class="fas fa-check mr-1"></i>No predicted collisions at 1366px</span></div></div></div>
                        <div class="card-body p-0"><div class="nexus-runtime-preview" id="nexus-live-preview">
                            <?php foreach ($nexusPreviewDocuments as $nexusPreviewSurface => $nexusPreviewDocument) { ?>
                                <div class="nexus-runtime-preview-panel <?= $nexusPreviewSurface === 'auth' ? '' : 'd-none' ?>" data-preview-panel="<?= escapeHtml($nexusPreviewSurface) ?>"><div class="nexus-preview-windowbar"><i></i><i></i><i></i><span><?= ucfirst(escapeHtml($nexusPreviewSurface)) ?> · <?= $nexusHasDraft ? 'Draft' : 'Published' ?></span></div><div class="nexus-runtime-preview-viewport"><iframe class="nexus-runtime-preview-frame" sandbox="" loading="lazy" title="<?= ucfirst(escapeHtml($nexusPreviewSurface)) ?> Nexus preview" srcdoc="<?= escapeHtml($nexusPreviewDocument) ?>"></iframe></div></div>
                            <?php } ?>
                        </div></div>
                        <div class="card-footer bg-transparent nexus-preview-footer"><small class="text-muted" id="nexus-preview-freshness"><i class="fas fa-info-circle mr-1"></i>Editing the form does not alter this trustworthy snapshot. Save draft to regenerate it.</small></div>
                    </section>

                    <section class="card nexus-design-operations nexus-workspace-panel" data-workspace-panel="operations" id="nexus-operations">
                        <div class="card-header border-0"><div><span class="nexus-manager-kicker">Design operations</span><h2 class="h5 mb-0">Drafts, history, presets &amp; schedule</h2></div></div>
                        <div class="card-body pt-0">
                            <section class="nexus-draft-comparison mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2"><div><span class="nexus-manager-kicker">Draft comparison</span><h3 class="h6 mb-0">Unpublished vs. live</h3></div><?php if ($nexusHasDraft) { ?><span class="badge badge-warning"><?= count($nexusDraftChanges) ?> changed</span><?php } ?></div>
                                <?php if ($nexusHasDraft) { ?><div class="table-responsive"><table class="table table-sm nexus-diff-table mb-0"><thead><tr><th>Setting</th><th>Published</th><th>Draft</th></tr></thead><tbody><?php foreach (array_slice($nexusDraftChanges, 0, 12) as $nexusChange) { ?><tr><th><?= escapeHtml(str_replace(['branding.', 'content.', 'colors.', 'appearance.', '_'], ['', '', '', '', ' '], $nexusChange['path'])) ?></th><td><?= escapeHtml($nexusChange['before'] === '' ? '—' : $nexusChange['before']) ?></td><td><?= escapeHtml($nexusChange['after'] === '' ? '—' : $nexusChange['after']) ?></td></tr><?php } ?></tbody></table></div><?php if (count($nexusDraftChanges) > 12) { ?><small class="text-muted d-block mt-2">Plus <?= count($nexusDraftChanges) - 12 ?> additional changes.</small><?php } ?><?php } else { ?><div class="nexus-empty-state"><i class="fas fa-check-circle"></i><span>No unpublished differences.</span></div><?php } ?>
                            </section>

                            <section class="nexus-revision-history mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2"><div><span class="nexus-manager-kicker">Revision history</span><h3 class="h6 mb-0">Published designs</h3></div><span class="badge badge-light"><?= count($nexusRevisions) ?>/<?= NEXUS_THEME_MAX_REVISIONS ?></span></div>
                                <?php if ($nexusRevisions !== []) { ?><div class="nexus-revision-list"><?php foreach (array_slice($nexusRevisions, 0, 12) as $nexusRevision) { $nexusRevisionIsActive = $nexusRevision['hash'] === $nexusActiveHash; $nexusRevisionDiff = nexusThemeSettingsDiff($nexusActiveSettings, $nexusRevision['settings']); $nexusRevisionChanges = count($nexusRevisionDiff); ?><article><div><strong><?= escapeHtml($nexusRevision['action']) ?><?php if ($nexusRevisionIsActive) { ?> <span class="badge badge-success">Live</span><?php } ?><?php if ($nexusRevision['pinned']) { ?> <span class="badge badge-primary"><i class="fas fa-thumbtack mr-1"></i>Known good</span><?php } ?></strong><small><?= escapeHtml($nexusRevision['actor']) ?> · <?= escapeHtml($nexusRevision['created_at']) ?> · <?= $nexusRevisionChanges ?> difference<?= $nexusRevisionChanges === 1 ? '' : 's' ?></small><code><?= escapeHtml(substr($nexusRevision['hash'], 0, 10)) ?></code><?php if ($nexusRevisionDiff !== []) { ?><details class="nexus-revision-diff"><summary>Compare with live</summary><ul><?php foreach ($nexusRevisionDiff as $nexusRevisionChange) { ?><li><strong><?= escapeHtml(str_replace('_', ' ', $nexusRevisionChange['path'])) ?></strong><span><?= escapeHtml($nexusRevisionChange['before'] === '' ? '—' : $nexusRevisionChange['before']) ?> → <?= escapeHtml($nexusRevisionChange['after'] === '' ? '—' : $nexusRevisionChange['after']) ?></span></li><?php } ?></ul></details><?php } ?></div><div class="nexus-revision-actions"><button type="submit" form="nexus-restore-revision-<?= escapeHtml($nexusRevision['id']) ?>" class="btn btn-sm btn-outline-info" <?= $nexusRevisionIsActive ? 'disabled' : '' ?> onclick="return confirm('Load this published revision into the private draft workspace?')">Restore</button><button type="submit" form="nexus-pin-revision-<?= escapeHtml($nexusRevision['id']) ?>" class="btn btn-sm <?= $nexusRevision['pinned'] ? 'btn-primary' : 'btn-outline-secondary' ?>"><i class="fas fa-thumbtack mr-1"></i><?= $nexusRevision['pinned'] ? 'Unpin' : 'Pin' ?></button></div></article><?php } ?></div><?php } else { ?><div class="nexus-empty-state"><i class="fas fa-history"></i><span>The current design and every new publication will appear here after the first publish.</span></div><?php } ?>
                            </section>

                            <hr><label for="nexus-preset-name">Save this design as a preset</label><div class="input-group mb-3"><input class="form-control" id="nexus-preset-name" name="nexus_preset_name" form="nexus-save-preset-form" maxlength="50" placeholder="e.g. Holiday campaign"><div class="input-group-append"><button class="btn btn-outline-info" type="submit" form="nexus-save-preset-form" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-bookmark mr-1"></i>Save</button></div></div>
                            <?php if ($nexusSavedPresets !== []) { ?><div class="nexus-saved-preset-list mb-3"><?php foreach ($nexusSavedPresets as $savedPreset) { ?><div><span><i class="fas fa-swatchbook mr-2 text-info"></i><?= escapeHtml($savedPreset['name']) ?></span><span><button type="submit" form="nexus-apply-preset-<?= $savedPreset['id'] ?>" class="btn btn-sm btn-link">Apply</button><button type="submit" form="nexus-delete-preset-<?= $savedPreset['id'] ?>" class="btn btn-sm btn-link text-danger" aria-label="Delete <?= escapeHtml($savedPreset['name']) ?>"><i class="fas fa-trash"></i></button></span></div><?php } ?></div><?php } else { ?><p class="small text-muted">No saved presets yet.</p><?php } ?>
                            <div class="btn-group w-100 mb-3"><button type="button" class="btn btn-outline-info" id="nexus-export-presets" <?= $nexusSavedPresets === [] ? 'disabled' : '' ?>><i class="fas fa-download mr-1"></i>Export presets</button><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#nexus-import-presets-modal"><i class="fas fa-upload mr-1"></i>Import presets</button></div><textarea class="d-none" id="nexus-export-presets-json"><?= escapeHtml($nexusSavedPresetsJson) ?></textarea>
                            <hr><label for="nexus-schedule-local">Scheduled activation</label><div class="form-row"><div class="col-7"><input type="datetime-local" class="form-control" id="nexus-schedule-local" form="nexus-schedule-form" required></div><div class="col-5"><select class="custom-select" name="nexus_schedule_action" form="nexus-schedule-form"><option value="enable">Activate</option><option value="disable">Pause</option></select></div></div><input type="hidden" name="nexus_schedule_at" id="nexus-schedule-at" form="nexus-schedule-form"><div class="d-flex align-items-center justify-content-between mt-2"><small class="text-muted"><?php if ($nexusSchedule !== null) { ?>Next: <?= ucfirst($nexusSchedule['action']) ?> at <?= escapeHtml($nexusSchedule['activate_at']) ?><?php } else { ?>Applies on the first request at or after this time.<?php } ?></small><span><button type="submit" form="nexus-schedule-form" class="btn btn-sm btn-outline-info">Schedule</button><?php if ($nexusSchedule !== null) { ?><button type="submit" form="nexus-cancel-schedule-form" class="btn btn-sm btn-link text-danger">Cancel</button><?php } ?></span></div>
                            <hr><small class="text-muted"><i class="fas fa-shield-alt mr-1 text-success"></i>Restoring history always creates a private draft first, so a mistaken rollback never changes the live interface.</small>
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
                        <div class="card-body pt-0"><p class="text-muted">Move designs between Nexus installations, download a sanitized support bundle, or load factory defaults into the private draft.</p><div class="nexus-system-tool-grid"><button type="button" class="btn btn-outline-info" id="nexus-export"><i class="fas fa-download mr-1"></i>Export workspace</button><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#nexus-import-modal"><i class="fas fa-upload mr-1"></i>Import to draft</button><button type="submit" form="nexus-diagnostics-form" class="btn btn-outline-info"><i class="fas fa-stethoscope mr-1"></i>Diagnostics bundle</button><button type="submit" form="nexus-reset-form" class="btn btn-outline-danger" <?= $nexusControlWritable ? '' : 'disabled' ?> onclick="return confirm('Load every Nexus default into the private draft? The live design will not change.')"><i class="fas fa-undo mr-1"></i>Defaults to draft</button></div><small class="form-text mt-2">Diagnostics include versions, compatibility, settings hashes, quality findings, asset health, revision counts, and sanitized updater state—never database credentials or filesystem paths.</small><textarea class="d-none" id="nexus-export-json"><?= escapeHtml((string)$nexusExportJson) ?></textarea></div>
                    </section>
                </div>
            </div>
                </div>
            </div>
        </div>
    </form>

    <form action="post.php" method="post" id="nexus-remove-light-logo-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="logo-light"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-remove-dark-logo-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="logo-dark"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-remove-favicon-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="favicon"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-remove-background-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_asset" value="login-background"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-reset-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_reset" value="1"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-save-preset-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="save"></form>
    <?php foreach ($nexusSavedPresets as $savedPreset) { ?><form action="post.php" method="post" id="nexus-apply-preset-<?= $savedPreset['id'] ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="apply"><input type="hidden" name="nexus_preset_id" value="<?= $savedPreset['id'] ?>"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form><form action="post.php" method="post" id="nexus-delete-preset-<?= $savedPreset['id'] ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="delete"><input type="hidden" name="nexus_preset_id" value="<?= $savedPreset['id'] ?>"></form><?php } ?>
    <form action="post.php" method="post" id="nexus-schedule-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_schedule_command" value="set"></form>
    <form action="post.php" method="post" id="nexus-cancel-schedule-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_schedule_command" value="cancel"></form>
    <form action="post.php" method="post" id="nexus-publish-draft-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_draft_action" value="publish"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-discard-draft-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_draft_action" value="discard"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-quality-fix-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_quality_fix" value="1"><input type="hidden" name="nexus_quality_width" value="390"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"></form>
    <form action="post.php" method="post" id="nexus-diagnostics-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_diagnostics_download" value="1"></form>
    <?php foreach ($nexusRevisions as $nexusRevision) { ?><form action="post.php" method="post" id="nexus-restore-revision-<?= escapeHtml($nexusRevision['id']) ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_draft_action" value="restore"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"><input type="hidden" name="nexus_revision_id" value="<?= escapeHtml($nexusRevision['id']) ?>"></form><form action="post.php" method="post" id="nexus-pin-revision-<?= escapeHtml($nexusRevision['id']) ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_revision_pin" value="<?= $nexusRevision['pinned'] ? '0' : '1' ?>"><input type="hidden" name="nexus_revision_id" value="<?= escapeHtml($nexusRevision['id']) ?>"></form><?php } ?>
    <form action="post.php" method="post" id="nexus-update-check-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_update_action" value="check"><input type="hidden" name="nexus_return_section" value="system"></form>
    <form action="post.php" method="post" id="nexus-update-install-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_update_action" value="update"><input type="hidden" name="nexus_return_section" value="system"></form>
</div>

<div class="modal fade" id="nexus-import-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_import" value="1"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-title">Import Nexus configuration</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a configuration exported from Theme Studio. Uploaded assets stay attached and the imported design remains private until you publish it.</p><textarea class="form-control nexus-json-editor" name="nexus_import_json" rows="16" required spellcheck="false" placeholder='{"schema": 1, ...}'></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload mr-2"></i>Import to draft</button></div></form></div></div>

<div class="modal fade" id="nexus-import-presets-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-presets-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="import"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-presets-title">Import saved presets</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a Nexus saved-presets export. Imported names are retained and new secure identifiers are generated.</p><textarea class="form-control nexus-json-editor" name="nexus_presets_json" rows="14" required spellcheck="false"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload mr-2"></i>Import presets</button></div></form></div></div>

<div class="modal fade" id="nexus-motion-preview-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-motion-preview-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><div><span class="nexus-manager-kicker">Motion preview</span><h2 class="modal-title h5 mb-0" id="nexus-motion-preview-title">A smoother ITFlow experience</h2></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="nexus-motion-preview-icon"><i class="fas fa-magic" aria-hidden="true"></i></div><p class="lead mb-2">This is how windows will arrive.</p><p class="text-muted mb-0">The selected profile also shapes dropdowns, notifications, tooltips, popovers, and floating panels throughout Nexus.</p><div class="alert alert-info mt-4 mb-0"><i class="fas fa-universal-access mr-2" aria-hidden="true"></i>Operating-system and Theme Studio reduced-motion preferences always take priority.</div></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal">Looks good</button></div></div></div></div>

<script>
(function () {
    'use strict';
    var studio = document.getElementById('nexus-theme-studio');
    if (!studio) return;
    var presetField = document.getElementById('nexus-preset');
    var workspace = studio.querySelector('.nexus-studio-workspace');
    var workspaceLinks = Array.prototype.slice.call(studio.querySelectorAll('[data-workspace-section]'));
    var designSections = ['brand', 'colors', 'layout', 'quality', 'motion', 'content'];
    var sectionSelect = document.getElementById('nexus-section-select');
    var sectionPrevious = document.getElementById('nexus-section-previous');
    var sectionNext = document.getElementById('nexus-section-next');
    var previewToggle = document.getElementById('nexus-preview-toggle');
    var previewSurfaceSelect = document.getElementById('nexus-preview-surface-select');

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
        var selectedIndex = workspaceLinks.indexOf(selected);
        document.getElementById('nexus-workspace-step').textContent = (selectedIndex + 1) + ' of ' + workspaceLinks.length;
        sectionSelect.value = section;
        sectionPrevious.disabled = selectedIndex === 0;
        sectionNext.disabled = selectedIndex === workspaceLinks.length - 1;
        previewToggle.hidden = designSections.indexOf(section) === -1;
        if (section === 'layout') showPreviewMode('technician');
        if (section === 'quality') showPreviewMode('mobile');
        if (section === 'brand' || section === 'content') showPreviewMode('auth');
        if (updateLocation && window.history && window.history.replaceState) window.history.replaceState(null, '', selected.getAttribute('href'));
    }

    workspaceLinks.forEach(function (link) { link.addEventListener('click', function (event) { event.preventDefault(); showWorkspaceSection(link.dataset.workspaceSection, true); }); });
    sectionSelect.addEventListener('change', function () { showWorkspaceSection(sectionSelect.value, true); });
    sectionPrevious.addEventListener('click', function () { var index = workspaceLinks.findIndex(function (link) { return link.classList.contains('active'); }); if (index > 0) showWorkspaceSection(workspaceLinks[index - 1].dataset.workspaceSection, true); });
    sectionNext.addEventListener('click', function () { var index = workspaceLinks.findIndex(function (link) { return link.classList.contains('active'); }); if (index >= 0 && index < workspaceLinks.length - 1) showWorkspaceSection(workspaceLinks[index + 1].dataset.workspaceSection, true); });
    previewToggle.addEventListener('click', function () { var collapsed = workspace.classList.toggle('is-preview-collapsed'); previewToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true'); previewToggle.querySelector('i').className = collapsed ? 'fas fa-eye mr-1' : 'fas fa-eye-slash mr-1'; previewToggle.querySelector('span').textContent = collapsed ? 'Show preview' : 'Hide preview'; });
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

    function rgb(hex) { return [1, 3, 5].map(function (offset) { var value = parseInt(hex.slice(offset, offset + 2), 16) / 255; return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4); }); }
    function contrast(first, second) { var a = rgb(first), b = rgb(second); var one = 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2]; var two = 0.2126 * b[0] + 0.7152 * b[1] + 0.0722 * b[2]; return (Math.max(one, two) + 0.05) / (Math.min(one, two) + 0.05); }
    function contrastInk(hex) { var channels = [1, 3, 5].map(function (offset) { return parseInt(hex.slice(offset, offset + 2), 16); }); return ((channels[0] * 299 + channels[1] * 587 + channels[2] * 114) / 1000) >= 145 ? '#0b0a17' : '#ffffff'; }
    function updateContrast() { var primary = studio.querySelector('[data-color="primary"]').value; var text = studio.querySelector('[data-color="text"]').value; var surface = studio.querySelector('[data-color="surface"]').value; if (![primary, text, surface].every(function (value) { return /^#[0-9a-f]{6}$/i.test(value); })) return; var bodyRatio = contrast(text, surface); var buttonRatio = contrast(primary, contrastInk(primary)); var pass = bodyRatio >= 4.5 && buttonRatio >= 4.5; var report = document.getElementById('nexus-contrast-report'); report.classList.toggle('nexus-contrast-pass', pass); report.classList.toggle('nexus-contrast-warning', !pass); document.getElementById('nexus-contrast-copy').textContent = (pass ? 'Pass' : 'Needs attention') + ' — text ' + bodyRatio.toFixed(2) + ':1 · primary button ' + buttonRatio.toFixed(2) + ':1'; }
    studio.querySelectorAll('.nexus-color-field').forEach(function (field) {
        var picker = field.querySelector('.nexus-color-picker');
        var text = field.querySelector('.nexus-color-value');
        function apply(value) { if (/^#[0-9a-f]{6}$/i.test(value)) { picker.value = value; presetField.value = 'custom'; studio.querySelectorAll('.nexus-preset').forEach(function (item) { item.classList.remove('active'); }); updateStudioPalette(); updateContrast(); } }
        picker.addEventListener('input', function () { text.value = picker.value; apply(picker.value); });
        text.addEventListener('input', function () { apply(text.value); });
    });
    studio.querySelectorAll('.nexus-preset').forEach(function (button) { button.addEventListener('click', function () { var colors = JSON.parse(button.dataset.colors); Object.keys(colors).forEach(function (key) { var input = studio.querySelector('[data-color="' + key + '"]'); if (input) { input.value = colors[key]; input.closest('.nexus-color-field').querySelector('.nexus-color-picker').value = colors[key]; } }); presetField.value = button.dataset.preset; studio.querySelectorAll('.nexus-preset').forEach(function (item) { item.classList.toggle('active', item === button); }); updateStudioPalette(); updateContrast(); }); });
    studio.querySelectorAll('.nexus-image-input').forEach(function (logoInput) { logoInput.addEventListener('change', function () { var file = logoInput.files && logoInput.files[0]; if (!file || !/^image\/(png|jpeg|webp|gif)$/.test(file.type)) return; var reader = new FileReader(); reader.onload = function (event) { document.getElementById(logoInput.dataset.previewTargetId).innerHTML = '<img src="' + event.target.result + '" alt="Selected logo preview">'; }; reader.readAsDataURL(file); }); });
    function showPreviewMode(mode) { var labels = {auth:'Authentication', reset:'Password reset', dashboard:'Technician dashboard', technician:'Ticket queue', client:'Client portal', mobile:'Mobile navigation', invoice:'Guest invoice', print:'Print / PDF invoice'}; studio.querySelectorAll('.nexus-preview-mode').forEach(function (item) { var selected = item.dataset.mode === mode; item.classList.toggle('active', selected); item.classList.toggle('btn-info', selected); item.classList.toggle('btn-outline-info', !selected); item.setAttribute('aria-pressed', selected ? 'true' : 'false'); }); studio.querySelectorAll('.nexus-runtime-preview-panel').forEach(function (panel) { panel.classList.toggle('d-none', panel.dataset.previewPanel !== mode); }); previewSurfaceSelect.value = mode; document.getElementById('nexus-preview-title').textContent = labels[mode] || 'Exact preview'; updateResponsivePreview(); updateAccessibilityReport(); }
    studio.querySelectorAll('.nexus-preview-mode').forEach(function (button) { button.addEventListener('click', function () { showPreviewMode(button.dataset.mode); }); });
    previewSurfaceSelect.addEventListener('change', function () { showPreviewMode(previewSurfaceSelect.value); });
    function updateNavigationPreview() { var width = document.getElementById('nexus-sidebar-width').value; document.getElementById('nexus-sidebar-width-output').textContent = width + 'px'; }
    studio.querySelectorAll('.nexus-navigation-control').forEach(function (control) { function refreshLayoutPreview() { updateNavigationPreview(); showPreviewMode('technician'); } control.addEventListener('input', refreshLayoutPreview); control.addEventListener('change', refreshLayoutPreview); });
    var logoSize = document.getElementById('nexus-logo-size'); var logoAlignment = document.getElementById('nexus-logo-alignment'); function updateLogoPreview() { document.getElementById('nexus-logo-size-output').textContent = logoSize.value + '%'; } logoSize.addEventListener('input', updateLogoPreview); logoAlignment.addEventListener('change', updateLogoPreview);
    var overlay = document.getElementById('nexus-background-overlay'); var backgroundPosition = document.getElementById('nexus-background-position'); function updateBackgroundPreview() { document.getElementById('nexus-background-overlay-output').textContent = overlay.value + '%'; } overlay.addEventListener('input', updateBackgroundPreview); backgroundPosition.addEventListener('change', updateBackgroundPreview);
    var scale = document.getElementById('nexus-font-scale');
    scale.addEventListener('input', function () { document.getElementById('nexus-font-scale-output').textContent = scale.value + '%'; });
    var motionStyle = document.getElementById('nexus-motion-style');
    var reduceMotion = document.getElementById('nexus-reduce-motion');
    function updateMotionPreview() { ['subtle', 'fluid', 'snappy'].forEach(function (profile) { document.body.classList.toggle('nexus-motion-' + profile, motionStyle.value === profile); }); document.body.classList.toggle('nexus-motion-reduced', reduceMotion.checked); }
    motionStyle.addEventListener('change', updateMotionPreview);
    reduceMotion.addEventListener('change', updateMotionPreview);
    var responsiveWidth = document.getElementById('nexus-responsive-width');
    var responsiveCopy = document.getElementById('nexus-responsive-copy');
    var responsivePresetButtons = Array.prototype.slice.call(studio.querySelectorAll('[data-responsive-mode]'));
    function updateResponsivePreview() {
        var activePanel = studio.querySelector('.nexus-runtime-preview-panel:not(.d-none)');
        var viewport = activePanel ? activePanel.querySelector('.nexus-runtime-preview-viewport') : null;
        var frame = activePanel ? activePanel.querySelector('.nexus-runtime-preview-frame') : null;
        var width = Math.max(320, Math.min(1920, parseInt(responsiveWidth.value, 10) || 1366));
        var preset = 'widescreen';
        if (width < 600) preset = 'phone';
        else if (width < 1024) preset = 'tablet';
        else if (width < 1500) preset = 'laptop';
        responsivePresetButtons.forEach(function (button) { var selected = button.dataset.responsiveMode === preset; button.classList.toggle('active', selected); button.classList.toggle('btn-info', selected); button.classList.toggle('btn-outline-info', !selected); });
        responsiveCopy.textContent = (preset.charAt(0).toUpperCase() + preset.slice(1)) + ' ' + width + 'px';
        if (!viewport || !frame) return;
        viewport.dataset.responsiveMode = preset;
        viewport.style.setProperty('--nexus-preview-width', width + 'px');
        var availableWidth = Math.max(1, viewport.clientWidth - 24);
        var scaleFactor = Math.min(1, availableWidth / width);
        viewport.style.setProperty('--nexus-preview-scale', scaleFactor);
        frame.style.height = width >= 1200 ? '50rem' : (width >= 900 ? '58rem' : (width >= 600 ? '72rem' : '80rem'));
        var alerts = [];
        var logoScale = parseInt(document.getElementById('nexus-logo-size').value, 10) || 100;
        var sidebarWidth = parseInt(document.getElementById('nexus-sidebar-width').value, 10) || 250;
        if (width <= 480 && logoScale > 115) alerts.push('Oversized logo');
        if (width <= 768 && sidebarWidth > 280 && !document.getElementById('nexus-sidebar-compact').checked) alerts.push('Sidebar collision risk');
        if (width <= 480) alerts.push('Check table scrolling');
        var alertBox = document.getElementById('nexus-responsive-alerts');
        alertBox.innerHTML = alerts.length ? alerts.map(function (label) { return '<span class="badge badge-warning mr-1"><i class="fas fa-exclamation-triangle mr-1"></i>' + label + '</span>'; }).join('') : '<span class="badge badge-success"><i class="fas fa-check mr-1"></i>No predicted collisions at ' + width + 'px</span>';
    }
    responsivePresetButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            responsiveWidth.value = button.dataset.responsiveWidth;
            updateResponsivePreview();
        });
    });
    responsiveWidth.addEventListener('input', updateResponsivePreview);
    window.addEventListener('resize', updateResponsivePreview);
    studio.querySelectorAll('.nexus-finding-target').forEach(function (button) { button.addEventListener('click', function () { showWorkspaceSection(button.dataset.targetSection, true); var control = document.getElementById(button.dataset.targetControl); if (control) { control.focus(); control.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block:'center'}); control.classList.add('nexus-quality-control-highlight'); setTimeout(function () { control.classList.remove('nexus-quality-control-highlight'); }, 1800); } }); });
    function updateAccessibilityReport() {
        var primary = studio.querySelector('[data-color="primary"]').value;
        var text = studio.querySelector('[data-color="text"]').value;
        var surface = studio.querySelector('[data-color="surface"]').value;
        var bodyRatio = contrast(text, surface);
        var buttonRatio = contrast(primary, contrastInk(primary));
        var logoAltField = document.getElementById('nexus-logo-alt').value.trim();
        var logoPresent = document.getElementById('nexus-logo-preview').querySelector('img') !== null || document.getElementById('nexus-dark-logo-preview').querySelector('img') !== null;
        var heading = document.getElementById('nexus-login-heading').value.trim();
        var tagline = document.getElementById('nexus-tagline').value.trim();
        var motion = reduceMotion.checked ? 'Reduced' : motionStyle.value.charAt(0).toUpperCase() + motionStyle.value.slice(1);
        document.getElementById('nexus-accessibility-body-contrast').textContent = bodyRatio.toFixed(2) + ':1';
        document.getElementById('nexus-accessibility-button-contrast').textContent = buttonRatio.toFixed(2) + ':1';
        document.getElementById('nexus-accessibility-motion').textContent = motion;
        document.getElementById('nexus-accessibility-brand').textContent = logoPresent ? 'Configured' : 'Text-only';
        document.getElementById('nexus-accessibility-body-status').textContent = bodyRatio >= 4.5 ? 'Pass' : 'Needs attention';
        document.getElementById('nexus-accessibility-button-status').textContent = buttonRatio >= 4.5 ? 'Pass' : 'Needs attention';
        document.getElementById('nexus-accessibility-brand-status').textContent = logoAltField !== '' || !logoPresent ? 'Pass' : 'Needs alt text';
        document.getElementById('nexus-accessibility-logo-check').textContent = logoAltField !== '' || !logoPresent ? 'Logo alt text present' : 'Add alt text for uploaded logos';
        document.getElementById('nexus-accessibility-heading-check').textContent = heading !== '' && tagline !== '' ? 'Login heading and tagline supplied' : 'Login copy should stay complete';
        document.getElementById('nexus-accessibility-nav-check').textContent = (parseInt(document.getElementById('nexus-sidebar-width').value, 10) <= 280 || document.getElementById('nexus-sidebar-compact').checked) ? 'Navigation labels remain readable at compact widths' : 'Navigation remains spacious';
        var summary = document.getElementById('nexus-accessibility-summary');
        var score = [bodyRatio >= 4.5, buttonRatio >= 4.5, logoAltField !== '' || !logoPresent, heading !== '' && tagline !== ''].filter(Boolean).length;
        summary.textContent = score + '/4 checks';
        summary.classList.toggle('badge-success', score === 4);
        summary.classList.toggle('badge-warning', score < 4);
    }
    document.getElementById('nexus-export').addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-configuration.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    var exportPresets = document.getElementById('nexus-export-presets'); if (exportPresets) exportPresets.addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-presets-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-presets.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    var scheduleForm = document.getElementById('nexus-schedule-form'); scheduleForm.addEventListener('submit', function (event) { var local = document.getElementById('nexus-schedule-local').value; if (!local) return; var date = new Date(local); if (isNaN(date.getTime())) { event.preventDefault(); return; } document.getElementById('nexus-schedule-at').value = date.toISOString(); });
    updateNavigationPreview(); updateLogoPreview(); updateBackgroundPreview();
    updateContrast(); updateAccessibilityReport(); updateResponsivePreview();
    showWorkspaceSection(requestedSection, false);
    function markDraftDirty() { workspace.classList.add('is-dirty'); var freshness = document.getElementById('nexus-preview-freshness'); if (freshness) freshness.innerHTML = '<i class="fas fa-exclamation-circle mr-1 text-warning"></i>Form edits are newer than this exact snapshot. Save draft to regenerate all eight previews.'; var state = document.getElementById('nexus-draft-save-state'); if (state) state.innerHTML = '<i class="fas fa-circle mr-1 text-warning"></i>Unsaved form changes'; }
    document.getElementById('nexus-customizer-form').addEventListener('input', markDraftDirty);
    document.getElementById('nexus-customizer-form').addEventListener('change', markDraftDirty);
    <?php if ($nexusUpdateBusy) { ?>setTimeout(function () { window.location.reload(); }, 3500);<?php } ?>
})();
</script>

<?php require_once '../includes/footer.php'; ?>

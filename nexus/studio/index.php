<?php
/*
 * Nexus Theme Studio 5 — the admin configuration interface.
 *
 * Eight workspace sections: brand, colors, layout, quality, motion, content,
 * operations (presets/schedule/revisions), and system (recovery/tools). Design
 * safely in a private draft, then publish atomically.
 *
 * Requires ITFlow admin authentication. Served at /nexus/studio/.
 */

// Bootstrap ITFlow admin session — chdir so inc_all_admin's relative includes resolve.
chdir($_SERVER['DOCUMENT_ROOT'] . '/admin');
require_once 'includes/inc_all_admin.php';
require_once dirname(__DIR__) . '/lib/engine.php';

// ── Data ────────────────────────────────────────────────────────────────────

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
$nexusLightLogoMeta = nexusThemeAssetMetadata($nexusSettings['branding']['logo_light_path']);
$nexusDarkLogoMeta = nexusThemeAssetMetadata($nexusSettings['branding']['logo_dark_path']);
$nexusFaviconMeta = nexusThemeAssetMetadata($nexusSettings['branding']['favicon_path']);
$nexusBackgroundMeta = nexusThemeAssetMetadata($nexusSettings['branding']['login_background_path']);
$nexusHealth = nexusThemeHealthReport();
$nexusSavedPresets = nexusThemeSavedPresets();
$nexusSavedPresetsJson = nexusThemeExportPresets();
$nexusSchedule = nexusThemeSchedule();
$nexusRevisions = array_reverse(nexusThemeRevisions());
$nexusActiveHash = nexusThemeSettingsHash($nexusActiveSettings);
$nexusDraftChanges = $nexusHasDraft ? nexusThemeSettingsDiff($nexusActiveSettings, $nexusSettings) : [];
$nexusBrandPlaceholder = 'Nexus MSP';
$nexusQualityReport = nexusThemeQualityReport($nexusSettings, 390);
$nexusStatusLabel = $nexusThemeEnabled ? 'Active' : 'Paused';
$nexusStatusClass = $nexusThemeEnabled ? 'success' : 'secondary';
$nexusExportJson = json_encode($nexusSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>

<link rel="stylesheet" href="/nexus/assets/studio.css">

<div class="nexus-manager-page nexus-studio" id="nexus-theme-studio">
    <section class="card border-0 nexus-manager-hero overflow-hidden">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center mb-3">
                        <span class="nexus-manager-mark me-3"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                        <div>
                            <span class="nexus-manager-kicker">Nexus design system</span>
                            <h1 class="h2 mb-0">Theme Studio</h1>
                        </div>
                    </div>
                    <p class="lead mb-4">Design safely in a private draft, then publish every change atomically.</p>
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="badge badge-<?= $nexusStatusClass ?> nexus-manager-status me-2 mb-2"><i class="fas fa-circle me-1" aria-hidden="true"></i><?= $nexusStatusLabel ?></span>
                        <?php if ($nexusHasDraft) { ?><span class="badge text-bg-warning nexus-manager-status me-2 mb-2"><i class="fas fa-pencil-alt me-1" aria-hidden="true"></i>Unpublished draft</span><?php } else { ?><span class="badge text-bg-light nexus-manager-status me-2 mb-2"><i class="fas fa-check me-1" aria-hidden="true"></i>Published</span><?php } ?>
                        <span class="nexus-manager-meta mb-2">Manager <?= NEXUS_MANAGER_VERSION ?> &middot; Theme <?= NEXUS_THEME_VERSION ?></span>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0 text-lg-right">
                    <form action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <?php if ($nexusThemeEnabled) { ?>
                            <button type="submit" name="nexus_theme_state" value="disable" class="btn btn-light btn-lg" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-pause me-2" aria-hidden="true"></i>Pause theme</button>
                        <?php } else { ?>
                            <button type="submit" name="nexus_theme_state" value="enable" class="btn btn-primary btn-lg" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-play me-2" aria-hidden="true"></i>Activate theme</button>
                        <?php } ?>
                    </form>
                    <small class="d-block mt-2 nexus-manager-meta">Pausing affects presentation only. Draft changes remain private until Publish.</small>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$nexusControlWritable) { ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>Theme Studio is read-only because the web service cannot write to the <code>nexus/data</code> directory.</div>
    <?php } ?>

    <section class="card nexus-draft-bar <?= $nexusHasDraft ? 'nexus-draft-bar-pending' : 'nexus-draft-bar-clean' ?>" aria-live="polite">
        <div class="card-body">
            <div class="nexus-draft-state">
                <span class="nexus-draft-state-icon"><i class="fas fa-<?= $nexusHasDraft ? 'pencil-ruler' : 'check' ?>"></i></span>
                <div><span class="nexus-manager-kicker"><?= $nexusHasDraft ? 'Private workspace' : 'Live design' ?></span><strong><?= $nexusHasDraft ? count($nexusDraftChanges) . ' unpublished change' . (count($nexusDraftChanges) === 1 ? '' : 's') : 'Published design is current' ?></strong><small><?= $nexusHasDraft ? 'Visitors still see the published revision until you publish.' : 'Start editing and choose Save draft; nothing goes live until you publish.' ?></small></div>
            </div>
            <?php if ($nexusHasDraft) { ?><div class="nexus-draft-actions"><label class="visually-hidden" for="nexus-revision-name">Revision name</label><input class="form-control" id="nexus-revision-name" name="nexus_revision_name" form="nexus-publish-draft-form" maxlength="120" placeholder="Revision name (optional)"><button type="submit" form="nexus-discard-draft-form" class="btn btn-outline-secondary" onclick="return confirm('Discard every unpublished Nexus change?')">Discard draft</button><button type="submit" form="nexus-publish-draft-form" class="btn btn-success" onclick="return confirm('Publish this draft across every Nexus surface now?')"><i class="fas fa-cloud-upload-alt me-2"></i>Publish draft</button></div><?php } ?>
        </div>
    </section>

    <div class="row nexus-manager-metrics">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-<?= $nexusStatusClass ?>"><i class="fas fa-toggle-on"></i></span><span class="text-muted text-uppercase small fw-bold">Theme</span><div class="h4 mb-0 mt-2"><?= $nexusStatusLabel ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-info"><i class="fas fa-swatchbook"></i></span><span class="text-muted text-uppercase small fw-bold">Palette</span><div class="h4 mb-0 mt-2"><?= ucfirst(escapeHtml($nexusSettings['preset'])) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-indigo"><i class="fas fa-image"></i></span><span class="text-muted text-uppercase small fw-bold">Branding</span><div class="h4 mb-0 mt-2"><?= $nexusLogoUrl !== '' ? 'Custom logo' : 'ITFlow default' ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><span class="nexus-manager-metric-icon bg-<?= $nexusHealth['healthy'] ? 'success' : 'danger' ?>"><i class="fas fa-heartbeat"></i></span><span class="text-muted text-uppercase small fw-bold">Health</span><div class="h4 mb-0 mt-2"><?= $nexusHealth['healthy'] ? 'Healthy' : count($nexusHealth['failed']) . ' failed' ?></div></div></div></div>
    </div>

    <form action="post.php" method="post" enctype="multipart/form-data" id="nexus-customizer-form">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="nexus_theme_save" value="1">
        <input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>">
        <input type="hidden" name="nexus[preset]" id="nexus-preset" value="<?= escapeHtml($nexusSettings['preset']) ?>">

        <div class="nexus-studio-workspace mt-4" data-active-section="brand">
            <aside class="card nexus-workspace-navigation">
                <div class="card-body p-3">
                    <div class="nexus-workspace-navigation-title"><div><span class="nexus-manager-kicker">Theme settings</span><strong>Studio navigation</strong></div><label class="visually-hidden" for="nexus-section-select">Choose a Theme Studio section</label><select class="form-select nexus-section-select" id="nexus-section-select" aria-label="Choose a Theme Studio section"><option value="brand">Branding</option><option value="colors">Colors</option><option value="layout">Layout</option><option value="quality">Design quality</option><option value="motion">Motion</option><option value="content">Content</option><option value="operations">Presets &amp; schedule</option><option value="system">System</option></select></div>
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
                        <a class="nav-link" href="#nexus-system" data-workspace-section="system" data-workspace-title="System" data-workspace-description="Recovery, configuration transfer, and lifecycle tools."><i class="fas fa-shield-alt"></i><span><strong>System</strong><small>Recovery &amp; tools</small></span></a>
                    </nav>
                    <div class="nexus-workspace-navigation-note"><i class="fas fa-pencil-ruler"></i><span>Save freely to the private draft. Live visitors see changes only after <strong>Publish</strong>.</span></div>
                </div>
            </aside>

            <div class="nexus-workspace-content">
                <header class="card nexus-workspace-heading">
                    <div class="card-body"><div><span class="nexus-manager-kicker">Current section</span><h2 class="h4 mb-1" id="nexus-workspace-title">Branding</h2><p class="text-muted mb-0" id="nexus-workspace-description">Logos, identity, browser details, and login artwork.</p></div><div class="nexus-workspace-heading-actions"><button type="button" class="btn btn-sm btn-outline-secondary" id="nexus-section-previous"><i class="fas fa-chevron-left me-1"></i>Previous</button><span class="nexus-workspace-step" id="nexus-workspace-step">1 of 8</span><button type="button" class="btn btn-sm btn-outline-secondary" id="nexus-section-next">Next<i class="fas fa-chevron-right ms-1"></i></button></div></div>
                </header>

                <div class="row nexus-workspace-row">
            <div class="col-xl-7 nexus-workspace-editor-column">
                <section class="card nexus-studio-editor">
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- ── Brand ─────────────────────────────────────────── -->
                            <div class="tab-pane fade show active" id="nexus-brand" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-signature"></i></span><div><h2 class="h5 mb-1">Brand identity</h2><p class="text-muted mb-0">Override ITFlow branding wherever Nexus is active.</p></div></div>
                                <div class="form-row mt-4">
                                    <div class="mb-3 col-md-7"><label for="nexus-brand-name">Display name</label><input class="form-control nexus-preview-input" id="nexus-brand-name" name="nexus[branding][brand_name]" maxlength="80" value="<?= escapeHtml($nexusSettings['branding']['brand_name']) ?>" placeholder="<?= escapeHtml($nexusBrandPlaceholder) ?>" data-preview="brand"><small class="form-text">Leave blank to preserve ITFlow's native company name on live pages.</small></div>
                                    <div class="mb-3 col-md-5"><label for="nexus-logo-alt">Logo alt text</label><input class="form-control" id="nexus-logo-alt" name="nexus[branding][logo_alt]" maxlength="120" value="<?= escapeHtml($nexusSettings['branding']['logo_alt']) ?>" placeholder="<?= escapeHtml($nexusBrandPlaceholder) ?> logo"></div>
                                </div>
                                <div class="form-row"><div class="mb-3 col-md-7"><label for="nexus-tagline">Brand tagline</label><input class="form-control nexus-preview-input" id="nexus-tagline" name="nexus[branding][tagline]" maxlength="140" value="<?= escapeHtml($nexusSettings['branding']['tagline']) ?>" data-preview="tagline"></div><div class="mb-3 col-md-5"><label for="nexus-browser-title">Browser title</label><input class="form-control" id="nexus-browser-title" name="nexus[branding][browser_title]" maxlength="80" value="<?= escapeHtml($nexusSettings['branding']['browser_title']) ?>" placeholder="<?= escapeHtml($nexusBrandPlaceholder) ?>"></div></div>
                                <div class="nexus-logo-dropzone">
                                    <div class="nexus-logo-current nexus-logo-current-dark" id="nexus-logo-preview">
                                        <?php if ($nexusLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusLogoUrl) ?>" alt="Current custom logo"><?php } else { ?><span><i class="fas fa-layer-group"></i></span><?php } ?>
                                    </div>
                                    <div class="flex-grow-1"><label for="nexus-logo" class="mb-1">Light logo <small class="text-muted">for dark surfaces</small></label><input type="file" class="form-control-file nexus-image-input" id="nexus-logo" name="nexus_logo_light" accept="image/png,image/jpeg,image/webp,image/gif" data-preview-target-id="nexus-logo-preview" data-preview-logo="light" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">PNG, JPEG, WebP, or animated GIF · up to 8 MB.</small></div>
                                    <?php if ($nexusSettings['branding']['logo_light_path'] !== '') { ?><button type="submit" form="nexus-remove-light-logo-form" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt me-1"></i>Remove</button><?php } ?>
                                </div>
                                <div class="nexus-logo-dropzone mt-3"><div class="nexus-logo-current" id="nexus-dark-logo-preview"><?php if ($nexusDarkLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusDarkLogoUrl) ?>" alt="Current dark logo"><?php } else { ?><span><i class="fas fa-sun"></i></span><?php } ?></div><div class="flex-grow-1"><label for="nexus-dark-logo" class="mb-1">Dark logo <small class="text-muted">for light surfaces</small></label><input type="file" class="form-control-file nexus-image-input" id="nexus-dark-logo" name="nexus_logo_dark" accept="image/png,image/jpeg,image/webp,image/gif" data-preview-target-id="nexus-dark-logo-preview" data-preview-logo="dark" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">Static images and animated GIFs up to 8 MB.</small></div><?php if ($nexusDarkLogoUrl !== '') { ?><button type="submit" form="nexus-remove-dark-logo-form" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt me-1"></i>Remove</button><?php } ?></div>
                                <div class="form-row mt-3"><div class="mb-3 col-md-7"><div class="d-flex justify-content-between"><label for="nexus-logo-size">Logo size</label><output id="nexus-logo-size-output"><?= (int)$nexusSettings['branding']['logo_size'] ?>%</output></div><input type="range" class="custom-range" id="nexus-logo-size" name="nexus[branding][logo_size]" min="50" max="180" value="<?= (int)$nexusSettings['branding']['logo_size'] ?>"></div><div class="mb-3 col-md-5"><label for="nexus-logo-alignment">Logo alignment</label><select class="form-select" id="nexus-logo-alignment" name="nexus[branding][logo_alignment]"><?php foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['branding']['logo_alignment'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div></div>
                                <div class="form-row"><div class="mb-3 col-md-6"><label for="nexus-favicon">Custom favicon</label><input type="file" class="form-control-file" id="nexus-favicon" name="nexus_favicon" accept="image/png,image/jpeg,image/webp" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">Square images work best. <?php if ($nexusSettings['branding']['favicon_path'] !== '') { ?><button type="submit" form="nexus-remove-favicon-form" class="btn btn-link btn-sm p-0 text-danger">Remove current</button><?php } ?></small></div><div class="mb-3 col-md-6"><label for="nexus-login-background">Login background</label><input type="file" class="form-control-file" id="nexus-login-background" name="nexus_login_background" accept="image/png,image/jpeg,image/webp" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">PNG, JPEG, or WebP · up to 8 MB. <?php if ($nexusSettings['branding']['login_background_path'] !== '') { ?><button type="submit" form="nexus-remove-background-form" class="btn btn-link btn-sm p-0 text-danger">Remove current</button><?php } ?></small></div></div>
                                <div class="form-row"><div class="mb-3 col-md-6"><label for="nexus-background-position">Background focal point</label><select class="form-select" id="nexus-background-position" name="nexus[branding][login_background_position]"><?php foreach (['top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['branding']['login_background_position'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div><div class="mb-3 col-md-6"><div class="d-flex justify-content-between"><label for="nexus-background-overlay">Background overlay</label><output id="nexus-background-overlay-output"><?= (int)$nexusSettings['branding']['login_background_overlay'] ?>%</output></div><input type="range" class="custom-range" id="nexus-background-overlay" name="nexus[branding][login_background_overlay]" min="0" max="90" value="<?= (int)$nexusSettings['branding']['login_background_overlay'] ?>"></div></div>
                                <div class="row mt-3">
                                    <div class="col-md-4"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-show-login-logo" name="nexus[branding][show_login_logo]" value="1" <?= $nexusSettings['branding']['show_login_logo'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-show-login-logo">Authentication pages</label></div></div>
                                    <div class="col-md-4"><div class="custom-control custom-switch"><input type="hidden" name="nexus[branding][show_agent_logo]" value="0"><input type="checkbox" class="custom-control-input" id="nexus-show-agent-logo" name="nexus[branding][show_agent_logo]" value="1" <?= $nexusSettings['branding']['show_agent_logo'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-show-agent-logo">Technician navigation</label></div></div>
                                    <div class="col-md-4"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-show-portal-logo" name="nexus[branding][show_portal_logo]" value="1" <?= $nexusSettings['branding']['show_portal_logo'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-show-portal-logo">Client portal</label></div></div>
                                </div>
                                <hr><div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-crop-alt"></i></span><div><h3 class="h6 mb-1">Advanced asset processing</h3><p class="text-muted mb-0">Optional pixel crop and proportional resize.</p></div></div>
                                <div class="form-row mt-3"><div class="mb-3 col-md-3"><label for="nexus-crop-x">Crop X</label><input class="form-control" type="number" min="0" id="nexus-crop-x" name="nexus_asset[logo][crop][x]" value="0"></div><div class="mb-3 col-md-3"><label for="nexus-crop-y">Crop Y</label><input class="form-control" type="number" min="0" id="nexus-crop-y" name="nexus_asset[logo][crop][y]" value="0"></div><div class="mb-3 col-md-3"><label for="nexus-crop-width">Crop width</label><input class="form-control" type="number" min="0" id="nexus-crop-width" name="nexus_asset[logo][crop][width]" value="0" placeholder="Full"></div><div class="mb-3 col-md-3"><label for="nexus-crop-height">Crop height</label><input class="form-control" type="number" min="0" id="nexus-crop-height" name="nexus_asset[logo][crop][height]" value="0" placeholder="Full"></div></div>
                                <div class="form-row"><div class="mb-3 col-md-6"><label for="nexus-resize-width">Output width</label><input class="form-control" type="number" min="0" max="4000" id="nexus-resize-width" name="nexus_asset[logo][resize_width]" value="0" placeholder="Keep original"></div><div class="mb-3 col-md-6"><label for="nexus-resize-height">Output height</label><input class="form-control" type="number" min="0" max="4000" id="nexus-resize-height" name="nexus_asset[logo][resize_height]" value="0" placeholder="Auto"></div></div>
                                <div class="nexus-asset-health-grid">
                                    <?php foreach (['Light logo' => $nexusLightLogoMeta, 'Dark logo' => $nexusDarkLogoMeta, 'Favicon' => $nexusFaviconMeta, 'Login background' => $nexusBackgroundMeta] as $nexusAssetLabel => $nexusAssetMeta) { ?><div><strong><?= escapeHtml($nexusAssetLabel) ?></strong><?php if (!$nexusAssetMeta['configured']) { ?><small>Not configured</small><?php } elseif (!$nexusAssetMeta['exists']) { ?><small class="text-danger">Missing</small><?php } else { ?><small><?= (int)$nexusAssetMeta['width'] ?>×<?= (int)$nexusAssetMeta['height'] ?> · <?= number_format((int)$nexusAssetMeta['bytes'] / 1024) ?> KB</small><small><?= $nexusAssetMeta['animated'] ? (int)$nexusAssetMeta['frames'] . ' frames · ' . escapeHtml((string)$nexusAssetMeta['fps']) . 'fps' : 'Static' ?> · <?= $nexusAssetMeta['webp'] ? 'WebP ready' : 'Original format' ?></small><?php } ?><?php foreach ($nexusAssetMeta['warnings'] as $nexusAssetWarning) { ?><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i><?= escapeHtml($nexusAssetWarning) ?></small><?php } ?></div><?php } ?>
                                </div>
                                <?php if ($nexusSettings['branding']['favicon_path'] !== '') { ?><div class="mt-3"><strong class="d-block mb-2">Favicon preview</strong><span class="nexus-favicon-preview"><img src="<?= escapeHtml(nexusThemeVersionedAssetUrl($nexusSettings['branding']['favicon_path'], $nexusSettings)) ?>" alt="Favicon preview"><span><?= escapeHtml($nexusSettings['branding']['browser_title'] ?: $nexusBrandPlaceholder) ?></span></span></div><?php } ?>
                            </div>

                            <!-- ── Colors ────────────────────────────────────────── -->
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
                                        <div class="col-md-6"><div class="mb-3 nexus-color-field"><label for="nexus-color-<?= $colorKey ?>"><?= $colorLabel ?></label><div class="input-group"><span class="input-group-text"><input type="color" class="nexus-color-picker" value="<?= $nexusSettings['colors'][$colorKey] ?>" tabindex="-1"></span><input type="text" class="form-control nexus-color-value" id="nexus-color-<?= $colorKey ?>" name="nexus[colors][<?= $colorKey ?>]" value="<?= $nexusSettings['colors'][$colorKey] ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color="<?= $colorKey ?>"></div></div></div>
                                    <?php } ?>
                                </div>
                                <div class="nexus-contrast-report" id="nexus-contrast-report" role="status" aria-live="polite">
                                    <span class="nexus-contrast-icon"><i class="fas fa-universal-access"></i></span>
                                    <div><strong>Accessibility contrast</strong><p class="mb-0 text-muted" id="nexus-contrast-copy">Checking the selected palette…</p></div>
                                </div>
                            </div>

                            <!-- ── Layout ────────────────────────────────────────── -->
                            <div class="tab-pane fade" id="nexus-layout" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-ruler-combined"></i></span><div><h2 class="h5 mb-1">Interface feel</h2><p class="text-muted mb-0">Control visual rhythm without sacrificing responsiveness.</p></div></div>
                                <div class="mb-3 mt-4"><label>Corner style</label><div class="nexus-choice-grid"><?php foreach (['sharp' => 'Sharp', 'balanced' => 'Balanced', 'rounded' => 'Rounded'] as $value => $label) { ?><label class="nexus-choice"><input type="radio" name="nexus[appearance][radius]" value="<?= $value ?>" <?= $nexusSettings['appearance']['radius'] === $value ? 'checked' : '' ?>><span class="nexus-choice-demo nexus-radius-<?= $value ?>"></span><strong><?= $label ?></strong></label><?php } ?></div></div>
                                <div class="mb-3"><label for="nexus-density">Content density</label><select class="form-select nexus-navigation-control" id="nexus-density" name="nexus[appearance][density]"><?php foreach (['compact' => 'Compact — more information on screen', 'comfortable' => 'Comfortable — balanced spacing', 'spacious' => 'Spacious — relaxed and touch friendly'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['density'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div>
                                <div class="form-row"><div class="mb-3 col-md-6"><label for="nexus-menu-density">Menu density</label><select class="form-select nexus-navigation-control" id="nexus-menu-density" name="nexus[appearance][menu_density]"><?php foreach (['compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['menu_density'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div><div class="mb-3 col-md-6"><div class="d-flex justify-content-between"><label for="nexus-sidebar-width">Sidebar width</label><output id="nexus-sidebar-width-output"><?= (int)$nexusSettings['appearance']['sidebar_width'] ?>px</output></div><input type="range" class="custom-range nexus-navigation-control" id="nexus-sidebar-width" name="nexus[appearance][sidebar_width]" min="220" max="340" step="5" value="<?= (int)$nexusSettings['appearance']['sidebar_width'] ?>"></div></div>
                                <div class="form-row"><div class="mb-3 col-md-6"><label for="nexus-header-style">Header treatment</label><select class="form-select nexus-navigation-control" id="nexus-header-style" name="nexus[appearance][header_style]"><?php foreach (['solid' => 'Solid', 'gradient' => 'Gradient', 'glass' => 'Glass'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['header_style'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div><div class="mb-3 col-md-6"><label for="nexus-navigation-style">Active navigation</label><select class="form-select nexus-navigation-control" id="nexus-navigation-style" name="nexus[appearance][navigation_style]"><?php foreach (['pill' => 'Gradient pill', 'rail' => 'Accent rail', 'outline' => 'Outline'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['navigation_style'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div></div>
                                <div class="custom-control custom-switch mb-3"><input type="checkbox" class="custom-control-input nexus-navigation-control" id="nexus-sidebar-compact" name="nexus[appearance][sidebar_compact]" value="1" <?= $nexusSettings['appearance']['sidebar_compact'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-sidebar-compact">Compact sidebar labels and section spacing</label></div>
                                <div class="mb-3"><div class="d-flex justify-content-between"><label for="nexus-font-scale">Interface scale</label><output id="nexus-font-scale-output"><?= (int)$nexusSettings['appearance']['font_scale'] ?>%</output></div><input type="range" class="custom-range" id="nexus-font-scale" name="nexus[appearance][font_scale]" min="90" max="110" step="1" value="<?= (int)$nexusSettings['appearance']['font_scale'] ?>"></div>
                                <hr><div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-object-group"></i></span><div><h3 class="h6 mb-1">Per-surface design profiles</h3><p class="text-muted mb-0">Global settings remain the default. Enable only the surfaces that need independent colors or density.</p></div></div>
                                <div class="accordion mt-3" id="nexus-profile-accordion"><?php foreach (['technician' => 'Technician', 'client' => 'Client portal', 'auth' => 'Login & reset', 'guest' => 'Guest invoice', 'print' => 'Print / PDF'] as $nexusSurface => $nexusSurfaceLabel) { $nexusProfile = $nexusSettings['profiles'][$nexusSurface]; ?><div class="card nexus-profile-card"><div class="card-header"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-profile-<?= $nexusSurface ?>" name="nexus[profiles][<?= $nexusSurface ?>][enabled]" value="1" <?= $nexusProfile['enabled'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-profile-<?= $nexusSurface ?>"><strong><?= escapeHtml($nexusSurfaceLabel) ?></strong> overrides</label></div></div><div class="card-body"><div class="form-row"><?php foreach (['primary' => 'Accent', 'header' => 'Header', 'page' => 'Page', 'surface' => 'Cards', 'text' => 'Text'] as $nexusProfileColor => $nexusProfileColorLabel) { ?><div class="mb-3 col-6 col-md"><label><?= escapeHtml($nexusProfileColorLabel) ?></label><input type="color" class="form-control nexus-profile-color" name="nexus[profiles][<?= $nexusSurface ?>][colors][<?= $nexusProfileColor ?>]" value="<?= escapeHtml($nexusProfile['colors'][$nexusProfileColor] ?? $nexusSettings['colors'][$nexusProfileColor]) ?>"></div><?php } ?></div><div class="mb-3 mb-0"><label>Content density</label><select class="form-select" name="nexus[profiles][<?= $nexusSurface ?>][appearance][density]"><option value="">Use global</option><?php foreach (['compact', 'comfortable', 'spacious'] as $nexusProfileDensity) { ?><option value="<?= $nexusProfileDensity ?>" <?= ($nexusProfile['appearance']['density'] ?? '') === $nexusProfileDensity ? 'selected' : '' ?>><?= ucfirst($nexusProfileDensity) ?></option><?php } ?></select></div></div></div><?php } ?></div>
                                <hr><div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-sitemap"></i></span><div><h3 class="h6 mb-1">Navigation builder</h3><p class="text-muted mb-0">Drag with the arrow buttons, relabel, choose approved icons, and set role visibility separately for desktop and mobile.</p></div></div>
                                <?php foreach (['desktop' => 'Desktop sidebar', 'mobile' => 'Mobile navigation'] as $nexusViewport => $nexusViewportLabel) { ?><div class="mt-3"><strong><?= escapeHtml($nexusViewportLabel) ?></strong><div class="nexus-navigation-builder" data-navigation-viewport="<?= $nexusViewport ?>"><?php foreach ($nexusSettings['navigation'][$nexusViewport] as $nexusNavIndex => $nexusNavItem) { ?><div class="nexus-navigation-row" data-navigation-item><input type="hidden" data-nav-field="id" name="nexus[navigation][<?= $nexusViewport ?>][<?= $nexusNavIndex ?>][id]" value="<?= escapeHtml($nexusNavItem['id']) ?>"><button type="button" class="btn btn-sm btn-default" data-nav-move="up" aria-label="Move up"><i class="fas fa-chevron-up"></i></button><button type="button" class="btn btn-sm btn-default" data-nav-move="down" aria-label="Move down"><i class="fas fa-chevron-down"></i></button><input class="form-control" data-nav-field="label" name="nexus[navigation][<?= $nexusViewport ?>][<?= $nexusNavIndex ?>][label]" maxlength="32" value="<?= escapeHtml($nexusNavItem['label']) ?>" aria-label="Navigation label"><select class="form-select" data-nav-field="icon" name="nexus[navigation][<?= $nexusViewport ?>][<?= $nexusNavIndex ?>][icon]" aria-label="Icon"><?php foreach (['fas fa-tachometer-alt' => 'Dashboard', 'fas fa-users' => 'Users', 'fas fa-ticket-alt' => 'Ticket', 'fas fa-project-diagram' => 'Project', 'fas fa-file-invoice' => 'Invoice', 'fas fa-chart-line' => 'Chart', 'fas fa-home' => 'Home'] as $nexusIcon => $nexusIconLabel) { ?><option value="<?= $nexusIcon ?>" <?= $nexusNavItem['icon'] === $nexusIcon ? 'selected' : '' ?>><?= escapeHtml($nexusIconLabel) ?></option><?php } ?></select><label><input type="checkbox" data-nav-field="roles][]" name="nexus[navigation][<?= $nexusViewport ?>][<?= $nexusNavIndex ?>][roles][]" value="admin" <?= in_array('admin', $nexusNavItem['roles'], true) ? 'checked' : '' ?>> Admin</label><label><input type="checkbox" data-nav-field="roles][]" name="nexus[navigation][<?= $nexusViewport ?>][<?= $nexusNavIndex ?>][roles][]" value="tech" <?= in_array('tech', $nexusNavItem['roles'], true) ? 'checked' : '' ?>> Tech</label><label><input type="checkbox" data-nav-field="visible" name="nexus[navigation][<?= $nexusViewport ?>][<?= $nexusNavIndex ?>][visible]" value="1" <?= $nexusNavItem['visible'] ? 'checked' : '' ?>> Show</label></div><?php } ?></div></div><?php } ?>
                                <hr><div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-moon"></i></span><div><h3 class="h6 mb-1">Automatic dark mode</h3><p class="text-muted mb-0">Follow the operating system, force a mode, or switch on a daily schedule.</p></div></div>
                                <div class="form-row mt-3"><div class="mb-3 col-md-6"><label for="nexus-dark-mode">Default mode</label><select class="form-select" id="nexus-dark-mode" name="nexus[dark_mode][mode]"><?php foreach (['system' => 'System preference', 'light' => 'Always light', 'dark' => 'Always dark', 'scheduled' => 'Scheduled'] as $nexusDarkMode => $nexusDarkLabel) { ?><option value="<?= $nexusDarkMode ?>" <?= $nexusSettings['dark_mode']['mode'] === $nexusDarkMode ? 'selected' : '' ?>><?= escapeHtml($nexusDarkLabel) ?></option><?php } ?></select></div><div class="mb-3 col-md-3"><label>Dark from</label><input class="form-control" type="time" name="nexus[dark_mode][schedule_start]" value="<?= escapeHtml($nexusSettings['dark_mode']['schedule_start']) ?>"></div><div class="mb-3 col-md-3"><label>Light from</label><input class="form-control" type="time" name="nexus[dark_mode][schedule_end]" value="<?= escapeHtml($nexusSettings['dark_mode']['schedule_end']) ?>"></div></div><div class="custom-control custom-switch mb-3"><input type="checkbox" class="custom-control-input" id="nexus-dark-user" name="nexus[dark_mode][user_selectable]" value="1" <?= $nexusSettings['dark_mode']['user_selectable'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-dark-user">Allow each user to select light, dark, or system mode</label></div><div class="form-row"><?php foreach (['primary' => 'Accent', 'sidebar' => 'Sidebar', 'header' => 'Header', 'page' => 'Page', 'surface' => 'Cards', 'text' => 'Text'] as $nexusDarkColor => $nexusDarkColorLabel) { ?><div class="mb-3 col-6 col-md-4"><label><?= escapeHtml($nexusDarkColorLabel) ?></label><input type="color" class="form-control" name="nexus[dark_mode][colors][<?= $nexusDarkColor ?>]" value="<?= escapeHtml($nexusSettings['dark_mode']['colors'][$nexusDarkColor]) ?>"></div><?php } ?></div>
                                <div class="nexus-accessibility-inspector mt-4" id="nexus-accessibility-inspector" aria-live="polite">
                                    <div class="d-flex align-items-start justify-content-between flex-wrap mb-3">
                                        <div>
                                            <span class="nexus-manager-kicker">Accessibility inspector</span>
                                            <h3 class="h6 mb-1">Current draft checks</h3>
                                            <p class="text-muted mb-0">Tracks contrast, reduced-motion, brand text, and logo accessibility before you publish.</p>
                                        </div>
                                        <span class="badge text-bg-light" id="nexus-accessibility-summary">Checking</span>
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

                            <!-- ── Quality ───────────────────────────────────────── -->
                            <div class="tab-pane fade" id="nexus-quality" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-universal-access"></i></span><div><h2 class="h5 mb-1">Accessibility &amp; responsive inspector</h2><p class="text-muted mb-0">Find design risks, jump to the responsible setting, and save conservative corrections into the private draft.</p></div></div>
                                <div class="nexus-quality-score mt-4"><div><span class="nexus-manager-kicker">Draft quality score</span><strong><?= (int)$nexusQualityReport['score'] ?></strong><small>Phone audit at <?= (int)$nexusQualityReport['width'] ?>px</small></div><div class="nexus-quality-counts"><span class="badge text-bg-danger"><?= (int)$nexusQualityReport['counts']['error'] ?> errors</span><span class="badge text-bg-warning"><?= (int)$nexusQualityReport['counts']['warning'] ?> warnings</span><span class="badge text-bg-info"><?= (int)$nexusQualityReport['counts']['info'] ?> notes</span></div></div>
                                <div class="nexus-quality-findings mt-3" id="nexus-quality-findings" aria-live="polite">
                                    <?php if ($nexusQualityReport['findings'] === []) { ?><div class="nexus-empty-state"><i class="fas fa-check-circle"></i><span>No accessibility or responsive risks were detected at this viewport.</span></div><?php } ?>
                                    <?php foreach ($nexusQualityReport['findings'] as $nexusFinding) { ?><article class="nexus-quality-finding nexus-quality-finding-<?= escapeHtml($nexusFinding['severity']) ?>"><span class="nexus-quality-finding-icon"><i class="fas fa-<?= $nexusFinding['severity'] === 'error' ? 'times-circle' : ($nexusFinding['severity'] === 'warning' ? 'exclamation-triangle' : 'info-circle') ?>"></i></span><div><strong><?= escapeHtml($nexusFinding['title']) ?></strong><p><?= escapeHtml($nexusFinding['detail']) ?></p><small><?= ucfirst(escapeHtml($nexusFinding['surface'])) ?> surface</small></div><button type="button" class="btn btn-sm btn-outline-info nexus-finding-target" data-target-section="<?= escapeHtml($nexusFinding['section']) ?>" data-target-control="<?= escapeHtml($nexusFinding['control']) ?>">Open setting</button></article><?php } ?>
                                </div>
                                <div class="nexus-quality-actions mt-3"><div><strong>Safe automatic corrections</strong><small>Only validated colors, scale, spacing, logo text, and reduced-motion settings are changed. The result stays unpublished.</small></div><button type="submit" form="nexus-quality-fix-form" class="btn btn-primary" <?= $nexusControlWritable && $nexusQualityReport['findings'] !== [] ? '' : 'disabled' ?>><i class="fas fa-magic me-2"></i>Apply fixes to draft</button></div>
                            </div>

                            <!-- ── Motion ────────────────────────────────────────── -->
                            <div class="tab-pane fade" id="nexus-motion" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-magic"></i></span><div><h2 class="h5 mb-1">Motion &amp; interaction</h2><p class="text-muted mb-0">Give overlays polish without getting in the user's way.</p></div></div>
                                <div class="nexus-motion-settings mt-4">
                                    <div class="mb-3"><label for="nexus-motion-style">Popup and modal motion</label><div class="input-group"><select class="form-select" id="nexus-motion-style" name="nexus[appearance][motion_style]"><?php foreach (['subtle' => 'Subtle - short, low movement', 'fluid' => 'Fluid - smooth and polished', 'snappy' => 'Snappy - quick with extra energy'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['motion_style'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select><button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#nexus-motion-preview-modal"><i class="fas fa-play me-1"></i>Preview</button></div><small class="form-text">Controls modals, dropdowns, tooltips, alerts, toasts, and floating panels.</small></div>
                                    <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-reduce-motion" name="nexus[appearance][reduce_motion]" value="1" <?= $nexusSettings['appearance']['reduce_motion'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-reduce-motion">Reduce animations and hover motion</label></div>
                                    <div class="nexus-motion-callout"><i class="fas fa-universal-access"></i><div><strong>Accessibility stays in control</strong><p class="mb-0">The visitor's operating-system preference still takes priority over the selected animation profile.</p></div></div>
                                </div>
                            </div>

                            <!-- ── Content ───────────────────────────────────────── -->
                            <div class="tab-pane fade" id="nexus-content" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-comment-alt"></i></span><div><h2 class="h5 mb-1">Portal copy</h2><p class="text-muted mb-0">Make entry points sound like your organization.</p></div></div>
                                <div class="form-row mt-4"><div class="mb-3 col-md-5"><label for="nexus-login-eyebrow">Login eyebrow</label><input class="form-control nexus-preview-input" id="nexus-login-eyebrow" name="nexus[content][login_eyebrow]" maxlength="60" value="<?= escapeHtml($nexusSettings['content']['login_eyebrow']) ?>" data-preview="eyebrow"></div><div class="mb-3 col-md-7"><label for="nexus-login-heading">Login heading</label><input class="form-control nexus-preview-input" id="nexus-login-heading" name="nexus[content][login_heading]" maxlength="80" value="<?= escapeHtml($nexusSettings['content']['login_heading']) ?>" data-preview="heading"></div></div>
                                <div class="mb-3"><label for="nexus-login-message">Login message</label><textarea class="form-control nexus-preview-input" id="nexus-login-message" name="nexus[content][login_message]" rows="3" maxlength="240" data-preview="message"><?= escapeHtml($nexusSettings['content']['login_message']) ?></textarea></div>
                                <hr><div class="mb-3"><label for="nexus-portal-heading">Portal heading</label><input class="form-control" id="nexus-portal-heading" name="nexus[content][portal_heading]" maxlength="80" value="<?= escapeHtml($nexusSettings['content']['portal_heading']) ?>"></div>
                                <div class="mb-3 mb-0"><label for="nexus-portal-message">Portal message</label><textarea class="form-control" id="nexus-portal-message" name="nexus[content][portal_message]" rows="3" maxlength="180"><?= escapeHtml($nexusSettings['content']['portal_message']) ?></textarea></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center">
                        <span class="text-muted small" id="nexus-draft-save-state"><i class="fas fa-lock me-1 text-success"></i>Saved drafts are private and validated</span>
                        <button class="btn btn-primary" type="submit" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-save me-2"></i>Save draft</button>
                    </div>
                </section>
            </div>

            <div class="col-xl-5 mt-4 mt-xl-0 nexus-workspace-side-column">
                <div class="nexus-preview-sticky">

                    <!-- ── Operations panel ──────────────────────────────── -->
                    <section class="card nexus-design-operations nexus-workspace-panel" data-workspace-panel="operations" id="nexus-operations">
                        <div class="card-header border-0"><div><span class="nexus-manager-kicker">Design operations</span><h2 class="h5 mb-0">Drafts, history, presets &amp; schedule</h2></div></div>
                        <div class="card-body pt-0">
                            <section class="nexus-draft-comparison mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2"><div><span class="nexus-manager-kicker">Draft comparison</span><h3 class="h6 mb-0">Unpublished vs. live</h3></div><?php if ($nexusHasDraft) { ?><span class="badge text-bg-warning"><?= count($nexusDraftChanges) ?> changed</span><?php } ?></div>
                                <?php if ($nexusHasDraft) { ?><div class="table-responsive"><table class="table table-sm nexus-diff-table mb-0"><thead><tr><th>Setting</th><th>Published</th><th>Draft</th></tr></thead><tbody><?php foreach (array_slice($nexusDraftChanges, 0, 12) as $nexusChange) { ?><tr><th><?= escapeHtml(str_replace(['branding.', 'content.', 'colors.', 'appearance.', '_'], ['', '', '', '', ' '], $nexusChange['path'])) ?></th><td><?= escapeHtml($nexusChange['before'] === '' ? '—' : $nexusChange['before']) ?></td><td><?= escapeHtml($nexusChange['after'] === '' ? '—' : $nexusChange['after']) ?></td></tr><?php } ?></tbody></table></div><?php if (count($nexusDraftChanges) > 12) { ?><small class="text-muted d-block mt-2">Plus <?= count($nexusDraftChanges) - 12 ?> additional changes.</small><?php } ?><?php } else { ?><div class="nexus-empty-state"><i class="fas fa-check-circle"></i><span>No unpublished differences.</span></div><?php } ?>
                            </section>

                            <section class="nexus-revision-history mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2"><div><span class="nexus-manager-kicker">Revision history</span><h3 class="h6 mb-0">Published designs</h3></div><span class="badge text-bg-light"><?= count($nexusRevisions) ?>/<?= NEXUS_THEME_MAX_REVISIONS ?></span></div>
                                <?php if ($nexusRevisions !== []) { ?><div class="nexus-revision-list"><?php foreach (array_slice($nexusRevisions, 0, 12) as $nexusRevision) { $nexusRevisionIsActive = $nexusRevision['hash'] === $nexusActiveHash; $nexusRevisionDiff = nexusThemeSettingsDiff($nexusActiveSettings, $nexusRevision['settings']); $nexusRevisionChanges = count($nexusRevisionDiff); ?><article><div><strong><?= escapeHtml($nexusRevision['action']) ?><?php if ($nexusRevisionIsActive) { ?> <span class="badge text-bg-success">Live</span><?php } ?><?php if ($nexusRevision['pinned']) { ?> <span class="badge text-bg-primary"><i class="fas fa-thumbtack me-1"></i>Known good</span><?php } ?></strong><small><?= escapeHtml($nexusRevision['actor']) ?> · <?= escapeHtml($nexusRevision['created_at']) ?> · <?= $nexusRevisionChanges ?> difference<?= $nexusRevisionChanges === 1 ? '' : 's' ?></small><code><?= escapeHtml(substr($nexusRevision['hash'], 0, 10)) ?></code><?php if ($nexusRevisionDiff !== []) { ?><details class="nexus-revision-diff"><summary>Compare with live</summary><ul><?php foreach ($nexusRevisionDiff as $nexusRevisionChange) { ?><li><strong><?= escapeHtml(str_replace('_', ' ', $nexusRevisionChange['path'])) ?></strong><span><?= escapeHtml($nexusRevisionChange['before'] === '' ? '—' : $nexusRevisionChange['before']) ?> → <?= escapeHtml($nexusRevisionChange['after'] === '' ? '—' : $nexusRevisionChange['after']) ?></span></li><?php } ?></ul></details><?php } ?></div><div class="nexus-revision-actions"><button type="submit" form="nexus-restore-revision-<?= escapeHtml($nexusRevision['id']) ?>" class="btn btn-sm btn-outline-info" <?= $nexusRevisionIsActive ? 'disabled' : '' ?> onclick="return confirm('Load this published revision into the private draft workspace?')">Restore</button><button type="submit" form="nexus-pin-revision-<?= escapeHtml($nexusRevision['id']) ?>" class="btn btn-sm <?= $nexusRevision['pinned'] ? 'btn-primary' : 'btn-outline-secondary' ?>"><i class="fas fa-thumbtack me-1"></i><?= $nexusRevision['pinned'] ? 'Unpin' : 'Pin' ?></button></div></article><?php } ?></div><?php } else { ?><div class="nexus-empty-state"><i class="fas fa-history"></i><span>The current design and every new publication will appear here after the first publish.</span></div><?php } ?>
                            </section>

                            <hr><label for="nexus-preset-name">Save this design as a preset</label><div class="input-group mb-3"><input class="form-control" id="nexus-preset-name" name="nexus_preset_name" form="nexus-save-preset-form" maxlength="50" placeholder="e.g. Holiday campaign"><button class="btn btn-outline-info" type="submit" form="nexus-save-preset-form" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-bookmark me-1"></i>Save</button></div>
                            <?php if ($nexusSavedPresets !== []) { ?><div class="nexus-saved-preset-list mb-3"><?php foreach ($nexusSavedPresets as $savedPreset) { ?><div><span><i class="fas fa-swatchbook me-2 text-info"></i><?= escapeHtml($savedPreset['name']) ?></span><span><button type="submit" form="nexus-apply-preset-<?= $savedPreset['id'] ?>" class="btn btn-sm btn-link">Apply</button><button type="submit" form="nexus-delete-preset-<?= $savedPreset['id'] ?>" class="btn btn-sm btn-link text-danger" aria-label="Delete <?= escapeHtml($savedPreset['name']) ?>"><i class="fas fa-trash"></i></button></span></div><?php } ?></div><?php } else { ?><p class="small text-muted">No saved presets yet.</p><?php } ?>
                            <div class="btn-group w-100 mb-3"><button type="button" class="btn btn-outline-info" id="nexus-export-presets" <?= $nexusSavedPresets === [] ? 'disabled' : '' ?>><i class="fas fa-download me-1"></i>Export presets</button><button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#nexus-import-presets-modal"><i class="fas fa-upload me-1"></i>Import presets</button></div><textarea class="d-none" id="nexus-export-presets-json"><?= escapeHtml($nexusSavedPresetsJson) ?></textarea>
                            <hr><label for="nexus-schedule-local">Scheduled activation</label><div class="form-row"><div class="col-7"><input type="datetime-local" class="form-control" id="nexus-schedule-local" form="nexus-schedule-form" required></div><div class="col-5"><select class="form-select" name="nexus_schedule_action" form="nexus-schedule-form"><option value="enable">Activate</option><option value="disable">Pause</option></select></div></div><input type="hidden" name="nexus_schedule_at" id="nexus-schedule-at" form="nexus-schedule-form"><div class="d-flex align-items-center justify-content-between mt-2"><small class="text-muted"><?php if ($nexusSchedule !== null) { ?>Next: <?= ucfirst($nexusSchedule['action']) ?> at <?= escapeHtml($nexusSchedule['activate_at']) ?><?php } else { ?>Applies on the first request at or after this time.<?php } ?></small><span><button type="submit" form="nexus-schedule-form" class="btn btn-sm btn-outline-info">Schedule</button><?php if ($nexusSchedule !== null) { ?><button type="submit" form="nexus-cancel-schedule-form" class="btn btn-sm btn-link text-danger">Cancel</button><?php } ?></span></div>
                            <hr><small class="text-muted"><i class="fas fa-shield-alt me-1 text-success"></i>Restoring history always creates a private draft first, so a mistaken rollback never changes the live interface.</small>
                        </div>
                    </section>

                    <!-- ── System panel ───────────────────────────────────── -->
                    <section class="card nexus-workspace-panel" id="nexus-system" data-workspace-panel="system">
                        <div class="card-header border-0"><div><span class="nexus-manager-kicker">Nexus <?= NEXUS_MANAGER_VERSION ?></span><h2 class="h5 mb-0">System</h2></div></div>
                        <div class="card-body pt-0">
                            <div class="nexus-update-versions mb-3">
                                <div><span>Manager</span><strong><?= NEXUS_MANAGER_VERSION ?></strong></div>
                                <div><span>Theme</span><strong><?= NEXUS_THEME_VERSION ?></strong></div>
                            </div>
                            <small class="text-muted d-block mb-3">Nexus 5 is a single <code>nexus/</code> folder with six one-line page hooks. To update, replace the folder contents and run <code>php nexus/cli.php install</code>.</small>
                        </div>
                    </section>

                    <section class="card nexus-workspace-panel nexus-recovery-card" data-workspace-panel="system">
                        <div class="card-header border-0"><div><span class="nexus-manager-kicker">Always-available safety</span><h2 class="h5 mb-0">Recovery mode</h2></div><span class="badge badge-<?= $nexusHealth['healthy'] ? 'success' : 'danger' ?>"><?= $nexusHealth['healthy'] ? 'Healthy' : count($nexusHealth['failed']) . ' failed' ?></span></div>
                        <div class="card-body pt-0"><p class="text-muted">Checks required files, readable settings, and CSS structure. Emergency disable immediately bypasses all Nexus presentation while leaving native administrator access intact.</p><?php if (!$nexusHealth['healthy']) { ?><div class="alert alert-danger"><strong>Detected:</strong> <?= escapeHtml(implode(', ', $nexusHealth['failed'])) ?></div><?php } ?><div class="nexus-system-tool-grid"><button type="submit" form="nexus-recovery-check-form" class="btn btn-outline-info"><i class="fas fa-stethoscope me-1"></i>Run health check</button><button type="submit" form="nexus-recovery-restore-form" class="btn btn-outline-warning" onclick="return confirm('Restore and activate the newest pinned known-good design?')"><i class="fas fa-history me-1"></i>Restore known-good</button><button type="submit" form="nexus-recovery-disable-form" class="btn btn-danger" onclick="return confirm('Emergency-disable every Nexus customization now?')"><i class="fas fa-power-off me-1"></i>Emergency disable</button></div><small class="form-text mt-2">A snapshot is created before restore. Pinned revisions are preferred; otherwise the newest revision is used.</small></div>
                    </section>

                    <section class="card nexus-workspace-panel" data-workspace-panel="system">
                        <div class="card-header border-0"><h2 class="card-title fw-bold"><i class="fas fa-toolbox me-2 text-info"></i>Configuration tools</h2></div>
                        <div class="card-body pt-0"><p class="text-muted">Move designs between Nexus installations, download a sanitized support bundle, or load factory defaults into the private draft.</p><div class="nexus-system-tool-grid"><button type="button" class="btn btn-outline-info" id="nexus-export"><i class="fas fa-download me-1"></i>Export workspace</button><button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#nexus-import-modal"><i class="fas fa-upload me-1"></i>Import to draft</button><button type="submit" form="nexus-diagnostics-form" class="btn btn-outline-info"><i class="fas fa-stethoscope me-1"></i>Diagnostics bundle</button><button type="submit" form="nexus-reset-form" class="btn btn-outline-danger" <?= $nexusControlWritable ? '' : 'disabled' ?> onclick="return confirm('Load every Nexus default into the private draft? The live design will not change.')"><i class="fas fa-undo me-1"></i>Defaults to draft</button></div><small class="form-text mt-2">Diagnostics include versions, compatibility, settings hashes, quality findings, asset health, revision counts — never database credentials or filesystem paths.</small><textarea class="d-none" id="nexus-export-json"><?= escapeHtml((string)$nexusExportJson) ?></textarea></div>
                    </section>
                </div>
            </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Hidden action forms -->
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
    <form action="post.php" method="post" id="nexus-recovery-check-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_recovery_action" value="check"><input type="hidden" name="nexus_return_section" value="system"></form>
    <form action="post.php" method="post" id="nexus-recovery-restore-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_recovery_action" value="restore"><input type="hidden" name="nexus_return_section" value="system"></form>
    <form action="post.php" method="post" id="nexus-recovery-disable-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_recovery_action" value="disable"><input type="hidden" name="nexus_return_section" value="system"></form>
    <?php foreach ($nexusRevisions as $nexusRevision) { ?><form action="post.php" method="post" id="nexus-restore-revision-<?= escapeHtml($nexusRevision['id']) ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_draft_action" value="restore"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"><input type="hidden" name="nexus_revision_id" value="<?= escapeHtml($nexusRevision['id']) ?>"></form><form action="post.php" method="post" id="nexus-pin-revision-<?= escapeHtml($nexusRevision['id']) ?>"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_revision_pin" value="<?= $nexusRevision['pinned'] ? '0' : '1' ?>"><input type="hidden" name="nexus_revision_id" value="<?= escapeHtml($nexusRevision['id']) ?>"></form><?php } ?>
</div>

<!-- ── Modals ──────────────────────────────────────────────────────────── -->

<div class="modal fade" id="nexus-import-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_import" value="1"><input type="hidden" name="nexus_draft_version" value="<?= escapeHtml($nexusDraftVersion) ?>"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-title">Import Nexus configuration</h2><button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a configuration exported from Theme Studio. Uploaded assets stay attached and the imported design remains private until you publish it.</p><textarea class="form-control nexus-json-editor" name="nexus_import_json" rows="16" required spellcheck="false" placeholder='{"schema": 1, ...}'></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload me-2"></i>Import to draft</button></div></form></div></div>

<div class="modal fade" id="nexus-import-presets-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-presets-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_preset_action" value="import"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-presets-title">Import saved presets</h2><button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a Nexus saved-presets export. Imported names are retained and new secure identifiers are generated.</p><textarea class="form-control nexus-json-editor" name="nexus_presets_json" rows="14" required spellcheck="false"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload me-2"></i>Import presets</button></div></form></div></div>

<div class="modal fade" id="nexus-motion-preview-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-motion-preview-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><div class="modal-header"><div><span class="nexus-manager-kicker">Motion preview</span><h2 class="modal-title h5 mb-0" id="nexus-motion-preview-title">A smoother ITFlow experience</h2></div><button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="nexus-motion-preview-icon"><i class="fas fa-magic" aria-hidden="true"></i></div><p class="lead mb-2">This is how windows will arrive.</p><p class="text-muted mb-0">The selected profile also shapes dropdowns, notifications, tooltips, popovers, and floating panels throughout Nexus.</p><div class="alert alert-info mt-4 mb-0"><i class="fas fa-universal-access me-2" aria-hidden="true"></i>Operating-system and Theme Studio reduced-motion preferences always take priority.</div></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Looks good</button></div></div></div></div>

<!-- ── Studio script ──────────────────────────────────────────────────── -->
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
        if (updateLocation && window.history && window.history.replaceState) window.history.replaceState(null, '', selected.getAttribute('href'));
    }

    workspaceLinks.forEach(function (link) { link.addEventListener('click', function (event) { event.preventDefault(); showWorkspaceSection(link.dataset.workspaceSection, true); }); });
    sectionSelect.addEventListener('change', function () { showWorkspaceSection(sectionSelect.value, true); });
    sectionPrevious.addEventListener('click', function () { var index = workspaceLinks.findIndex(function (link) { return link.classList.contains('active'); }); if (index > 0) showWorkspaceSection(workspaceLinks[index - 1].dataset.workspaceSection, true); });
    sectionNext.addEventListener('click', function () { var index = workspaceLinks.findIndex(function (link) { return link.classList.contains('active'); }); if (index >= 0 && index < workspaceLinks.length - 1) showWorkspaceSection(workspaceLinks[index + 1].dataset.workspaceSection, true); });
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
    studio.querySelectorAll('.nexus-finding-target').forEach(function (button) { button.addEventListener('click', function () { showWorkspaceSection(button.dataset.targetSection, true); var control = document.getElementById(button.dataset.targetControl); if (control) { control.focus(); control.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block:'center'}); control.classList.add('nexus-quality-control-highlight'); setTimeout(function () { control.classList.remove('nexus-quality-control-highlight'); }, 1800); } }); });

    var motionStyle = document.getElementById('nexus-motion-style');
    var reduceMotion = document.getElementById('nexus-reduce-motion');
    function updateMotionPreview() { ['subtle', 'fluid', 'snappy'].forEach(function (profile) { document.body.classList.toggle('nexus-motion-' + profile, motionStyle.value === profile); }); document.body.classList.toggle('nexus-motion-reduced', reduceMotion.checked); }
    motionStyle.addEventListener('change', updateMotionPreview);
    reduceMotion.addEventListener('change', updateMotionPreview);

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
        summary.classList.toggle('text-bg-success', score === 4);
        summary.classList.toggle('text-bg-warning', score < 4);
    }

    document.getElementById('nexus-export').addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-configuration.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    var exportPresets = document.getElementById('nexus-export-presets'); if (exportPresets) exportPresets.addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-presets-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-presets.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    var scheduleForm = document.getElementById('nexus-schedule-form'); scheduleForm.addEventListener('submit', function (event) { var local = document.getElementById('nexus-schedule-local').value; if (!local) return; var date = new Date(local); if (isNaN(date.getTime())) { event.preventDefault(); return; } document.getElementById('nexus-schedule-at').value = date.toISOString(); });

    var logoSize = document.getElementById('nexus-logo-size'); var logoAlignment = document.getElementById('nexus-logo-alignment'); function updateLogoPreview() { document.getElementById('nexus-logo-size-output').textContent = logoSize.value + '%'; } logoSize.addEventListener('input', updateLogoPreview);
    var overlay = document.getElementById('nexus-background-overlay'); function updateBackgroundPreview() { document.getElementById('nexus-background-overlay-output').textContent = overlay.value + '%'; } overlay.addEventListener('input', updateBackgroundPreview);
    var scale = document.getElementById('nexus-font-scale');
    scale.addEventListener('input', function () { document.getElementById('nexus-font-scale-output').textContent = scale.value + '%'; });
    function updateNavigationPreview() { var width = document.getElementById('nexus-sidebar-width').value; document.getElementById('nexus-sidebar-width-output').textContent = width + 'px'; }
    studio.querySelectorAll('.nexus-navigation-control').forEach(function (control) { control.addEventListener('input', updateNavigationPreview); control.addEventListener('change', updateNavigationPreview); });

    updateContrast(); updateAccessibilityReport();
    showWorkspaceSection(requestedSection, false);

    function markDraftDirty() { workspace.classList.add('is-dirty'); var state = document.getElementById('nexus-draft-save-state'); if (state) state.innerHTML = '<i class="fas fa-circle me-1 text-warning"></i>Unsaved form changes'; }
    document.getElementById('nexus-customizer-form').addEventListener('input', markDraftDirty);
    document.getElementById('nexus-customizer-form').addEventListener('change', markDraftDirty);

    function reindexNavigation(builder) {
        builder.querySelectorAll('[data-navigation-item]').forEach(function (row, index) {
            row.querySelectorAll('[data-nav-field]').forEach(function (field) {
                var suffix = field.dataset.navField;
                field.name = 'nexus[navigation][' + builder.dataset.navigationViewport + '][' + index + '][' + suffix + (suffix === 'roles][]' ? '' : ']');
            });
        });
    }
    studio.querySelectorAll('[data-navigation-viewport]').forEach(function (builder) {
        builder.addEventListener('click', function (event) {
            var button = event.target.closest('[data-nav-move]'); if (!button) return;
            var row = button.closest('[data-navigation-item]');
            if (button.dataset.navMove === 'up' && row.previousElementSibling) builder.insertBefore(row, row.previousElementSibling);
            if (button.dataset.navMove === 'down' && row.nextElementSibling) builder.insertBefore(row.nextElementSibling, row);
            reindexNavigation(builder);
        });
    });
})();
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/includes/footer.php'; ?>

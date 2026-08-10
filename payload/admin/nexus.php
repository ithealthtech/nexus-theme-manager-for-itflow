<?php

require_once 'includes/inc_all_admin.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

$nexusDocumentRoot = nexusThemeDocumentRoot();
$nexusThemeEnabled = nexusThemeIsEnabled();
$nexusControlWritable = nexusThemeControlIsWritable();
$nexusSettings = nexusThemeSettings();
$nexusDefaults = nexusThemeDefaults();
$nexusPresets = nexusThemePresets();
$nexusLogoUrl = nexusThemeLogoUrl($nexusSettings);
$nexusBrandName = nexusThemeBrandName($session_company_name, $nexusSettings);
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

        <div class="row mt-4">
            <div class="col-xl-7">
                <section class="card nexus-studio-editor">
                    <div class="card-header border-0 pb-0">
                        <ul class="nav nav-pills nexus-studio-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#nexus-brand" role="tab"><i class="fas fa-fingerprint mr-2"></i>Brand</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#nexus-colors" role="tab"><i class="fas fa-palette mr-2"></i>Colors</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#nexus-layout" role="tab"><i class="fas fa-sliders-h mr-2"></i>Layout</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#nexus-content" role="tab"><i class="fas fa-pen-nib mr-2"></i>Content</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="nexus-brand" role="tabpanel">
                                <div class="nexus-section-heading"><span class="nexus-section-icon"><i class="fas fa-signature"></i></span><div><h2 class="h5 mb-1">Brand identity</h2><p class="text-muted mb-0">Override ITFlow branding wherever Nexus is active.</p></div></div>
                                <div class="form-row mt-4">
                                    <div class="form-group col-md-7"><label for="nexus-brand-name">Display name</label><input class="form-control nexus-preview-input" id="nexus-brand-name" name="nexus[branding][brand_name]" maxlength="80" value="<?= escapeHtml($nexusSettings['branding']['brand_name']) ?>" placeholder="<?= escapeHtml($session_company_name) ?>" data-preview="brand"><small class="form-text">Leave blank to inherit the ITFlow company name.</small></div>
                                    <div class="form-group col-md-5"><label for="nexus-logo-alt">Logo alt text</label><input class="form-control" id="nexus-logo-alt" name="nexus[branding][logo_alt]" maxlength="120" value="<?= escapeHtml($nexusSettings['branding']['logo_alt']) ?>" placeholder="<?= escapeHtml($nexusBrandName) ?> logo"></div>
                                </div>
                                <div class="form-group"><label for="nexus-tagline">Brand tagline</label><input class="form-control nexus-preview-input" id="nexus-tagline" name="nexus[branding][tagline]" maxlength="140" value="<?= escapeHtml($nexusSettings['branding']['tagline']) ?>" data-preview="tagline"></div>
                                <div class="nexus-logo-dropzone">
                                    <div class="nexus-logo-current" id="nexus-logo-preview">
                                        <?php if ($nexusLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusLogoUrl) ?>?v=<?= @filemtime($nexusDocumentRoot . $nexusLogoUrl) ?: 1 ?>" alt="Current custom logo"><?php } else { ?><span><i class="fas fa-layer-group"></i></span><?php } ?>
                                    </div>
                                    <div class="flex-grow-1"><label for="nexus-logo" class="mb-1">Custom logo</label><input type="file" class="form-control-file" id="nexus-logo" name="nexus_logo" accept="image/png,image/jpeg,image/webp" <?= $nexusControlWritable ? '' : 'disabled' ?>><small class="form-text">PNG, JPEG, or WebP · up to 3 MB · maximum 4000×2000.</small></div>
                                    <?php if ($nexusLogoUrl !== '') { ?><button type="submit" form="nexus-remove-logo-form" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-alt mr-1"></i>Remove</button><?php } ?>
                                </div>
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
                                    <?php $nexusColorLabels = ['primary' => 'Primary accent', 'secondary' => 'Gradient accent', 'sidebar' => 'Navigation', 'auth_background' => 'Login background', 'page' => 'Page background', 'surface' => 'Cards & surfaces', 'text' => 'Primary text']; ?>
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
                                <div class="form-group"><label for="nexus-density">Content density</label><select class="custom-select" id="nexus-density" name="nexus[appearance][density]"><?php foreach (['compact' => 'Compact — more information on screen', 'comfortable' => 'Comfortable — balanced spacing', 'spacious' => 'Spacious — relaxed and touch friendly'] as $value => $label) { ?><option value="<?= $value ?>" <?= $nexusSettings['appearance']['density'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div>
                                <div class="form-group"><div class="d-flex justify-content-between"><label for="nexus-font-scale">Interface scale</label><output id="nexus-font-scale-output"><?= (int)$nexusSettings['appearance']['font_scale'] ?>%</output></div><input type="range" class="custom-range" id="nexus-font-scale" name="nexus[appearance][font_scale]" min="90" max="110" step="1" value="<?= (int)$nexusSettings['appearance']['font_scale'] ?>"></div>
                                <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nexus-reduce-motion" name="nexus[appearance][reduce_motion]" value="1" <?= $nexusSettings['appearance']['reduce_motion'] ? 'checked' : '' ?>><label class="custom-control-label" for="nexus-reduce-motion">Reduce animations and hover motion</label></div>
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

            <div class="col-xl-5 mt-4 mt-xl-0">
                <div class="nexus-preview-sticky">
                    <section class="card nexus-preview-card">
                        <div class="card-header border-0 d-flex align-items-center justify-content-between"><div><span class="nexus-manager-kicker">Live preview</span><h2 class="h5 mb-0">Authentication</h2></div><span class="badge badge-light"><i class="fas fa-bolt mr-1 text-warning"></i>Instant</span></div>
                        <div class="card-body p-0">
                            <div class="nexus-live-preview" id="nexus-live-preview" style="--preview-primary:<?= $nexusSettings['colors']['primary'] ?>;--preview-secondary:<?= $nexusSettings['colors']['secondary'] ?>;--preview-sidebar:<?= $nexusSettings['colors']['sidebar'] ?>;--preview-auth:<?= $nexusSettings['colors']['auth_background'] ?>;--preview-surface:<?= $nexusSettings['colors']['surface'] ?>;--preview-text:<?= $nexusSettings['colors']['text'] ?>;">
                                <div class="nexus-preview-windowbar"><i></i><i></i><i></i></div>
                                <div class="nexus-preview-canvas">
                                    <div class="nexus-preview-brand">
                                        <?php if ($nexusLogoUrl !== '') { ?><img src="<?= escapeHtml($nexusLogoUrl) ?>?v=<?= @filemtime($nexusDocumentRoot . $nexusLogoUrl) ?: 1 ?>" alt="Preview logo"><?php } else { ?><span class="nexus-preview-symbol"><i class="fas fa-layer-group"></i></span><?php } ?>
                                        <strong data-preview-target="brand"><?= escapeHtml($nexusBrandName) ?></strong>
                                    </div>
                                    <div class="nexus-preview-login-card"><span class="nexus-preview-eyebrow" data-preview-target="eyebrow"><?= escapeHtml($nexusSettings['content']['login_eyebrow']) ?></span><h3 data-preview-target="heading"><?= escapeHtml($nexusSettings['content']['login_heading']) ?></h3><p data-preview-target="message"><?= escapeHtml($nexusSettings['content']['login_message']) ?></p><span class="nexus-preview-label">Email address</span><div class="nexus-preview-field"></div><span class="nexus-preview-label">Password</span><div class="nexus-preview-field"></div><button type="button" tabindex="-1">Sign in</button></div>
                                    <small data-preview-target="tagline"><?= escapeHtml($nexusSettings['branding']['tagline']) ?></small>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card nexus-update-card">
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

                    <section class="card">
                        <div class="card-header border-0"><h2 class="card-title font-weight-bold"><i class="fas fa-toolbox mr-2 text-info"></i>Configuration tools</h2></div>
                        <div class="card-body pt-0"><p class="text-muted">Move designs between Nexus installations or return to the factory palette.</p><div class="btn-group w-100"><button type="button" class="btn btn-outline-info" id="nexus-export"><i class="fas fa-download mr-1"></i>Export</button><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#nexus-import-modal"><i class="fas fa-upload mr-1"></i>Import</button><button type="submit" form="nexus-reset-form" class="btn btn-outline-danger" <?= $nexusControlWritable ? '' : 'disabled' ?> onclick="return confirm('Restore every Nexus customization to its default value?')"><i class="fas fa-undo mr-1"></i>Reset</button></div><textarea class="d-none" id="nexus-export-json"><?= escapeHtml((string)$nexusExportJson) ?></textarea></div>
                    </section>
                </div>
            </div>
        </div>
    </form>

    <form action="post.php" method="post" id="nexus-remove-logo-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_remove_logo" value="1"></form>
    <form action="post.php" method="post" id="nexus-reset-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_reset" value="1"></form>
    <form action="post.php" method="post" id="nexus-update-check-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_update_action" value="check"></form>
    <form action="post.php" method="post" id="nexus-update-install-form"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_update_action" value="update"></form>
</div>

<div class="modal fade" id="nexus-import-modal" tabindex="-1" role="dialog" aria-labelledby="nexus-import-title" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><form action="post.php" method="post" class="modal-content"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="nexus_theme_import" value="1"><div class="modal-header"><h2 class="modal-title h5" id="nexus-import-title">Import Nexus configuration</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="text-muted">Paste a configuration exported from Theme Studio. Your current uploaded logo is kept.</p><textarea class="form-control nexus-json-editor" name="nexus_import_json" rows="16" required spellcheck="false" placeholder='{"schema": 1, ...}'></textarea></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" <?= $nexusControlWritable ? '' : 'disabled' ?>><i class="fas fa-upload mr-2"></i>Import and apply</button></div></form></div></div>

<script>
(function () {
    'use strict';
    var studio = document.getElementById('nexus-theme-studio');
    if (!studio) return;
    var preview = document.getElementById('nexus-live-preview');
    var presetField = document.getElementById('nexus-preset');
    var colorVariables = {primary:'--preview-primary', secondary:'--preview-secondary', sidebar:'--preview-sidebar', auth_background:'--preview-auth', surface:'--preview-surface', text:'--preview-text'};

    function setPreviewText(key, value) {
        var target = studio.querySelector('[data-preview-target="' + key + '"]');
        if (target) target.textContent = value || (key === 'brand' ? <?= json_encode($session_company_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> : '');
    }
    function rgb(hex) { return [1, 3, 5].map(function (offset) { var value = parseInt(hex.slice(offset, offset + 2), 16) / 255; return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4); }); }
    function contrast(first, second) { var a = rgb(first), b = rgb(second); var one = 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2]; var two = 0.2126 * b[0] + 0.7152 * b[1] + 0.0722 * b[2]; return (Math.max(one, two) + 0.05) / (Math.min(one, two) + 0.05); }
    function contrastInk(hex) { var channels = [1, 3, 5].map(function (offset) { return parseInt(hex.slice(offset, offset + 2), 16); }); return ((channels[0] * 299 + channels[1] * 587 + channels[2] * 114) / 1000) >= 145 ? '#0b0a17' : '#ffffff'; }
    function updateContrast() { var primary = studio.querySelector('[data-color="primary"]').value; var text = studio.querySelector('[data-color="text"]').value; var surface = studio.querySelector('[data-color="surface"]').value; if (![primary, text, surface].every(function (value) { return /^#[0-9a-f]{6}$/i.test(value); })) return; var bodyRatio = contrast(text, surface); var buttonRatio = contrast(primary, contrastInk(primary)); var pass = bodyRatio >= 4.5 && buttonRatio >= 4.5; var report = document.getElementById('nexus-contrast-report'); report.classList.toggle('nexus-contrast-pass', pass); report.classList.toggle('nexus-contrast-warning', !pass); document.getElementById('nexus-contrast-copy').textContent = (pass ? 'Pass' : 'Needs attention') + ' — text ' + bodyRatio.toFixed(2) + ':1 · primary button ' + buttonRatio.toFixed(2) + ':1'; }
    studio.querySelectorAll('.nexus-preview-input').forEach(function (input) { input.addEventListener('input', function () { setPreviewText(input.dataset.preview, input.value); }); });
    studio.querySelectorAll('.nexus-color-field').forEach(function (field) {
        var picker = field.querySelector('.nexus-color-picker');
        var text = field.querySelector('.nexus-color-value');
        function apply(value) { if (/^#[0-9a-f]{6}$/i.test(value)) { picker.value = value; preview.style.setProperty(colorVariables[text.dataset.color], value); presetField.value = 'custom'; studio.querySelectorAll('.nexus-preset').forEach(function (item) { item.classList.remove('active'); }); updateContrast(); } }
        picker.addEventListener('input', function () { text.value = picker.value; apply(picker.value); });
        text.addEventListener('input', function () { apply(text.value); });
    });
    studio.querySelectorAll('.nexus-preset').forEach(function (button) { button.addEventListener('click', function () { var colors = JSON.parse(button.dataset.colors); Object.keys(colors).forEach(function (key) { var input = studio.querySelector('[data-color="' + key + '"]'); if (input) { input.value = colors[key]; input.closest('.nexus-color-field').querySelector('.nexus-color-picker').value = colors[key]; preview.style.setProperty(colorVariables[key], colors[key]); } }); presetField.value = button.dataset.preset; studio.querySelectorAll('.nexus-preset').forEach(function (item) { item.classList.toggle('active', item === button); }); updateContrast(); }); });
    var logoInput = document.getElementById('nexus-logo');
    if (logoInput) logoInput.addEventListener('change', function () { var file = logoInput.files && logoInput.files[0]; if (!file || !/^image\/(png|jpeg|webp)$/.test(file.type)) return; var reader = new FileReader(); reader.onload = function (event) { document.getElementById('nexus-logo-preview').innerHTML = '<img src="' + event.target.result + '" alt="Selected logo preview">'; var brand = studio.querySelector('.nexus-preview-brand'); var old = brand.querySelector('img,.nexus-preview-symbol'); if (old) old.remove(); var image = document.createElement('img'); image.src = event.target.result; image.alt = 'Selected logo preview'; brand.insertBefore(image, brand.firstChild); }; reader.readAsDataURL(file); });
    var scale = document.getElementById('nexus-font-scale');
    scale.addEventListener('input', function () { document.getElementById('nexus-font-scale-output').textContent = scale.value + '%'; preview.style.fontSize = scale.value + '%'; });
    document.getElementById('nexus-export').addEventListener('click', function () { var blob = new Blob([document.getElementById('nexus-export-json').value], {type:'application/json'}); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'nexus-theme-configuration.json'; document.body.appendChild(link); link.click(); link.remove(); setTimeout(function () { URL.revokeObjectURL(link.href); }, 0); });
    updateContrast();
    <?php if ($nexusUpdateBusy) { ?>setTimeout(function () { window.location.reload(); }, 3500);<?php } ?>
})();
</script>

<?php require_once '../includes/footer.php'; ?>

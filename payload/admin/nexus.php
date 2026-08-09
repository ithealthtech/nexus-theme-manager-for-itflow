<?php

require_once 'includes/inc_all_admin.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

$nexusDocumentRoot = nexusThemeDocumentRoot();
$nexusThemeEnabled = nexusThemeIsEnabled();
$nexusControlWritable = nexusThemeControlIsWritable();
$nexusManagedAssets = [
    'Theme stylesheet' => '/css/nexus-theme.css',
    'Theme control' => '/includes/nexus_theme.php',
    'Administration page' => '/admin/nexus.php',
    'Administration action handler' => '/admin/post/nexus.php',
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
$nexusPackagePath = '/opt/Nexus-Theme-Manager-for-ITFlow-' . NEXUS_MANAGER_VERSION;

?>

<div class="nexus-manager-page">
    <section class="card border-0 nexus-manager-hero overflow-hidden">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center mb-3">
                        <span class="nexus-manager-mark mr-3"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                        <div>
                            <span class="nexus-manager-kicker">Nexus for ITFlow</span>
                            <h1 class="h2 mb-0">Theme Manager</h1>
                        </div>
                    </div>
                    <p class="lead mb-4">Control the Nexus experience, confirm package health, and keep lifecycle operations visible from one administrative workspace.</p>
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="badge badge-<?= $nexusStatusClass ?> nexus-manager-status mr-2 mb-2">
                            <i class="fas fa-circle mr-1" aria-hidden="true"></i><?= $nexusStatusLabel ?>
                        </span>
                        <span class="nexus-manager-meta mb-2">Manager <?= NEXUS_MANAGER_VERSION ?> &middot; Theme <?= NEXUS_THEME_VERSION ?></span>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0 text-lg-right">
                    <form action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <?php if ($nexusThemeEnabled) { ?>
                            <button type="submit" name="nexus_theme_state" value="disable" class="btn btn-light btn-lg" <?= $nexusControlWritable ? '' : 'disabled' ?>>
                                <i class="fas fa-pause mr-2" aria-hidden="true"></i>Pause Nexus theme
                            </button>
                        <?php } else { ?>
                            <button type="submit" name="nexus_theme_state" value="enable" class="btn btn-primary btn-lg" <?= $nexusControlWritable ? '' : 'disabled' ?>>
                                <i class="fas fa-play mr-2" aria-hidden="true"></i>Activate Nexus theme
                            </button>
                        <?php } ?>
                    </form>
                    <small class="d-block mt-2 nexus-manager-meta">Applies to login, technician, admin, and client surfaces.</small>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$nexusControlWritable) { ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i>
            Theme controls are read-only because the web service cannot write to the ITFlow <code>uploads</code> directory.
        </div>
    <?php } ?>

    <div class="row nexus-manager-metrics">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <span class="nexus-manager-metric-icon bg-<?= $nexusStatusClass ?>"><i class="fas fa-toggle-on" aria-hidden="true"></i></span>
                    <span class="text-muted text-uppercase small font-weight-bold">Theme state</span>
                    <div class="h4 mb-0 mt-2"><?= $nexusStatusLabel ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <span class="nexus-manager-metric-icon bg-info"><i class="fas fa-cube" aria-hidden="true"></i></span>
                    <span class="text-muted text-uppercase small font-weight-bold">Manager</span>
                    <div class="h4 mb-0 mt-2"><?= NEXUS_MANAGER_VERSION ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <span class="nexus-manager-metric-icon bg-indigo"><i class="fas fa-palette" aria-hidden="true"></i></span>
                    <span class="text-muted text-uppercase small font-weight-bold">Theme payload</span>
                    <div class="h4 mb-0 mt-2"><?= NEXUS_THEME_VERSION ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <span class="nexus-manager-metric-icon bg-<?= $nexusAssetsHealthy ? 'success' : 'danger' ?>"><i class="fas fa-heartbeat" aria-hidden="true"></i></span>
                    <span class="text-muted text-uppercase small font-weight-bold">Core assets</span>
                    <div class="h4 mb-0 mt-2"><?= $nexusPresentAssets ?>/<?= count($nexusManagedAssets) ?> present</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-7">
            <section class="card h-100">
                <div class="card-header border-0">
                    <h2 class="card-title font-weight-bold"><i class="fas fa-sliders-h mr-2 text-info" aria-hidden="true"></i>Experience controls</h2>
                </div>
                <div class="card-body pt-0">
                    <div class="nexus-manager-control-row">
                        <div>
                            <h3 class="h5 mb-1">Nexus interface</h3>
                            <p class="text-muted mb-0">Enable the full Nexus visual layer across every managed ITFlow surface.</p>
                        </div>
                        <span class="badge badge-<?= $nexusStatusClass ?> px-3 py-2"><?= $nexusStatusLabel ?></span>
                    </div>
                    <hr>
                    <div class="nexus-manager-control-row">
                        <div>
                            <h3 class="h5 mb-1">Compatibility baseline</h3>
                            <p class="text-muted mb-0">ITFlow 26.08 at the exact verified commit.</p>
                        </div>
                        <code title="<?= NEXUS_ITFLOW_COMMIT ?>"><?= substr(NEXUS_ITFLOW_COMMIT, 0, 8) ?></code>
                    </div>
                    <hr>
                    <div class="nexus-manager-control-row">
                        <div>
                            <h3 class="h5 mb-1">Package assets</h3>
                            <p class="text-muted mb-0">The control panel, action handler, navigation, helper, and stylesheet are present.</p>
                        </div>
                        <span class="text-<?= $nexusAssetsHealthy ? 'success' : 'danger' ?> font-weight-bold">
                            <i class="fas fa-<?= $nexusAssetsHealthy ? 'check-circle' : 'exclamation-circle' ?> mr-1" aria-hidden="true"></i>
                            <?= $nexusAssetsHealthy ? 'Healthy' : 'Attention needed' ?>
                        </span>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-xl-5 mt-4 mt-xl-0">
            <section class="card h-100">
                <div class="card-header border-0">
                    <h2 class="card-title font-weight-bold"><i class="fas fa-shield-alt mr-2 text-info" aria-hidden="true"></i>Lifecycle safety</h2>
                </div>
                <div class="card-body pt-0">
                    <p>Theme activation is available here. Package-level operations remain intentionally protected by the root-only manager so backups, conflict checks, and rollback cannot be bypassed by the web service.</p>
                    <div class="nexus-manager-command">
                        <span>Verify package</span>
                        <code>sudo php <?= escapeHtml($nexusPackagePath) ?>/manager.php verify --root <?= escapeHtml($nexusDocumentRoot) ?></code>
                    </div>
                    <div class="nexus-manager-command">
                        <span>Remove package safely</span>
                        <code>sudo php <?= escapeHtml($nexusPackagePath) ?>/manager.php uninstall --root <?= escapeHtml($nexusDocumentRoot) ?> --yes</code>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="https://github.com/ithealthtech/nexus-theme-manager-for-itflow/releases/latest" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-github mr-2" aria-hidden="true"></i>View latest release
                    </a>
                </div>
            </section>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

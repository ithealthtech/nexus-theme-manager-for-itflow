<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
nexusThemeApplyDueSchedule();
$nexus_guest_enabled = nexusThemeIsEnabled();
$nexus_guest_settings = nexusThemeSettings();
$nexus_guest_brand = nexusThemeBrandName($session_company_name, $nexus_guest_settings);
$nexus_guest_logo = nexusThemeVersionedAssetUrl(
    nexusThemeLogoUrl($nexus_guest_settings, '', nexusThemeLogoVariantForColor($nexus_guest_settings['colors']['sidebar'])),
    $nexus_guest_settings
);
$nexus_guest_native_favicon = is_file($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico') ? '/uploads/favicon.ico' : '';
$nexus_guest_favicon = $nexus_guest_enabled
    ? nexusThemeVersionedAssetUrl(nexusThemeFaviconUrl($nexus_guest_settings, $nexus_guest_native_favicon), $nexus_guest_settings)
    : $nexus_guest_native_favicon;
$nexus_guest_is_invoice = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'guest_view_invoice.php';
if ($nexus_guest_enabled) {
    $tab_title = nexusThemePageTitle($session_company_name, '', $nexus_guest_settings);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?= escapeHtml($nexus_guest_enabled ? nexusThemePageTitle($session_company_name, $nexus_guest_is_invoice ? 'Invoice' : 'Guest Portal', $nexus_guest_settings) : $session_company_name) ?></title>

    <?php if ($nexus_guest_favicon !== '') { ?>
        <link rel="icon" href="<?= escapeHtml($nexus_guest_favicon) ?>">
    <?php } ?>

    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/libs/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link rel="stylesheet" href="/libs/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libs/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="/libs/daterangepicker/daterangepicker.css">
    <?php if ($nexus_guest_enabled) { ?>
        <link rel="stylesheet" href="/css/nexus-theme.css">
        <link rel="stylesheet" href="/css/nexus-theme-custom.php?v=<?= nexusThemeSettingsVersion() ?>">
    <?php } ?>

    <script src="/libs/jquery/jquery.min.js"></script>
    <script src="/libs/toastr/toastr.min.js"></script>

</head>
<body class="layout-top-nav <?= $nexus_guest_enabled ? 'nexus-theme nexus-guest ' . ($nexus_guest_is_invoice ? 'nexus-guest-invoice ' : '') . nexusThemeBodyClasses($nexus_guest_settings) : '' ?>">
    <div class="wrapper text-sm">
        <?php if ($nexus_guest_enabled) { ?>
            <header class="nexus-guest-masthead d-print-none">
                <div class="container nexus-guest-masthead-inner">
                    <a class="nexus-guest-brand" href="/login.php" aria-label="<?= escapeHtml($nexus_guest_brand) ?> support portal">
                        <?php if ($nexus_guest_logo !== '') { ?>
                            <img src="<?= escapeHtml($nexus_guest_logo) ?>" alt="<?= escapeHtml($nexus_guest_settings['branding']['logo_alt'] ?: $nexus_guest_brand . ' logo') ?>">
                        <?php } else { ?>
                            <span class="nexus-preview-symbol"><i class="fas fa-layer-group" aria-hidden="true"></i></span>
                            <strong><?= escapeHtml($nexus_guest_brand) ?></strong>
                        <?php } ?>
                    </a>
                    <div class="nexus-guest-heading">
                        <span>Secure billing portal</span>
                        <strong><?= $nexus_guest_is_invoice ? 'Invoice details' : 'Shared workspace' ?></strong>
                    </div>
                    <?php if ($nexus_guest_settings['branding']['tagline'] !== '') { ?>
                        <p class="nexus-guest-tagline"><?= escapeHtml($nexus_guest_settings['branding']['tagline']) ?></p>
                    <?php } ?>
                </div>
            </header>
        <?php } ?>

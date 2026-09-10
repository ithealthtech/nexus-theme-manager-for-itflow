<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
nexusThemeApplyDueSchedule();
$nexus_guest_enabled = nexusThemeRuntimeEnabled();
$nexus_guest_settings = nexusThemeSettings();
$nexus_guest_is_invoice = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'guest_view_invoice.php';
$nexus_guest_presentation = nexusThemePresentationModel($nexus_guest_settings, $session_company_name, $nexus_guest_is_invoice ? 'guest' : 'client');
$nexus_guest_brand = $nexus_guest_presentation['brand'];
$nexus_guest_logo_raw = nexusThemeLogoUrl($nexus_guest_settings, '', nexusThemeLogoVariantForColor($nexus_guest_settings['colors']['sidebar']));
$nexus_guest_print_logo_raw = nexusThemeLogoUrl($nexus_guest_settings, '', 'dark');
$nexus_guest_logo = nexusThemeVersionedAssetUrl($nexus_guest_logo_raw, $nexus_guest_settings);
$nexus_guest_print_logo = nexusThemeVersionedAssetUrl($nexus_guest_print_logo_raw, $nexus_guest_settings);
$nexus_guest_native_favicon = is_file($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico') ? '/uploads/favicon.ico' : '';
$nexus_guest_favicon = $nexus_guest_enabled
    ? nexusThemeVersionedAssetUrl(nexusThemeFaviconUrl($nexus_guest_settings, $nexus_guest_native_favicon), $nexus_guest_settings)
    : $nexus_guest_native_favicon;
$nexus_guest_dark = $nexus_guest_enabled && nexusThemeInitialDarkMode($nexus_guest_settings, false);
?>
<!DOCTYPE html>
<html lang="en" data-lte-print="plain"<?php if ($nexus_guest_enabled) { ?> data-bs-theme="<?= $nexus_guest_dark ? 'dark' : 'light' ?>" data-lte-color-mode="off"<?php } ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">
    <?php if ($nexus_guest_enabled) { ?><meta name="color-scheme" content="<?= $nexus_guest_dark ? 'dark' : 'light' ?>"><?php } ?>

    <title><?= escapeHtml($nexus_guest_enabled ? nexusThemePageTitle($session_company_name, $nexus_guest_is_invoice ? 'Invoice' : 'Guest Portal', $nexus_guest_settings) : $session_company_name) ?></title>

    <!--
    Favicon
    If Fav Icon exists else use the default one
    -->
    <?php if ($nexus_guest_favicon !== '') { ?>
        <link rel="icon" href="<?= escapeHtml($nexus_guest_favicon) ?>">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <?php /* Opt-in AdminLTE 3 palette, new in v4.5.0. Supplies the
             --bs-<colour> tokens plus .text-bg-* / .card-* / .callout-*
             / .bg-gradient-* families. Loads BEFORE itflow_custom.css so
             our own .bg-<colour> box colours still win. */ ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-colors-v3.min.css">

    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="/libs/flatpickr/css/flatpickr.min.css">
    <link rel="stylesheet" href="/libs/tom-select/css/tom-select.bootstrap5.min.css">
    <?php /* Required: includes/footer.php loads sweetalert2.min.js for the
             confirm-link dialogs used by guest_approve_ticket_task.php and
             guest_view_quote.php. Every bit of the dialog's positioning
             (position:fixed, inset:0, the centering grid) lives in this
             stylesheet, so without it the popup renders as a static block at
             the foot of the document. */ ?>
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">

    <!-- ITFlow style: loaded last so it wins. See includes/header.php -->
    <link rel="stylesheet" href="/css/itflow_custom.css">

    <?php if ($nexus_guest_enabled) { ?>
        <!-- Nexus loads after itflow_custom.css so the managed theme wins -->
        <link rel="stylesheet" href="/css/nexus-theme.css?v=<?= escapeHtml(NEXUS_THEME_VERSION) ?>">
        <link rel="stylesheet" href="/css/nexus-theme-custom.php?v=<?= nexusThemeSettingsVersion() ?>">
    <?php } ?>

    <!-- Scripts -->
    <?php if ($nexus_guest_enabled) { ?><script><?= nexusThemeColorModeScript($nexus_guest_settings) ?></script><?php } ?>

</head>
<body class="layout-fixed bg-body-tertiary theme-<?= escapeHtml($config_theme) ?> <?= $nexus_guest_enabled ? 'nexus-theme nexus-guest ' . ($nexus_guest_is_invoice ? 'nexus-guest-invoice ' : '') . $nexus_guest_presentation['body_classes'] : '' ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>">
    <div class="app-wrapper text-sm">
        <?php if ($nexus_guest_enabled) { ?>
            <header class="nexus-guest-masthead d-print-none">
                <div class="container nexus-guest-masthead-inner">
                    <a class="nexus-guest-brand" href="/login.php" aria-label="<?= escapeHtml($nexus_guest_brand) ?> support portal">
                        <?php if ($nexus_guest_logo !== '') { ?>
                            <img class="nexus-guest-logo-screen" data-nexus-color-logo src="<?= escapeHtml($nexus_guest_logo) ?>" alt="<?= escapeHtml($nexus_guest_settings['branding']['logo_alt'] ?: $nexus_guest_brand . ' logo') ?>">
                            <?php if ($nexus_guest_print_logo_raw !== '' && $nexus_guest_print_logo_raw !== $nexus_guest_logo_raw) { ?>
                                <img class="nexus-guest-logo-print" src="<?= escapeHtml($nexus_guest_print_logo) ?>" alt="">
                            <?php } else { ?>
                                <strong class="nexus-guest-brand-print-text"><?= escapeHtml($nexus_guest_brand) ?></strong>
                            <?php } ?>
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

<?php

    // Calculate Execution time start
    // uncomment for test
    // $time_start = microtime(true);

header("X-Frame-Options: DENY");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
nexusThemeApplyDueSchedule();
$nexus_theme_enabled = nexusThemeIsEnabled();
$nexus_theme_settings = nexusThemeSettings();
$nexus_brand_name = nexusThemeBrandName($session_company_name, $nexus_theme_settings);
$nexus_native_favicon = is_file($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico') ? '/uploads/favicon.ico' : '';
$nexus_favicon_url = $nexus_theme_enabled ? nexusThemeVersionedAssetUrl(nexusThemeFaviconUrl($nexus_theme_settings, $nexus_native_favicon), $nexus_theme_settings) : $nexus_native_favicon;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?= escapeHtml($nexus_theme_enabled ? nexusThemePageTitle($session_company_name, '', $nexus_theme_settings) : $session_company_name) ?></title>

    <!-- Favicon -->
    <?php if($nexus_favicon_url !== '') { ?>
        <link rel="icon" href="<?= escapeHtml($nexus_favicon_url) ?>">
    <?php } ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="/libs/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" >
    <link rel="stylesheet" href="/libs/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libs/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="/libs/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="/libs/toastr/toastr.min.css">
    <link rel="stylesheet" href="/libs/DataTables/datatables.min.css">
    <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <?php if ($nexus_theme_enabled) { ?>
        <!-- Nexus theme: intentionally loaded after AdminLTE -->
        <link rel="stylesheet" href="/css/nexus-theme.css">
        <link rel="stylesheet" href="/css/nexus-theme-custom.php?v=<?= nexusThemeSettingsVersion() ?>">
    <?php } ?>

    <!-- Scripts -->
    <script src="/libs/jquery/jquery.min.js"></script>
    <script src="/libs/toastr/toastr.min.js"></script>
</head>
<body class="
    hold-transition sidebar-mini layout-fixed layout-navbar-fixed <?= $nexus_theme_enabled ? 'nexus-theme nexus-agent ' . nexusThemeBodyClasses($nexus_theme_settings) : '' ?>
    accent-<?= escapeHtml($config_theme) ?>
    <?php if ($user_config_theme_dark) echo 'dark-mode'; ?>
">
    <div class="wrapper text-sm">

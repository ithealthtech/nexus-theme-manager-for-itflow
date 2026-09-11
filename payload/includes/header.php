<?php

    // Calculate Execution time start
    // uncomment for test
    // $time_start = microtime(true);

header("X-Frame-Options: DENY");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
nexusThemeApplyDueSchedule();
$nexus_theme_enabled = nexusThemeRuntimeEnabled();
$nexus_theme_settings = nexusThemeSettings();
$nexus_theme_presentation = nexusThemePresentationModel($nexus_theme_settings, $session_company_name, 'technician');
$nexus_brand_name = $nexus_theme_presentation['brand'];
$nexus_native_favicon = is_file($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico') ? '/uploads/favicon.ico' : '';
$nexus_favicon_url = $nexus_theme_enabled ? nexusThemeVersionedAssetUrl(nexusThemeFaviconUrl($nexus_theme_settings, $nexus_native_favicon), $nexus_theme_settings) : $nexus_native_favicon;

/* 26.09 made the server-rendered mode authoritative: AdminLTE 4's own colour-mode
   manager is switched off (data-lte-color-mode="off") and ITFlow paints
   data-bs-theme from user_settings.user_config_theme_dark before first paint.

   Nexus dark mode is a superset of that one boolean - system, user-selectable,
   forced and scheduled - so when it is running it decides the initial mode, and
   nexusThemeColorModeScript() keeps data-bs-theme, data-color-scheme and the
   color-scheme meta in step with it on every later change. When Nexus is off, or
   its dark mode is set to follow ITFlow, this resolves straight back to
   $user_config_theme_dark and the native behaviour is unchanged. */
$nexus_dark_initial = $nexus_theme_enabled
    ? nexusThemeInitialDarkMode($nexus_theme_settings, (bool)$user_config_theme_dark)
    : (bool)$user_config_theme_dark;

?>

<!DOCTYPE html>
<?php /* data-color-scheme is FullCalendar v7's own switch - its themes ship a
         dark palette keyed on [data-color-scheme=dark] that nothing was turning on */ ?>
<?php /* data-bs-theme is emitted for BOTH modes on purpose. AdminLTE 4 ships a
         colour-mode manager that runs at DOMContentLoaded and resolves the theme
         as localStorage['lte-theme'] ?? the markup attribute ?? prefers-color-scheme.
         With no attribute in the markup it falls through to the OS preference and
         sets data-bs-theme AFTER first paint - which is the light-to-dark flash.
         data-lte-color-mode="off" disables that manager outright: ITFlow holds the
         per-user setting in user_settings.user_config_theme_dark, so a browser-local
         localStorage key or an OS preference must not be able to override it. */ ?>
<html lang="en" data-bs-theme="<?= $nexus_dark_initial ? 'dark' : 'light' ?>"<?php if ($nexus_dark_initial) echo ' data-color-scheme="dark"'; ?> data-lte-color-mode="off" data-lte-print="plain">
<head>
    <meta charset="utf-8">
    <?php /* Must come BEFORE the stylesheets. The browser applies a meta
             color-scheme while it parses the head, so the very first paint is
             already dark. Left to CSS alone the only declaration is
             [data-bs-theme=dark]{color-scheme:dark} inside adminlte.min.css,
             which is ~580KB of render-blocking CSS away - the white canvas
             painted while that loads is the dark-mode flash. */ ?>
    <meta name="color-scheme" content="<?= $nexus_dark_initial ? 'dark' : 'light' ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?= escapeHtml($nexus_theme_enabled ? nexusThemePageTitle($session_company_name, '', $nexus_theme_settings) : $session_company_name) ?></title>

    <!-- Favicon -->
    <?php if($nexus_favicon_url !== '') { ?>
        <link rel="icon" href="<?= escapeHtml($nexus_favicon_url) ?>">
    <?php } ?>

    <?php /* The Tom Select pair is the first thing includes/footer.php runs,
             because until it does the browser is showing raw <select>
             controls. Preloading here starts both fetches during head parse,
             in parallel with the stylesheets, so the bytes are already warm
             when the parser reaches the tag instead of being requested only
             at that point. Hints only - the <script> tags in footer.php are
             what actually load them. */ ?>
    <link rel="preload" as="script" href="/libs/tom-select/js/tom-select.complete.min.js">
    <link rel="preload" as="script" href="/js/tom_select.js">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="/libs/flatpickr/css/flatpickr.min.css">
    <link rel="stylesheet" href="/libs/tom-select/css/tom-select.bootstrap5.min.css">
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">
    <link rel="stylesheet" href="/libs/DataTables/datatables.min.css">
    <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <?php /* Opt-in AdminLTE 3 palette, new in v4.5.0. Supplies the
             --bs-<colour> tokens plus .text-bg-* / .card-* / .callout-*
             / .bg-gradient-* families. Loads BEFORE itflow_custom.css so
             our own .bg-<colour> box colours still win. */ ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-colors-v3.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
    <?php if ($nexus_theme_enabled) { ?>
        <?php /* Nexus loads last on purpose: it has to win over both AdminLTE 4 and
                 itflow_custom.css, the AdminLTE 3 compatibility layer 26.09 added.
                 It consumes --itflow-accent rather than redeclaring the theme
                 colour, so an ITFlow theme change still moves the accent. */ ?>
        <link rel="stylesheet" href="/css/nexus-theme.css?v=<?= escapeHtml(NEXUS_THEME_VERSION) ?>">
        <link rel="stylesheet" href="/css/nexus-theme-custom.php?v=<?= nexusThemeSettingsVersion() ?>">
    <?php } ?>

    <!-- Scripts -->
    <?php if ($nexus_theme_enabled) { ?><script><?= nexusThemeColorModeScript($nexus_theme_settings) ?></script><?php } ?>
</head>
<?php /* intl-tel-input needs an ISO2 country, the companies table stores a
         country NAME - $country_iso2_array bridges the two. Passed as a data
         attribute rather than an inline <script> so it does not add to the
         CSP unsafe-inline debt. Empty when the company has no country set,
         which js/app.js reads as "let the library decide". */ ?>
<?php /* bg-body-tertiary is what tints the page behind the cards. AdminLTE 3 painted
         it on .content-wrapper; v4's .app-main has no background at all and the tint
         moved to this body utility, so the migration dropped it silently. It matters
         because --bs-card-bg is --bs-body-bg - without it every card is the exact
         colour of the page and only its border separates the two. */ ?>
<body class="layout-fixed sidebar-expand-lg app-loaded bg-body-tertiary theme-<?= escapeHtml($config_theme) ?> <?= $nexus_theme_enabled ? 'nexus-theme nexus-agent ' . $nexus_theme_presentation['body_classes'] : '' ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>"
      data-itflow-phone-country="<?= escapeHtml($country_iso2_array[$session_company_country] ?? '') ?>">
    <div class="app-wrapper text-sm">
        <?php if ($nexus_theme_enabled) { ?><script><?= nexusThemeNavigationScript($nexus_theme_settings, !empty($session_is_admin) ? 'admin' : 'tech') ?></script><?php } ?>

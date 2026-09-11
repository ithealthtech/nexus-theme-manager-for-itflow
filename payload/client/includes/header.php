<?php
/*
 * Client Portal
 * HTML Header
 */

header("X-Frame-Options: DENY"); // Legacy

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
nexusThemeApplyDueSchedule();
$nexus_theme_enabled = nexusThemeRuntimeEnabled();
$nexus_theme_settings = nexusThemeSettings();
$nexus_theme_presentation = nexusThemePresentationModel($nexus_theme_settings, $session_company_name, 'client');
$nexus_brand_name = $nexus_theme_presentation['brand'];
$nexus_logo_url = nexusThemeVersionedAssetUrl(nexusThemeLogoUrl($nexus_theme_settings, $session_company_logo ? '/uploads/settings/' . $session_company_logo : '', nexusThemeLogoVariantForColor($nexus_theme_settings['colors']['sidebar'])), $nexus_theme_settings);
$nexus_portal_has_logo = $nexus_theme_enabled && $nexus_theme_settings['branding']['show_portal_logo'] && $nexus_logo_url !== '';
$nexus_native_favicon = is_file($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico') ? '/uploads/favicon.ico' : '';
$nexus_favicon_url = $nexus_theme_enabled ? nexusThemeVersionedAssetUrl(nexusThemeFaviconUrl($nexus_theme_settings, $nexus_native_favicon), $nexus_theme_settings) : $nexus_native_favicon;
?>

<!DOCTYPE html>
<html lang="en" data-lte-print="plain">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= escapeHtml($nexus_theme_enabled ? nexusThemePageTitle($session_company_name, 'Client Portal', $nexus_theme_settings) : $session_company_name . ' | Client Portal') ?></title>

    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <!-- Favicon: If Fav Icon exists, else use the default one -->
    <?php if($nexus_favicon_url !== '') { ?>
        <link rel="icon" href="<?= escapeHtml($nexus_favicon_url) ?>">
    <?php } ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <?php /* Opt-in AdminLTE 3 palette, new in v4.5.0. Supplies the
             --bs-<colour> tokens plus .text-bg-* / .card-* / .callout-*
             / .bg-gradient-* families. Loads BEFORE itflow_custom.css so
             our own .bg-<colour> box colours still win. */ ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-colors-v3.min.css">
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">

    <!-- ITFlow style: the AdminLTE 3 compatibility layer and the theme colours. Must load
         last so it wins, and must load HERE too - it used to be on the agent header only,
         which left this portal without .text-bold, the bg-dark text pairing or any theme. -->
    <link rel="stylesheet" href="/css/itflow_custom.css">

    <?php /* Nexus loads after itflow_custom.css so the managed theme wins over the
             AdminLTE 3 compatibility layer, and reads --itflow-accent rather than
             redefining the theme colour beside it. */ ?>
    <?php if ($nexus_theme_enabled) { ?>
        <link rel="stylesheet" href="/css/nexus-theme.css?v=<?= escapeHtml(NEXUS_THEME_VERSION) ?>">
        <link rel="stylesheet" href="/css/nexus-theme-custom.php?v=<?= nexusThemeSettingsVersion() ?>">
    <?php } ?>

    <?php /* Only the pages that set this flag before including inc_all.php pull
             in intl-tel-input - it is ~200KB of JS and flag sprites, and one
             page uses it. */ ?>
    <?php if (!empty($portal_load_phone_inputs)) { ?>
        <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <?php } ?>

    <?php if (!empty($portal_load_datatables)) { ?>
        <link rel="stylesheet" href="/libs/DataTables/datatables.min.css">
    <?php } ?>

    <?php if ($nexus_theme_enabled) { ?><script><?= nexusThemeColorModeScript($nexus_theme_settings) ?></script><?php } ?>
</head>

<?php /* Same country bridge as the agent header: intl-tel-input wants an ISO2
         code, the companies table stores a country NAME. Given as a data
         attribute rather than an inline <script>, which this portal's CSP
         (default-src 'self') would block outright. Empty when the company has
         no country set, which js/phone_inputs.js reads as "let the library
         decide".

         The layout classes are upstream's and must stay: the portal has no
         .app-wrapper grid, so d-flex flex-column min-vh-100 here is what lets
         footer.php's mt-auto hold the bottom edge. Nexus classes append. */ ?>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary theme-<?= escapeHtml($config_theme) ?> <?= $nexus_theme_enabled ? 'nexus-theme nexus-client ' . $nexus_theme_presentation['body_classes'] : '' ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>"
      data-itflow-phone-country="<?= escapeHtml($country_iso2_array[$session_company_country] ?? '') ?>">

<a class="visually-hidden visually-hidden-focusable" href="#main-content">Skip to main content</a>

<!-- Navbar -->

<nav class="navbar navbar-expand-lg navbar-dark <?= $nexus_theme_enabled ? 'nexus-client-nav' : 'bg-dark' ?>">
    <div class="container">
        <a class="navbar-brand <?= $nexus_portal_has_logo ? 'nexus-client-brand--logo' : 'nexus-client-brand--text' ?>" href="index.php" aria-label="<?= escapeHtml(($nexus_theme_enabled ? $nexus_brand_name : $session_company_name) . ' home') ?>">
            <?php if ($nexus_portal_has_logo) { ?>
                <img height="48" width="176" class="nexus-client-nav-logo" data-nexus-color-logo src="<?= escapeHtml($nexus_logo_url) ?>" alt="<?= escapeHtml($nexus_theme_settings['branding']['logo_alt'] ?: $nexus_brand_name . ' logo') ?>">
            <?php } else { ?>
                <span><?= escapeHtml($nexus_theme_enabled ? $nexus_brand_name : $session_company_name) ?></span>
            <?php } ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle portal navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item <?php if (basename($_SERVER['PHP_SELF']) == "index.php") {echo "active";} ?>">
                    <a class="nav-link" href="/client/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "tickets.php" || basename($_SERVER['PHP_SELF']) == "ticket_add.php" || basename($_SERVER['PHP_SELF']) == "ticket.php") {echo "active";} ?>" href="/client/tickets.php">Tickets</a>
                </li>

                <?php if (contactCan('accounting') && $config_module_enable_accounting == 1) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['invoices.php', 'quotes.php', 'autopay.php', 'statement.php']) ? 'active' : '' ?>" href="#" id="navbarDropdown1" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Finance
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdown1">
                            <a class="dropdown-item" href="/client/invoices.php">Invoices</a>
                            <a class="dropdown-item" href="/client/recurring_invoices.php">Recurring Invoices</a>
                            <a class="dropdown-item" href="/client/quotes.php">Quotes</a>
                            <a class="dropdown-item" href="/client/statement.php">Account Statement</a>
                            <a class="dropdown-item" href="/client/saved_payment_methods.php">Saved Payments</a>
                        </div>
                    </li>
                <?php } ?>

                <?php if ($config_module_enable_itdoc && contactCan('itdoc')) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['documents.php', 'contacts.php', 'domains.php', 'certificates.php']) ? 'active' : '' ?>" href="#" id="navbarDropdown2" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Technical
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdown2">
                            <a class="dropdown-item" href="/client/contacts.php">Contacts</a>
                            <a class="dropdown-item" href="/client/assets.php">Assets</a>
                            <a class="dropdown-item" href="/client/documents.php">Documents</a>
                            <a class="dropdown-item" href="/client/domains.php">Domains</a>
                            <a class="dropdown-item" href="/client/certificates.php">Certificates</a>
                            <a class="dropdown-item" href="/client/ticket_view_all.php">All tickets</a>
                        </div>
                    </li>
                <?php } ?>

                <?php
                $sql_custom_links = mysqli_query($mysqli, "SELECT custom_link_name, custom_link_new_tab, custom_link_uri FROM custom_links WHERE custom_link_location = 3 AND custom_link_archived_at IS NULL
                    ORDER BY custom_link_order ASC, custom_link_name ASC"
                );

                while ($row = mysqli_fetch_assoc($sql_custom_links)) {
                    $custom_link_name = escapeHtml($row['custom_link_name']);
                    $custom_link_uri = escapeHtml($row['custom_link_uri']);
                    $custom_link_new_tab = intval($row['custom_link_new_tab']);
                    if ($custom_link_new_tab == 1) {
                        $target = "target='_blank' rel='noopener noreferrer'";
                    } else {
                        $target = "";
                    }

                    ?>

                    <li class="nav-item">
                        <a href="<?= $custom_link_uri ?>" <?= $target ?> class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == basename($custom_link_uri)) { echo "active"; } ?>"><?= $custom_link_name ?></a>
                    </li>

                <?php } ?>

            </ul><!-- End left nav -->

            <ul class="nav navbar-nav ms-auto">
                <?php if ($nexus_theme_enabled) { ?>
                <li class="nav-item me-lg-2">
                    <a class="btn nexus-portal-cta" href="/client/ticket_add.php"><i class="fas fa-plus me-2" aria-hidden="true"></i>Create support request</a>
                </li>
                <?php } ?>
                <?php if ($nexus_theme_enabled && $nexus_theme_settings['dark_mode']['user_selectable']) { ?>
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Choose color mode"><i class="fas fa-adjust"></i></a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <button type="button" class="dropdown-item" onclick="window.nexusSetColorMode('light')">Light</button>
                        <button type="button" class="dropdown-item" onclick="window.nexusSetColorMode('dark')">Dark</button>
                        <button type="button" class="dropdown-item" onclick="window.nexusSetColorMode('system')">System</button>
                    </div>
                </li>
                <?php } ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <?= stripslashes(escapeHtml($session_contact_name)) ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="/client/profile.php"><i class="fas fa-fw fa-user me-2"></i>Account</a>
                        <a class="dropdown-item" href="/client/activity.php"><i class="fas fa-fw fa-list me-2"></i>Activity</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/client/post.php?logout"><i class="fas fa-fw fa-sign-out-alt me-2"></i>Sign out</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php /* The gap under the navbar used to be a bare line-break element sitting
         directly in the body. That stopped working the moment the body became a
         flex column: a break is only a break inside an inline formatting
         context, and as a flex item it is blockified into an empty zero-height
         box - so the gap vanished and the welcome row rode up against the
         navbar.

         Carried on the container as a real margin instead. mt-4 is 1.5rem,
         exactly what the break was worth: one line box at the body's 1.5
         line-height on a 1rem font.

         Nexus promotes the container to <main> so the skip link has a target;
         footer.php closes </main> to match. */ ?>
<!-- Page content container -->
<main id="main-content" class="container mt-4 <?= $nexus_theme_enabled ? 'nexus-client-shell' : '' ?>">

    <div class="row mb-3 <?= $nexus_theme_enabled ? 'nexus-client-welcome' : '' ?>">
        <div class="col-md-1 text-center <?= $nexus_theme_enabled ? 'nexus-avatar' : '' ?>">
            <?php if (!empty($session_contact_photo)) { ?>
                <img src="/uploads/clients/<?= $session_client_id ?>/<?= $session_contact_photo ?>" alt="<?= escapeHtml($session_contact_name) ?> profile photo" height="50" width="50" class="rounded-circle img-fluid">

            <?php } else { ?>
                <span class="fa-stack fa-2x rounded-start">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?= $session_contact_initials ?></span>
                </span>
            <?php } ?>
        </div>

        <div class="col-md-11 p-0">
                <?php if (!$nexus_theme_enabled && $session_company_logo) { ?>
                    <img height="48" width="142" class="img-fluid float-end nexus-client-logo" src="<?= "/uploads/settings/$session_company_logo" ?>" alt="<?= escapeHtml($session_company_name) ?> logo">
                <?php } ?>
            <p class="h4 mb-1"><?= $nexus_theme_enabled ? escapeHtml($nexus_theme_settings['content']['portal_heading']) : 'Welcome' ?>, <strong><?= stripslashes(escapeHtml($session_contact_name)) ?></strong></p>
            <?php if ($nexus_theme_enabled && $nexus_theme_settings['content']['portal_message'] !== '') { ?><p class="mb-0 text-muted"><?= nl2br(escapeHtml($nexus_theme_settings['content']['portal_message'])) ?></p><?php } ?>
        </div>
    </div>
    <?php if (!$nexus_theme_enabled) { ?><hr><?php } ?>

    <?php
    //Alert Feedback
    if (!empty($_SESSION['alert_message'])) {
        if (!isset($_SESSION['alert_type'])) {
            $_SESSION['alert_type'] = "info";
        }
        ?>
        <div class="alert alert-<?= alertStyleClass($_SESSION['alert_type'] ?? 'success') ?> alert-dismissible" id="alert" role="status" aria-live="polite">
            <?= alertMessageHtml($_SESSION['alert_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php

        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);

    }
    ?>

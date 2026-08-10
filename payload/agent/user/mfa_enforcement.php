<?php
require_once "../../config.php";
require_once "../../functions.php";
require_once "../../includes/check_login.php";
require_once '../../libs/totp/totp.php'; //TOTP MFA Lib
require_once '../../includes/nexus_theme.php';

$nexus_theme_enabled = nexusThemeIsEnabled();
$nexus_theme_settings = nexusThemeSettings();

// Get Company Logo
$sql = mysqli_query($mysqli, "SELECT company_logo FROM companies");
$row = mysqli_fetch_assoc($sql);
$company_logo = escapeHtml($row['company_logo']);


// Only generate the token once and store it in session:
if (empty($_SESSION['mfa_token'])) {
    $token = generateTotpSecret();
    $_SESSION['mfa_token'] = $token;
}
$token = $_SESSION['mfa_token'];

// Generate QR Code
$data = "otpauth://totp/ITFlow:$session_email?secret=$token";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title>MFA Enforcement | <?= escapeHtml($nexus_theme_enabled ? nexusThemeBrandName($session_company_name, $nexus_theme_settings) : $session_company_name) ?></title>

    <!--
    Favicon
    If Fav Icon exists else use the default one
    -->
    <?php if(file_exists('../../uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="../../uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="../../libs/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="../../libs/adminlte/css/adminlte.min.css">
    <link href="../../libs/toastr/toastr.min.css" rel="stylesheet">
    <?php if ($nexus_theme_enabled) { ?>
        <link rel="stylesheet" href="../../css/nexus-theme.css">
        <link rel="stylesheet" href="../../css/nexus-theme-custom.php?v=<?= nexusThemeSettingsVersion() ?>">
    <?php } ?>

    <!-- jQuery -->
    <script src="../../libs/jquery/jquery.min.js"></script>
    <script src="../../libs/toastr/toastr.min.js"></script>

</head>
<body class="hold-transition login-page <?= $nexus_theme_enabled ? 'nexus-theme nexus-auth ' . nexusThemeBodyClasses($nexus_theme_settings) : '' ?>">
    <?php require_once "../../includes/inc_alert_feedback.php"; ?>
    <div class="login-box">
        <div class="login-logo">
            <?php $nexus_mfa_logo = nexusThemeLogoUrl($nexus_theme_settings, !empty($company_logo) ? "/uploads/settings/$company_logo" : ''); ?>
            <?php if ($nexus_theme_settings['branding']['show_login_logo'] && $nexus_mfa_logo !== '') { ?>
                <img alt="<?= escapeHtml($nexus_theme_settings['branding']['logo_alt'] ?: nexusThemeBrandName($company_name, $nexus_theme_settings) . ' logo') ?>" height="110" width="380" class="img-fluid" src="<?= escapeHtml($nexus_mfa_logo) ?>">
            <?php } else { ?>
                <span class="nexus-fallback-logo"><i class="fas fa-layer-group mr-2" aria-hidden="true"></i><?= escapeHtml(nexusThemeBrandName($session_company_name, $nexus_theme_settings)) ?></span>
            <?php } ?>
        </div>

        <!-- /.login-logo -->
        <div class="card">
            <div class="card-body login-card-body text-center">

                <span class="nexus-eyebrow">Account protection</span>
                <h1 class="nexus-auth-title">Set up multi-factor authentication</h1>
                <p class="nexus-auth-copy">Scan the QR code with your authenticator app, then enter the six-digit code to confirm setup.</p>

                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <img src='../../libs/barcode/barcode.php?f=png&s=qr&d=<?= $data ?>' data-toggle="tooltip" title="Scan QR code into your MFA App" alt="QR code for authenticator setup">

                    <p>
                        <small data-toggle="tooltip" title="Can't Scan? Copy and paste this code into your app"><?= $token ?></small>
                        <button type="button" class='btn btn-sm clipboardjs' data-clipboard-text='<?= $token ?>'><i class='far fa-copy text-secondary'></i></button>
                    </p>

                    <label class="nexus-field-label text-left" for="verify-code">Authentication code</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="verify-code" inputmode="numeric" pattern="[0-9]*" minlength="6" maxlength="6" name="verify_code" autocomplete="one-time-code" placeholder="6-digit code" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="enable_mfa" class="btn btn-primary btn-block mb-3"><i class="fa fa-check mr-2"></i>Enable MFA</button>
                </form>

            </div>
            <!-- /.login-card-body -->
        </div>
    </div>
    <!-- /.login-box -->

    <!-- REQUIRED SCRIPTS -->

    <!-- Bootstrap 4 -->
    <script src="../../libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Custom js-->
    <script src="../../libs/clipboardjs/clipboard.min.js"></script>

    <script>

    // Slide alert up after 4 secs
    $("#alert").fadeTo(5000, 500).slideUp(500, function(){
        $("#alert").slideUp(500);
    });

    // ClipboardJS

    // Tooltip - manual trigger only, so a copy flash can never stay stranded
    // on screen. This page is standalone and does not load js/app.js, so it
    // carries its own copy of the helper.
    function flashTooltip(button, message) {
        $(button)
            .tooltip('dispose')
            .tooltip({
                trigger: 'manual',
                placement: 'bottom',
                title: message
            })
            .tooltip('show');

        setTimeout(function() {
            $(button).tooltip('dispose');
        }, 1000);
    }

    // Clipboard

    var clipboard = new ClipboardJS('.clipboardjs');

    clipboard.on('success', function(e) {
        flashTooltip(e.trigger, 'Copied!');
    });

    clipboard.on('error', function(e) {
        flashTooltip(e.trigger, 'Failed!');
    });

    // Enable Popovers
    $(function () {
        $('[data-toggle="popover"]').popover()
    });

    </script>

</body>

</html>

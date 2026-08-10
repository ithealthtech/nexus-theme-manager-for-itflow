<?php

defined('FROM_POST_HANDLER') || die('Direct file access is not allowed');

$nexusRedirect = static function (): never {
    header('Location: /admin/nexus.php');
    exit;
};

if (isset($_POST['nexus_theme_state'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    $requestedState = (string)$_POST['nexus_theme_state'];
    if (!in_array($requestedState, ['enable', 'disable'], true)) {
        flashAlert('Invalid Nexus theme action.', 'error');
        $nexusRedirect();
    }

    $enabled = $requestedState === 'enable';
    try {
        nexusThemeSetEnabled($enabled);
        $stateLabel = $enabled ? 'enabled' : 'paused';
        logAudit('Nexus Theme Manager', 'Edit', "$session_name $stateLabel the Nexus theme");
        flashAlert('Nexus theme ' . $stateLabel . '.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not update the theme state: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_save'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $current = nexusThemeSettings();
        $submitted = is_array($_POST['nexus'] ?? null) ? $_POST['nexus'] : [];
        $submitted['branding'] = is_array($submitted['branding'] ?? null) ? $submitted['branding'] : [];
        $submitted['branding']['logo_path'] = $current['branding']['logo_path'];

        if (isset($_FILES['nexus_logo']) && ($_FILES['nexus_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $submitted['branding']['logo_path'] = nexusThemeStoreLogo($_FILES['nexus_logo']);
        }

        nexusThemeSaveSettings($submitted);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name updated Nexus theme customization");
        flashAlert('Nexus customization saved and applied.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not save the customization: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_remove_logo'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $settings = nexusThemeSettings();
        nexusThemeRemoveLogo();
        $settings['branding']['logo_path'] = '';
        nexusThemeSaveSettings($settings);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name removed the Nexus custom logo");
        flashAlert('Custom logo removed. ITFlow branding will be used as the fallback.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not remove the logo: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_reset'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        nexusThemeResetSettings();
        logAudit('Nexus Theme Manager', 'Edit', "$session_name restored the Nexus customization defaults");
        flashAlert('Nexus customization restored to its defaults.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not restore defaults: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_update_action'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $action = (string)$_POST['nexus_update_action'];
        nexusUpdaterQueue($action);
        $label = $action === 'update' ? 'installation' : 'update check';
        logAudit('Nexus Theme Manager', 'Edit', "$session_name queued a protected Nexus $label");
        flashAlert('Nexus ' . $label . ' queued. This page will show progress when the protected updater starts.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not queue that updater action: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_import'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $decoded = json_decode((string)($_POST['nexus_import_json'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('The imported configuration must be a JSON object.');
        }
        $current = nexusThemeSettings();
        $decoded['branding'] = is_array($decoded['branding'] ?? null) ? $decoded['branding'] : [];
        $decoded['branding']['logo_path'] = $current['branding']['logo_path'];
        nexusThemeSaveSettings($decoded);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name imported Nexus theme customization");
        flashAlert('Nexus customization imported and applied.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not import that configuration: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

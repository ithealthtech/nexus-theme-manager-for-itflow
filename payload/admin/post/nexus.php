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
        foreach (['logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path', 'asset_revision'] as $asset) {
            $submitted['branding'][$asset] = $current['branding'][$asset];
        }

        $assetChanged = false;
        foreach (['nexus_logo_light' => ['logo_light_path', 'logo-light'], 'nexus_logo_dark' => ['logo_dark_path', 'logo-dark'], 'nexus_favicon' => ['favicon_path', 'favicon'], 'nexus_login_background' => ['login_background_path', 'login-background']] as $uploadName => [$setting, $slot]) {
            if (isset($_FILES[$uploadName]) && ($_FILES[$uploadName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $submitted['branding'][$setting] = nexusThemeStoreImage($_FILES[$uploadName], $slot);
                $assetChanged = true;
            }
        }
        if ($assetChanged) {
            $submitted['branding']['asset_revision'] = bin2hex(random_bytes(8));
        }
        $submitted['branding']['logo_path'] = $submitted['branding']['logo_light_path'];

        nexusThemeSaveSettings($submitted);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name updated Nexus theme customization");
        flashAlert('Nexus customization saved and applied.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not save the customization: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_remove_asset'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';

    try {
        $slot = (string)$_POST['nexus_theme_remove_asset'];
        $settingMap = ['logo-light' => 'logo_light_path', 'logo-dark' => 'logo_dark_path', 'favicon' => 'favicon_path', 'login-background' => 'login_background_path'];
        if (!isset($settingMap[$slot])) {
            throw new RuntimeException('Invalid Nexus asset action.');
        }
        $settings = nexusThemeSettings();
        $settings['branding'][$settingMap[$slot]] = '';
        if ($slot === 'logo-light') {
            $settings['branding']['logo_path'] = '';
        }
        $settings['branding']['asset_revision'] = bin2hex(random_bytes(8));
        nexusThemeSaveSettings($settings);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name removed a Nexus custom image");
        flashAlert('Custom image detached from the active design.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not remove the image: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_theme_rollback'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        nexusThemeRollback();
        logAudit('Nexus Theme Manager', 'Edit', "$session_name restored the previous Nexus design");
        flashAlert('Previous Nexus design restored.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not restore the previous design: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_preset_action'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $action = (string)$_POST['nexus_preset_action'];
        $id = (string)($_POST['nexus_preset_id'] ?? '');
        if ($action === 'save') {
            nexusThemeSavePreset((string)($_POST['nexus_preset_name'] ?? ''), nexusThemeSettings());
            $message = 'Theme preset saved.';
        } elseif ($action === 'apply' && preg_match('/^[a-f0-9]{16}$/', $id) === 1) {
            nexusThemeApplyPreset($id);
            $message = 'Theme preset applied.';
        } elseif ($action === 'delete' && preg_match('/^[a-f0-9]{16}$/', $id) === 1) {
            nexusThemeDeletePreset($id);
            $message = 'Theme preset deleted.';
        } elseif ($action === 'import') {
            $count = nexusThemeImportPresets((string)($_POST['nexus_presets_json'] ?? ''));
            $message = $count . ' theme preset' . ($count === 1 ? '' : 's') . ' imported.';
        } else {
            throw new RuntimeException('Invalid saved-preset action.');
        }
        logAudit('Nexus Theme Manager', 'Edit', "$session_name used Nexus saved presets");
        flashAlert($message);
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not complete the preset action: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}

if (isset($_POST['nexus_schedule_command'])) {
    validateCSRFToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/nexus_theme.php';
    try {
        $command = (string)$_POST['nexus_schedule_command'];
        if ($command === 'cancel') {
            nexusThemeCancelSchedule();
            $message = 'Scheduled theme action cancelled.';
        } elseif ($command === 'set') {
            $schedule = nexusThemeSetSchedule((string)($_POST['nexus_schedule_action'] ?? ''), (string)($_POST['nexus_schedule_at'] ?? ''));
            $message = 'Theme ' . ($schedule['action'] === 'enable' ? 'activation' : 'pause') . ' scheduled for ' . $schedule['activate_at'] . '.';
        } else {
            throw new RuntimeException('Invalid schedule action.');
        }
        logAudit('Nexus Theme Manager', 'Edit', "$session_name updated the Nexus activation schedule");
        flashAlert($message);
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not update the schedule: ' . escapeHtml($error->getMessage()), 'error');
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
        foreach (['logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_background_path', 'asset_revision'] as $asset) {
            $decoded['branding'][$asset] = $current['branding'][$asset];
        }
        nexusThemeSaveSettings($decoded);
        logAudit('Nexus Theme Manager', 'Edit', "$session_name imported Nexus theme customization");
        flashAlert('Nexus customization imported and applied.');
    } catch (Throwable $error) {
        logApp('Nexus Theme Manager', 'error', $error->getMessage());
        flashAlert('Nexus could not import that configuration: ' . escapeHtml($error->getMessage()), 'error');
    }
    $nexusRedirect();
}
